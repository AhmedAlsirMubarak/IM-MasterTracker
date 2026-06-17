<?php

namespace App\Services;

use App\Models\ImportSession;
use App\Models\ProjectWell;
use App\Models\SapTracking;
use App\Models\DcTracking;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ImportService
{
    /**
     * Read headers from an uploaded Excel/CSV file.
     * Uses streaming for XLSX (ZipArchive+XMLReader) to avoid loading the full shared-strings table.
     * Returns [sheet_name => [column_headers]]
     */
    public function readHeaders(string $storedPath): array
    {
        $fullPath = Storage::path($storedPath);
        $ext      = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return $this->readCsvHeaders($fullPath);
        }

        if ($ext === 'xlsx') {
            return $this->readXlsxHeaders($fullPath);
        }

        // XLS (binary) — use PhpSpreadsheet with row filter
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class implements IReadFilter {
                public function readCell($columnAddress, $row, $worksheetName = ''): bool {
                    return $row <= 10;
                }
            });
        }
        $spreadsheet = $reader->load($fullPath);
        $result = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '10', null, true, false);
            foreach ($rows as $row) {
                $filtered = array_values(array_filter($row, fn($v) => $v !== null && $v !== ''));
                if (!empty($filtered)) { $result[$sheet->getTitle()] = $filtered; break; }
            }
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        return $result;
    }

    private function readCsvHeaders(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return [];
        $row = fgetcsv($handle);
        fclose($handle);
        if (!$row) return [];
        $headers = array_values(array_filter($row, fn($v) => $v !== null && $v !== ''));
        return $headers ? ['Sheet1' => $headers] : [];
    }

    private function readXlsxHeaders(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open XLSX file.');
        }

        // workbook.xml and its rels are tiny — safe to load as strings
        $wbXml   = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!$wbXml || !$relsXml) { $zip->close(); return []; }

        $wb   = simplexml_load_string($wbXml);
        $rels = simplexml_load_string($relsXml);

        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $target = (string) $rel['Target'];
            $relMap[(string) $rel['Id']] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
        }

        $sheets = [];
        $ns  = $wb->getNamespaces(true);
        $rNs = $ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        foreach ($wb->sheets->sheet as $s) {
            $rId = (string) $s->attributes($rNs)['id'];
            if (isset($relMap[$rId])) {
                $sheets[(string) $s['name']] = $relMap[$rId];
            }
        }

        $tmpDir = storage_path('framework/cache');
        $result = [];

        foreach ($sheets as $sheetName => $sheetFile) {
            $tmpSheet = tempnam($tmpDir, 'xlsh_');
            $this->streamZipEntry($zip, $sheetFile, $tmpSheet);

            $cells = [];
            $xr    = new \XMLReader();
            $xr->open($tmpSheet);

            $inRow = false; $rowsSeen = 0; $cType = 'n'; $inV = false;
            while ($xr->read()) {
                $nt = $xr->nodeType; $ln = $xr->localName;
                if ($nt === \XMLReader::ELEMENT) {
                    if ($ln === 'row') {
                        if (++$rowsSeen > 1) break;
                        $inRow = true;
                    } elseif ($ln === 'c' && $inRow) {
                        $cType = $xr->getAttribute('t') ?? 'n';
                    } elseif ($ln === 'v' && $inRow) {
                        $inV = true;
                    }
                } elseif ($nt === \XMLReader::TEXT && $inV) {
                    $cells[] = [$cType, $xr->value]; $inV = false;
                } elseif ($nt === \XMLReader::END_ELEMENT && $ln === 'v') {
                    $inV = false;
                }
            }
            $xr->close();
            @unlink($tmpSheet);

            if (empty($cells)) continue;

            $needed = [];
            foreach ($cells as [$t, $v]) {
                if ($t === 's') $needed[(int) $v] = true;
            }

            $ss = [];
            if (!empty($needed) && $zip->statName('xl/sharedStrings.xml') !== false) {
                $tmpSs = tempnam($tmpDir, 'xlss_');
                $this->streamZipEntry($zip, 'xl/sharedStrings.xml', $tmpSs);
                $ss = $this->loadSelectiveSharedStrings($tmpSs, $needed);
                @unlink($tmpSs);
            }

            $headers = [];
            foreach ($cells as [$t, $v]) {
                $val = ($t === 's') ? ($ss[(int) $v] ?? '') : $v;
                if ($val !== '' && $val !== null) $headers[] = $val;
            }
            if (!empty($headers)) $result[$sheetName] = $headers;
        }

        $zip->close();
        return $result;
    }

    private function streamZipEntry(\ZipArchive $zip, string $entry, string $destFile): void
    {
        $in  = $zip->getStream($entry);
        if (!$in) return;
        $out = fopen($destFile, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }

    /**
     * Preview first 10 data rows applying the column mapping.
     */
    public function preview(string $storedPath, string $sheet, array $mapping): array
    {
        $fullPath = Storage::path($storedPath);
        $ext      = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // maxRows=11: 1 header + 10 data rows — two-pass strings keep memory tiny
        $rows = ($ext === 'xlsx')
            ? $this->streamAllRows($fullPath, $sheet, 11)
            : $this->readRowsViaSpreadsheet($fullPath, $sheet, 11);

        [$headerRow, $dataRows] = $this->splitHeaderData($rows);

        $preview = [];
        foreach (array_slice($dataRows, 0, 10) as $row) {
            $mapped = [];
            foreach ($mapping as $excelCol => $systemField) {
                if (empty($systemField)) continue;
                $colIndex = array_search($excelCol, $headerRow);
                $mapped[$systemField] = ($colIndex !== false) ? ($row[$colIndex] ?? null) : null;
            }
            $preview[] = $mapped;
        }
        return $preview;
    }

    /**
     * Run the full import. Called from ProcessImportJob.
     * For XLSX: streams rows one at a time via callback — never accumulates the whole sheet.
     * Each row's updateOrCreate is auto-committed to avoid long-held MySQL gap locks
     * on the jobs table that block new job inserts.
     */
    public function execute(ImportSession $session): void
    {
        ini_set('memory_limit', '512M');
        $session->update(['status' => 'processing']);

        $fullPath  = Storage::path($session->stored_path);
        $ext       = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $sheetName = $session->sheet_name ?? '';
        $mapping   = $session->column_mapping;

        $processed        = 0;
        $skipped          = 0;
        $errors           = [];
        $headerRow        = null;
        $totalRows        = 0;
        $lastUpdate       = time();
        $consecutiveEmpty = 0; // early-exit: stop after 500 consecutive rows with no well_id

        try {
            $processRow = function (array $row, int $rowNum) use (
                &$headerRow, &$processed, &$skipped, &$errors, &$totalRows, &$lastUpdate, &$consecutiveEmpty, $session, $mapping
            ): ?bool {
                // First non-empty row is the header
                if ($headerRow === null) {
                    $filtered = array_filter($row, fn($v) => $v !== null && $v !== '');
                    if (!empty($filtered)) {
                        $headerRow = array_values($row);
                    }
                    return null;
                }

                $totalRows++;

                try {
                    $rowData = $this->mapRow(array_values($row), $headerRow, $mapping);

                    $hasWellId     = !empty($rowData['well_id']     ?? null);
                    $hasWellNumber = !empty($rowData['well_number'] ?? null);

                    if (!$hasWellId && !$hasWellNumber) {
                        $skipped++;
                        // Stop reading after 500 consecutive rows with no identifier — handles Excel
                        // sheets padded to 1,048,576 rows where only the first ~60 have real data
                        if (++$consecutiveEmpty >= 500) {
                            return false;
                        }
                    } else {
                        $consecutiveEmpty = 0;

                        // Try to find an existing well by well_id first, then by well_number
                        $well = null;
                        if ($hasWellId) {
                            $well = ProjectWell::withTrashed()
                                ->where('project_id', $session->project_id)
                                ->where('well_id', $rowData['well_id'])
                                ->first();
                        }
                        if (!$well && $hasWellNumber) {
                            $well = ProjectWell::withTrashed()
                                ->where('project_id', $session->project_id)
                                ->where('well_number', $rowData['well_number'])
                                ->first();
                        }

                        // Ensure well_id is set (required for create)
                        if (!$hasWellId) {
                            $rowData['well_id'] = $well?->well_id ?? $rowData['well_number'];
                        }

                        $updateData = array_merge(
                            array_intersect_key($rowData, array_flip(
                                array_merge(ProjectWell::$fixedColumns, ProjectWell::$managerColumns)
                            )),
                            ['updated_by' => $session->uploaded_by]
                        );

                        if ($well) {
                            if ($well->trashed()) $well->restore();

                            // If the year is changing, record the old year so the UI can show "shifted from X"
                            $newYear = $updateData['year'] ?? null;
                            if ($newYear && $well->year && (int) $newYear !== (int) $well->year) {
                                $updateData['previous_year'] = $well->year;
                            }

                            $well->update($updateData);
                        } else {
                            $well = ProjectWell::create(array_merge(
                                ['project_id' => $session->project_id, 'well_id' => $rowData['well_id']],
                                $updateData,
                                ['created_by' => $session->uploaded_by]
                            ));
                        }

                        if (in_array($session->target_table, ['sap_tracking', 'all'])) {
                            $sapFillable = array_diff((new SapTracking)->getFillable(), ['project_well_id', 'updated_by', 'sap_kpi']);
                            $sapFields   = array_intersect_key($rowData, array_flip($sapFillable));
                            if (!empty($sapFields)) {
                                SapTracking::updateOrCreate(
                                    ['project_well_id' => $well->id],
                                    array_merge($sapFields, ['updated_by' => $session->uploaded_by])
                                );
                            }
                        }

                        if (in_array($session->target_table, ['dc_tracking', 'all'])) {
                            $dcFillable = array_diff((new DcTracking)->getFillable(), ['project_well_id', 'updated_by']);
                            $dcFields   = array_intersect_key($rowData, array_flip($dcFillable));
                            if (!empty($dcFields)) {
                                DcTracking::updateOrCreate(
                                    ['project_well_id' => $well->id],
                                    array_merge($dcFields, ['updated_by' => $session->uploaded_by])
                                );
                            }
                        }

                        $processed++;
                    }
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                }

                // Time-based update (every 3 s) instead of count-based — avoids 20k DB writes for huge sheets
                $now = time();
                if ($now - $lastUpdate >= 3) {
                    $session->update([
                        'processed_rows' => $processed,
                        'skipped_rows'   => $skipped,
                        'total_rows'     => $totalRows,
                    ]);
                    $lastUpdate = $now;
                }

                return null;
            };

            if ($ext === 'xlsx') {
                $this->forEachXlsxRow($fullPath, $sheetName, $processRow);
            } else {
                $rows = $this->readRowsViaSpreadsheet($fullPath, $sheetName);
                foreach ($rows as $i => $row) {
                    $processRow($row, $i + 1);
                }
            }

            $session->update([
                'status'         => 'completed',
                'processed_rows' => $processed,
                'skipped_rows'   => $skipped,
                'total_rows'     => $totalRows,
                'error_log'      => implode("\n", $errors) ?: null,
            ]);
        } catch (\Exception $e) {
            $session->update([
                'status'    => 'failed',
                'error_log' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Stream XLSX rows one at a time via callback — O(unique_strings) memory, not O(total_rows).
     * Uses zip:// stream wrapper so XMLReader decompresses on-the-fly; no temp file for the sheet.
     * Pass 1 is limited to 2000 rows (real data always ends well before that).
     * Callback may return false to stop iteration early.
     */
    private function forEachXlsxRow(string $path, string $sheetName, callable $callback): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open XLSX file.');
        }
        $sheetFile = $this->findSheetFile($zip, $sheetName);
        if (!$sheetFile) {
            $zip->close();
            throw new \RuntimeException("Sheet '{$sheetName}' not found in workbook.");
        }
        $hasStyles = $zip->statName('xl/styles.xml')        !== false;
        $hasSs     = $zip->statName('xl/sharedStrings.xml') !== false;
        $zip->close();

        // zip:// decompresses on-the-fly; avoids writing the 200MB+ sheet XML to disk
        $base     = 'zip://' . str_replace('\\', '/', realpath($path)) . '#';
        $sheetUrl = $base . $sheetFile;

        $isDate = $hasStyles ? $this->loadDateStyles($base . 'xl/styles.xml') : [];

        // Pass 1: scan first 2000 rows only — real data ends at ~60 rows; the 500-consecutive-empty
        // early-exit cushion means we never need string indices past row ~560
        $needed = $this->collectUsedStringIndices($sheetUrl, 2000);
        $ss = ($hasSs && !empty($needed))
            ? $this->loadSelectiveSharedStrings($base . 'xl/sharedStrings.xml', $needed)
            : [];

        // Pass 2: stream rows — callback returns false to break early
        $this->streamRowsViaCallback($sheetUrl, $ss, $isDate, $callback);
    }

    /**
     * Collect every shared-string index that appears in the sheet (no data kept, just integer keys).
     */
    private function collectUsedStringIndices(string $tmpSheetFile, int $maxRows = PHP_INT_MAX): array
    {
        $xr       = new \XMLReader();
        $xr->open($tmpSheetFile);
        $needed   = [];
        $cType    = 'n';
        $inV      = false;
        $rowCount = 0;

        while ($xr->read()) {
            $nt = $xr->nodeType;
            $ln = $xr->localName;
            if ($nt === \XMLReader::ELEMENT) {
                if ($ln === 'row') {
                    if (++$rowCount > $maxRows) break;
                } elseif ($ln === 'c') {
                    $cType = $xr->getAttribute('t') ?? 'n';
                    $inV   = false;
                } elseif ($ln === 'v') {
                    $inV = true;
                }
            } elseif ($nt === \XMLReader::TEXT && $inV && $cType === 's') {
                $needed[(int) $xr->value] = true;
                $inV = false;
            } elseif ($nt === \XMLReader::END_ELEMENT && $ln === 'v') {
                $inV = false;
            }
        }
        $xr->close();
        return $needed;
    }

    /**
     * Load only the string indices listed in $needed from the sharedStrings temp file.
     * Stops reading as soon as all needed strings are found.
     */
    private function loadSelectiveSharedStrings(string $tmpFile, array $needed): array
    {
        $ss    = [];
        $xr    = new \XMLReader();
        $xr->open($tmpFile);
        $inSi  = false;
        $cur   = '';
        $idx   = 0;
        $found = 0;
        $total = count($needed);

        while ($xr->read() && $found < $total) {
            $nt = $xr->nodeType;
            $ln = $xr->localName;
            if ($nt === \XMLReader::ELEMENT) {
                if ($ln === 'si') { $inSi = true; $cur = ''; }
                elseif ($ln === 't' && $inSi) {
                    $xr->read();
                    if ($xr->nodeType === \XMLReader::TEXT) $cur .= $xr->value;
                }
            } elseif ($nt === \XMLReader::END_ELEMENT && $ln === 'si') {
                if (isset($needed[$idx])) { $ss[$idx] = $cur; $found++; }
                $idx++; $inSi = false;
            }
        }
        $xr->close();
        return $ss;
    }

    /**
     * Stream rows via callback — never accumulates the full sheet in memory.
     * Callback may return false to stop iteration early (e.g. after N consecutive empty rows).
     */
    private function streamRowsViaCallback(string $tmpFile, array $ss, array $isDate, callable $callback): void
    {
        $xr       = new \XMLReader();
        $xr->open($tmpFile);
        $curRow   = null;
        $cRef     = null;
        $cType    = 'n';
        $cStyle   = -1;
        $inV      = false;
        $rowCount = 0;

        while ($xr->read()) {
            $nt = $xr->nodeType;
            $ln = $xr->localName;

            if ($nt === \XMLReader::ELEMENT) {
                if ($ln === 'row') {
                    $curRow = [];
                    $rowCount++;
                } elseif ($ln === 'c' && $curRow !== null) {
                    $cRef   = $xr->getAttribute('r');
                    $cType  = $xr->getAttribute('t') ?? 'n';
                    $cStyle = (int) ($xr->getAttribute('s') ?? -1);
                    $inV    = false;
                } elseif ($ln === 'v' && $curRow !== null) {
                    $inV = true;
                }
            } elseif ($nt === \XMLReader::TEXT && $inV) {
                $idx = $cRef ? $this->colRefToIndex($cRef) : count($curRow);
                while (count($curRow) < $idx) $curRow[] = null;
                $raw = $xr->value;
                if ($cType === 's') {
                    $curRow[$idx] = $ss[(int) $raw] ?? '';
                } elseif ($cStyle >= 0 && ($isDate[$cStyle] ?? false) && is_numeric($raw)) {
                    $curRow[$idx] = $this->excelSerialToDate((float) $raw);
                } else {
                    $curRow[$idx] = $raw;
                }
                $inV = false;
            } elseif ($nt === \XMLReader::END_ELEMENT) {
                if ($ln === 'v') $inV = false;
                elseif ($ln === 'row' && $curRow !== null) {
                    // Only call callback for rows with at least one non-null, non-empty-string value
                    $hasData = !empty(array_filter($curRow, fn($v) => $v !== null && $v !== ''));
                    if ($hasData) {
                        $result = $callback($curRow, $rowCount);
                        if ($result === false) { $curRow = null; break; }
                    }
                    $curRow = null;
                }
            }
        }
        if ($curRow !== null) {
            $hasData = !empty(array_filter($curRow, fn($v) => $v !== null && $v !== ''));
            if ($hasData) $callback($curRow, $rowCount);
        }
        $xr->close();
    }

    /**
     * streamAllRows: used only for preview (small maxRows). Uses zip:// — no temp file for the sheet.
     */
    private function streamAllRows(string $path, string $sheetName, int $maxRows = PHP_INT_MAX): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open XLSX file.');
        }
        $sheetFile = $this->findSheetFile($zip, $sheetName);
        if (!$sheetFile) {
            $zip->close();
            throw new \RuntimeException("Sheet '{$sheetName}' not found in workbook.");
        }
        $hasStyles = $zip->statName('xl/styles.xml')        !== false;
        $hasSs     = $zip->statName('xl/sharedStrings.xml') !== false;
        $zip->close();

        $base     = 'zip://' . str_replace('\\', '/', realpath($path)) . '#';
        $sheetUrl = $base . $sheetFile;

        $isDate = $hasStyles ? $this->loadDateStyles($base . 'xl/styles.xml') : [];
        $needed = $this->collectUsedStringIndices($sheetUrl, $maxRows);
        $ss     = ($hasSs && !empty($needed))
            ? $this->loadSelectiveSharedStrings($base . 'xl/sharedStrings.xml', $needed)
            : [];

        return $this->parseSheetRows($sheetUrl, $ss, $isDate, $maxRows);
    }

    private function parseSheetRows(string $tmpFile, array $ss, array $isDate, int $maxRows): array
    {
        $xr       = new \XMLReader();
        $xr->open($tmpFile);
        $rows     = [];
        $curRow   = null;
        $cRef     = null;
        $cType    = 'n';
        $cStyle   = -1;
        $inV      = false;
        $rowCount = 0;

        while ($xr->read()) {
            $nt = $xr->nodeType;
            $ln = $xr->localName;

            if ($nt === \XMLReader::ELEMENT) {
                if ($ln === 'row') {
                    $curRow = [];
                    if (++$rowCount > $maxRows) break;
                } elseif ($ln === 'c' && $curRow !== null) {
                    $cRef   = $xr->getAttribute('r');
                    $cType  = $xr->getAttribute('t') ?? 'n';
                    $cStyle = (int) ($xr->getAttribute('s') ?? -1);
                    $inV    = false;
                } elseif ($ln === 'v' && $curRow !== null) {
                    $inV = true;
                }
            } elseif ($nt === \XMLReader::TEXT && $inV) {
                $idx = $cRef ? $this->colRefToIndex($cRef) : count($curRow);
                while (count($curRow) < $idx) $curRow[] = null;
                $raw = $xr->value;
                if ($cType === 's') {
                    $curRow[$idx] = $ss[(int) $raw] ?? '';
                } elseif ($cStyle >= 0 && ($isDate[$cStyle] ?? false) && is_numeric($raw)) {
                    $curRow[$idx] = $this->excelSerialToDate((float) $raw);
                } else {
                    $curRow[$idx] = $raw;
                }
                $inV = false;
            } elseif ($nt === \XMLReader::END_ELEMENT) {
                if ($ln === 'v') $inV = false;
                elseif ($ln === 'row' && $curRow !== null) {
                    if (!empty($curRow)) $rows[] = $curRow;
                    $curRow = null;
                }
            }
        }
        if ($curRow !== null && !empty($curRow)) $rows[] = $curRow;
        $xr->close();
        return $rows;
    }

    private function findSheetFile(\ZipArchive $zip, string $sheetName): ?string
    {
        $wbXml   = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!$wbXml || !$relsXml) return null;

        $wb   = simplexml_load_string($wbXml);
        $rels = simplexml_load_string($relsXml);

        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $target = (string) $rel['Target'];
            $relMap[(string) $rel['Id']] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
        }

        $ns  = $wb->getNamespaces(true);
        $rNs = $ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $needle = trim($sheetName);

        // Pass 1: exact match
        foreach ($wb->sheets->sheet as $s) {
            if (trim((string) $s['name']) === $needle) {
                $rId = (string) $s->attributes($rNs)['id'];
                return $relMap[$rId] ?? null;
            }
        }

        // Pass 2: case-insensitive match (handles locale differences)
        foreach ($wb->sheets->sheet as $s) {
            if (mb_strtolower(trim((string) $s['name'])) === mb_strtolower($needle)) {
                $rId = (string) $s->attributes($rNs)['id'];
                return $relMap[$rId] ?? null;
            }
        }

        // Pass 3: only fall back to first sheet when no sheet name was specified
        if ($needle === '') {
            foreach ($wb->sheets->sheet as $s) {
                $rId = (string) $s->attributes($rNs)['id'];
                if (isset($relMap[$rId])) return $relMap[$rId];
            }
        }

        return null;
    }

    private function loadDateStyles(string $tmpFile): array
    {
        $xr        = new \XMLReader();
        $xr->open($tmpFile);
        $numFmts   = [];
        $cellXfs   = [];
        $inNumFmts = false;
        $inXfs     = false;
        $xfIdx     = 0;

        while ($xr->read()) {
            $nt = $xr->nodeType;
            $ln = $xr->localName;
            if ($nt === \XMLReader::ELEMENT) {
                if ($ln === 'numFmts') {
                    $inNumFmts = true;
                } elseif ($ln === 'numFmt' && $inNumFmts) {
                    $id           = (int) $xr->getAttribute('numFmtId');
                    $numFmts[$id] = $xr->getAttribute('formatCode') ?? '';
                } elseif ($ln === 'cellXfs') {
                    $inXfs = true; $xfIdx = 0;
                } elseif ($ln === 'xf' && $inXfs) {
                    $cellXfs[$xfIdx++] = (int) ($xr->getAttribute('numFmtId') ?? 0);
                }
            } elseif ($nt === \XMLReader::END_ELEMENT) {
                if ($ln === 'numFmts') $inNumFmts = false;
                if ($ln === 'cellXfs')  $inXfs     = false;
            }
        }
        $xr->close();

        $builtinDate = range(14, 22);
        $result = [];
        foreach ($cellXfs as $idx => $fmtId) {
            if (in_array($fmtId, $builtinDate, true)) {
                $result[$idx] = true;
            } elseif (isset($numFmts[$fmtId])) {
                $result[$idx] = $this->isDateFormatCode($numFmts[$fmtId]);
            } else {
                $result[$idx] = false;
            }
        }
        return $result;
    }

    private function isDateFormatCode(string $code): bool
    {
        $code = preg_replace('/"[^"]*"/', '', $code);
        $code = preg_replace('/\[[^\]]*\]/', '', $code);
        return (bool) preg_match('/[yYmMdD]/', $code);
    }

    private function excelSerialToDate(float $serial): string
    {
        // Excel epoch is 1899-12-30 (handles Excel's false 1900 leap year)
        $dt = new \DateTime('1899-12-30');
        $dt->modify('+' . (int) $serial . ' days');
        return $dt->format('Y-m-d');
    }

    private function colRefToIndex(string $ref): int
    {
        $col = preg_replace('/[0-9]+/', '', $ref);
        $idx = 0;
        foreach (str_split(strtoupper($col)) as $c) {
            $idx = $idx * 26 + (ord($c) - 64);
        }
        return $idx - 1;
    }

    private function readRowsViaSpreadsheet(string $path, string $sheetName, int $maxRows = PHP_INT_MAX): array
    {
        $reader      = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $ws          = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();
        $rows        = $ws->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        return array_slice($rows, 0, $maxRows);
    }

    private function mapRow(array $row, array $headerRow, array $mapping): array
    {
        $result = [];
        foreach ($mapping as $excelCol => $systemField) {
            if (empty($systemField)) continue; // "— Skip —" entries have null/empty value
            $colIndex = array_search($excelCol, $headerRow);
            if ($colIndex === false) continue;
            $result[$systemField] = $this->normalizeValue($row[$colIndex] ?? null, $systemField);
        }
        return $result;
    }

    private static array $numericFields = ['year', 'sap_kpi'];

    private function normalizeValue(mixed $value, string $field): mixed
    {
        if ($value === null || $value === false) return null;

        // Trim strings and treat blank as null
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') return null;
            // "0" string in a non-numeric field = empty numeric-formatted cell
            if ($value === '0' && !in_array($field, self::$numericFields, true)) return null;
            return $value;
        }

        // Numeric 0 / 0.0 in non-numeric fields = empty cell formatted as number
        if (is_numeric($value) && (float) $value === 0.0 && !in_array($field, self::$numericFields, true)) {
            return null;
        }

        return $value;
    }

    private function splitHeaderData(array $rows): array
    {
        $headerRow = null;
        $dataRows  = [];
        foreach ($rows as $row) {
            $filtered = array_filter($row, fn($v) => $v !== null && $v !== '');
            if ($headerRow === null && !empty($filtered)) {
                $headerRow = array_values($row);
            } elseif ($headerRow !== null) {
                $dataRows[] = array_values($row);
            }
        }
        return [$headerRow ?? [], $dataRows];
    }
}
