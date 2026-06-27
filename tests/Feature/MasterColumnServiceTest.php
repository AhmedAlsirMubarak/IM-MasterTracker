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
