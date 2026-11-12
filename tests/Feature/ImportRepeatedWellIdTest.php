<?php

use App\Models\ProjectWell;
use App\Services\Import\ImportRowInspector;

// Mirrors the tracking sheet: Well ID 32003 is on rows 53 and 149 with different data.
// Runs without a database: wells are kept in memory the way ImportService saves them.

/** Run rows through the inspector the way ImportService does. Returns [inspector, wells]. */
function importRows(array $rows, array $existing = []): array
{
    $inspector = new ImportRowInspector(1, $existing);
    $wells     = $existing;

    foreach ($rows as $rowNum => $data) {
        $data = $inspector->inspect($data, $rowNum);
        if ($data === null) continue;

        $well = $inspector->findWell($data);
        if ($well) {
            $well->fill($data);
        } else {
            $well = new ProjectWell($data);
            $wells[] = $well;
            $inspector->remember($well);
        }
        $inspector->applied();
    }

    $inspector->finish();

    return [$inspector, $wells];
}

$rows = [
    53  => ['well_id' => '32003', 'well_number' => 'L-1206', 'responsible_asset' => 'MEC'],
    149 => ['well_id' => '32003', 'well_number' => 'L-1206', 'responsible_asset' => 'HDE'],
    150 => ['well_id' => '30061', 'well_number' => 'L-1202', 'responsible_asset' => 'MEC'],
];

it('keeps a repeated Well ID as a separate well, like the sheet', function () use ($rows) {
    [$inspector, $wells] = importRows($rows);

    $repeated = array_values(array_filter($wells, fn ($w) => $w->well_id === '32003'));

    expect($wells)->toHaveCount(3);
    expect(array_map(fn ($w) => $w->responsible_asset, $repeated))->toBe(['MEC', 'HDE']);
    expect($inspector->summary())->toMatchArray(['new_wells' => 3, 'existing' => 0, 'duplicate_rows' => 1, 'merged_rows' => 0]);
    expect($inspector->warnings[0])->toContain('row 53')->toContain('separate well');
});

it('updates the same wells when the sheet is imported again', function () use ($rows) {
    [, $wells] = importRows($rows);

    $changed = $rows;
    $changed[149]['responsible_asset'] = 'PDO';
    [$inspector, $after] = importRows($changed, $wells);

    $repeated = array_values(array_filter($after, fn ($w) => $w->well_id === '32003'));

    expect($after)->toHaveCount(3);
    expect(array_map(fn ($w) => $w->responsible_asset, $repeated))->toBe(['MEC', 'PDO']);
    expect($inspector->summary())->toMatchArray(['new_wells' => 0, 'existing' => 3]);
});
