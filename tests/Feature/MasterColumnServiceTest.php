<?php

use App\Services\MasterColumnService;

test('sap tracking available columns include station code', function () {
    $service = new MasterColumnService();

    $columns = $service->availableColumns()['sap_tracking'];

    expect($columns)->toContain([
        'column' => 'station_code',
        'label' => 'Station Code',
        'type' => 'select',
        'locked' => false,
    ]);
});

test('jcc actual date and actual countdown are available and pinned by default', function () {
    $available = (new MasterColumnService())->availableColumns();

    expect(array_column($available['sap_tracking'], 'column'))->toContain('jcc_actual_date');
    expect(array_column($available['computed'], 'column'))->toContain('actual_countdown_days', 'actual_countdown_status');
});
