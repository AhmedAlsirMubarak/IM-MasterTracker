<?php

namespace App\Services\Import;

use App\Models\DcTracking;
use App\Models\ProjectWell;
use App\Models\SapTracking;
use App\Services\MasterColumnService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Checks and cleans import rows one at a time, and keeps the running tally.
 *
 * The same inspector runs for the preview (nothing is written) and for the real
 * import, so the warnings shown before importing match what the import does.
 *
 * Rules:
 *  - Rows are matched on Well ID. Well Number is only used when a row has no Well ID.
 *  - A Well ID that appears on several rows keeps every row as its own well, like the
 *    sheet (warning). The 1st, 2nd, … row with that ID matches the 1st, 2nd, … well
 *    with that ID, so importing the same sheet again updates them instead of adding more.
 *  - A Well Number used by two different Well IDs keeps both wells (warning).
 *  - A cell that can't be read (bad date, Excel error such as #N/A, bad year) is left
 *    blank and the rest of the row is still imported (warning).
 *  - Rows with no Well ID and no Well Number are skipped; trailing ones after the last
 *    real row (totals, padding) are ignored.
 */
class ImportRowInspector
{
    private const MAX_MESSAGES = 300;

    /** match key => list of ProjectWell, oldest first (existing wells, plus wells created during this run) */
    public array $byWellId = [];

    /** match key => how many rows with this Well ID have been read so far */
    private array $idOccurrences = [];

    /** Which occurrence of its Well ID the last inspected row was (0 = first). */
    private int $lastOccurrence = 0;

    /** well-number key => ProjectWell, for rows that only have a Well Number */
    public array $byWellNumber = [];

    /** Well IDs seen in the file: key => first row number */
    private array $seenIds = [];

    /** well-number key => ['key' => well-id key, 'id' => display id, 'row' => ?int] */
    private array $numberOwners = [];

    private array $warnedPairs   = [];
    private array $pendingNoId   = [];
    private array $existingCount = [];
    private array $touchedWells  = ['new' => [], 'existing' => []];

    /** True when the last inspected row repeated a Well ID seen earlier in the file. */
    public bool $lastWasRepeat = false;

    public array $warnings = [];
    public array $errors   = [];
    public int $warningCount = 0;
    public int $errorCount   = 0;

    public int $rowsRead    = 0;
    public int $rowsApplied = 0;
    public int $rowsSkipped = 0;
    public int $rowsMerged  = 0; // kept for older sessions; repeats are no longer merged
    public int $rowsDuplicate = 0;

    private array $labels;
    private array $dateFields;

    /**
     * @param iterable<ProjectWell>|null $existingWells the project's wells, oldest first;
     *        loaded from the database when not given
     */
    public function __construct(int $projectId, ?iterable $existingWells = null)
    {
        $this->labels     = self::fieldLabels();
        $this->dateFields = self::dateFields();

        $existingWells ??= ProjectWell::withTrashed()->where('project_id', $projectId)->orderBy('id')->get();

        foreach ($existingWells as $well) {
            $key = self::key($well->well_id);
            $this->byWellId[$key][] = $well;
            $this->existingCount[$key] = ($this->existingCount[$key] ?? 0) + 1;

            if (!blank($well->well_number)) {
                $nKey = self::key($well->well_number);
                $this->byWellNumber[$nKey] ??= $well;
                $this->numberOwners[$nKey] ??= ['key' => $key, 'id' => $well->well_id, 'row' => null];
            }
        }
    }

    /**
     * Same comparison MySQL uses for well ids (case-insensitive, trailing spaces ignored).
     */
    public static function key(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * Clean one mapped row. Returns the data to save, or null when the row is skipped.
     */
    public function inspect(array $data, int $rowNum): ?array
    {
        $id  = $data['well_id']     ?? null;
        $num = $data['well_number'] ?? null;

        if (blank($id) && blank($num)) {
            // Decide later: skipped if real rows follow, ignored if it's trailing padding
            $this->pendingNoId[] = $rowNum;
            return null;
        }

        $this->flushNoId();
        $this->rowsRead++;
        $this->lastWasRepeat  = false;
        $this->lastOccurrence = 0;

        $label = $this->rowLabel($rowNum, $id ?? $num);
        $data  = $this->cleanValues($data, $label);

        if (!blank($id)) {
            $key = self::key($id);

            $occurrence = $this->idOccurrences[$key] ?? 0;
            $this->idOccurrences[$key] = $occurrence + 1;
            $this->lastOccurrence = $occurrence;

            if ($occurrence > 0) {
                $this->rowsDuplicate++;
                $this->lastWasRepeat = true;
                $this->warn("{$label}: this Well ID is also on row {$this->seenIds[$key]}. Kept as a separate well, as in the sheet, and marked as a duplicate ID — check which row is correct.");
            } else {
                $this->seenIds[$key] = $rowNum;
            }

            // Each row is its own well: the n-th row with this ID is the n-th well with it
            $isExisting = $occurrence < ($this->existingCount[$key] ?? 0);
            $this->touchedWells[$isExisting ? 'existing' : 'new'][$key . '#' . $occurrence] = true;

            if (!blank($num)) {
                $nKey  = self::key($num);
                $owner = $this->numberOwners[$nKey] ?? null;

                if ($owner === null) {
                    $this->numberOwners[$nKey] = ['key' => $key, 'id' => $id, 'row' => $rowNum];
                } elseif ($owner['key'] !== $key && !isset($this->warnedPairs[$nKey . '|' . $key])) {
                    $this->warnedPairs[$nKey . '|' . $key] = true;
                    $where = $owner['row'] ? "row {$owner['row']}" : 'a well already in this project';
                    $this->warn("{$label}: Well Number {$num} is also used by Well ID {$owner['id']} ({$where}). Both wells are kept — check which one should have this number.");
                }
            }
        }

        return $data;
    }

    /**
     * Find the well the last inspected row belongs to (null = new well).
     * A repeated Well ID matches the well in the same position (2nd row → 2nd well).
     */
    public function findWell(array $data): ?ProjectWell
    {
        if (!blank($data['well_id'] ?? null)) {
            return $this->byWellId[self::key($data['well_id'])][$this->lastOccurrence] ?? null;
        }
        return $this->byWellNumber[self::key($data['well_number'])] ?? null;
    }

    /** Remember a newly created well so the next row with its ID lines up after it. */
    public function remember(ProjectWell $well): void
    {
        $this->byWellId[self::key($well->well_id)][] = $well;
        if (!blank($well->well_number)) {
            $this->byWellNumber[self::key($well->well_number)] ??= $well;
        }
    }

    public function applied(): void
    {
        $this->rowsApplied++;
    }

    /** A row that passed inspection but could not be saved. */
    public function failed(int $rowNum, array $data, \Throwable $e): void
    {
        $this->rowsSkipped++;
        $this->error($this->rowLabel($rowNum, $data['well_id'] ?? $data['well_number'] ?? null)
            . ': not imported — ' . $this->describe($e) . '.');

        Log::warning('Import row failed', ['row' => $rowNum, 'exception' => $e->getMessage()]);
    }

    /** Call once all rows have been read. Trailing rows without an identifier are ignored. */
    public function finish(): void
    {
        $this->pendingNoId = [];
    }

    /** Identifier-less rows waiting since the last real row. */
    public function pendingNoIdCount(): int { return count($this->pendingNoId); }

    public function createdCount(): int  { return count($this->touchedWells['new']); }
    public function updatedCount(): int  { return count($this->touchedWells['existing']); }

    public function summary(): array
    {
        return [
            'rows'          => $this->totalRows(),
            'new_wells'     => $this->createdCount(),
            'existing'      => $this->updatedCount(),
            'merged_rows'   => $this->rowsMerged,
            'duplicate_rows'=> $this->rowsDuplicate,
            'skipped_rows'  => $this->rowsSkipped,
            'warning_count' => $this->warningCount,
            'error_count'   => $this->errorCount,
            'warnings'      => $this->warnings,
            'errors'        => $this->errors,
        ];
    }

    /** Messages joined for storage, with a note when the list was cut short. */
    public static function joinMessages(array $messages, int $total): ?string
    {
        if (!$messages) return null;
        $text = implode("\n", $messages);
        if ($total > count($messages)) {
            $text .= "\n…and " . ($total - count($messages)) . ' more.';
        }
        return $text;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /** Data rows read: rows with an identifier plus skipped rows without one. */
    public function totalRows(): int
    {
        return $this->rowsRead + $this->noIdSkipped;
    }

    private int $noIdSkipped = 0;

    private function flushNoId(): void
    {
        foreach ($this->pendingNoId as $row) {
            $this->rowsSkipped++;
            $this->noIdSkipped++;
            $this->error("Row {$row}: no Well ID or Well Number — row skipped.");
        }
        $this->pendingNoId = [];
    }

    private function cleanValues(array $data, string $label): array
    {
        foreach ($data as $field => $value) {
            if ($value === null) continue;
            $name = $this->labels[$field] ?? $field;

            if (is_string($value) && preg_match('/^#(N\/A|REF!|VALUE!|DIV\/0!|NAME\?|NULL!|NUM!|SPILL!|CALC!|GETTING_DATA)$/i', trim($value))) {
                $data[$field] = null;
                $this->warn("{$label}: {$name} contains the Excel error \"{$value}\" — left blank.");
                continue;
            }

            if (isset($this->dateFields[$field])) {
                $date = self::parseDate($value);
                if ($date === null) {
                    $this->warn("{$label}: {$name} \"{$value}\" is not a valid date — left blank.");
                }
                $data[$field] = $date;
                continue;
            }

            if ($field === 'year') {
                $year = is_numeric($value) ? (int) $value : null;
                if ($year === null || $year < 1900 || $year > 2100 || (float) $value != $year) {
                    $this->warn("{$label}: Year \"{$value}\" is not a valid year — left blank.");
                    $year = null;
                }
                $data[$field] = $year;
            }
        }

        return $data;
    }

    /**
     * Accepts Excel serial numbers and common written forms (day first, as used
     * in the tracker sheets). Returns Y-m-d, or null when the value isn't one clear date.
     */
    public static function parseDate(mixed $value): ?string
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $serial = (float) $value;
            if ($serial < 1 || $serial > 2958465) return null;
            $d = (new \DateTime('1899-12-30'))->modify('+' . (int) $serial . ' days');
            return self::sane($d);
        }

        $s = trim((string) $value);
        // Drop a midnight/any time part: "2026-10-21 00:00:00", "21/10/2026 08:30"
        $s = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2}(\.\d+)?)?\s*(AM|PM)?$/i', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        $formats = [
            'Y-m-d', 'Y/m/d', 'Y.m.d',
            'd/m/Y', 'd-m-Y', 'd.m.Y',
            'd/m/y', 'd-m-y', 'd.m.y',
            'd-M-Y', 'd-M-y', 'd M Y', 'd M y', 'd-F-Y', 'd F Y',
            'M d, Y', 'F d, Y', 'M d Y', 'F d Y',
        ];

        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat('!' . $format, $s);
            if (!$d) continue;
            $err = \DateTime::getLastErrors();
            if ($err && ($err['warning_count'] || $err['error_count'])) continue;
            if (($ok = self::sane($d)) !== null) return $ok;
        }

        return null;
    }

    private static function sane(\DateTimeInterface $d): ?string
    {
        $year = (int) $d->format('Y');
        return ($year >= 1950 && $year <= 2100) ? $d->format('Y-m-d') : null;
    }

    private function describe(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $code = $e->errorInfo[1] ?? null;
            preg_match("/column '([^']+)'/", $e->getMessage(), $m);
            $name = isset($m[1]) ? ($this->labels[$m[1]] ?? $m[1]) : 'a column';

            return match ($code) {
                1406    => "the value for {$name} is too long",
                1366,
                1292    => "the value for {$name} is not valid",
                1048    => "{$name} cannot be empty",
                1062    => 'this well already exists in the project',
                default => 'the database rejected the row',
            };
        }

        return 'an unexpected problem occurred while saving it';
    }

    private function rowLabel(int $rowNum, mixed $id): string
    {
        return blank($id) ? "Row {$rowNum}" : "Row {$rowNum} (Well {$id})";
    }

    private function warn(string $message): void
    {
        $this->warningCount++;
        if (count($this->warnings) < self::MAX_MESSAGES) $this->warnings[] = $message;
    }

    private function error(string $message): void
    {
        $this->errorCount++;
        if (count($this->errors) < self::MAX_MESSAGES) $this->errors[] = $message;
    }

    /** column => label, across wells / SAP / DC */
    public static function fieldLabels(): array
    {
        $labels = [];
        foreach (app(MasterColumnService::class)->availableColumns() as $cols) {
            foreach ($cols as $c) $labels[$c['column']] ??= $c['label'];
        }
        return $labels;
    }

    /** Fields stored as dates in any of the three tables */
    private static function dateFields(): array
    {
        $fields = [];
        foreach ([new ProjectWell, new SapTracking, new DcTracking] as $model) {
            foreach ($model->getCasts() as $field => $cast) {
                if (in_array($cast, ['date', 'datetime', 'immutable_date', 'immutable_datetime'], true)) {
                    $fields[$field] = true;
                }
            }
        }
        return $fields;
    }
}
