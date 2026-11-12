<?php

use App\Services\CountdownService;
use Carbon\Carbon;

// Values below are real rows from "ALL scope Detailed Traking sheet.xlsx" (ODC-C3100000180),
// checked against the sheet's own formulas on 12 Nov 2026.

beforeEach(function () {
    config(['app.business_timezone' => 'Asia/Dubai']);
    Carbon::setTestNow(Carbon::parse('2026-11-12 10:00', 'Asia/Dubai'));
    $this->svc = new CountdownService();
});

afterEach(fn () => Carbon::setTestNow());

it('calculates SAP KPI as End − Start, like the sheet', function () {
    // Well 36174: 5 Jan → 22 Jan = 17
    expect($this->svc->computeSapKpi(Carbon::parse('2026-01-05'), Carbon::parse('2026-01-22')))->toBe(17);
    // Well 36028: 9 Jan → 24 Jan = 15
    expect($this->svc->computeSapKpi(Carbon::parse('2026-01-09'), Carbon::parse('2026-01-24')))->toBe(15);
    // Well 30091: 9 Jan → 29 Jan = 20
    expect($this->svc->computeSapKpi(Carbon::parse('2026-01-09'), Carbon::parse('2026-01-29')))->toBe(20);
});

it('leaves SAP KPI empty until both dates are filled', function () {
    expect($this->svc->computeSapKpi(null, Carbon::parse('2026-01-22')))->toBeNull();
    expect($this->svc->computeSapKpi(Carbon::parse('2026-01-05'), null))->toBeNull();
});

it('does not change SAP KPI from day to day', function () {
    $start = Carbon::parse('2026-01-05');
    $end   = Carbon::parse('2026-01-22');
    $today = $this->svc->computeSapKpi($start, $end);

    Carbon::setTestNow(Carbon::parse('2027-03-01'));

    expect($this->svc->computeSapKpi($start, $end))->toBe($today);
});

it('calculates the countdown as (Commissioning + 90) − today, like the sheet', function () {
    // Well 36174: commissioned 19 Feb → target 20 May → −176 on 12 Nov
    expect($this->svc->countdownDays(Carbon::parse('2026-02-19')))->toBe(-176);
    // Commissioned 1 Oct → target 30 Dec → 48 days left
    expect($this->svc->countdownDays(Carbon::parse('2026-10-01')))->toBe(48);
    expect($this->svc->countdownDays(null))->toBeNull();
});

it('uses the Dubai date for today, not the server UTC date', function () {
    // 01:30 in Dubai on 13 Nov is still 12 Nov in UTC
    Carbon::setTestNow(Carbon::parse('2026-11-13 01:30', 'Asia/Dubai'));

    expect($this->svc->countdownDays(Carbon::parse('2026-02-19')))->toBe(-177);
});

it('colours the countdown red only when negative', function () {
    expect($this->svc->countdownStatus(-1, null))->toBe('overdue');
    expect($this->svc->countdownStatus(0, null))->toBe('warning');
    expect($this->svc->countdownStatus(10, null))->toBe('warning');
    expect($this->svc->countdownStatus(11, null))->toBe('ok');
    expect($this->svc->countdownStatus(-50, ' Closed '))->toBe('closed');
    expect($this->svc->countdownStatus(null, null))->toBe('none');
});

it('colours the SAP KPI by duration', function () {
    expect($this->svc->sapKpiStatus(17))->toBe('green');
    expect($this->svc->sapKpiStatus(45))->toBe('amber');
    expect($this->svc->sapKpiStatus(61))->toBe('red');
    expect($this->svc->sapKpiStatus(null))->toBe('none');
});

it('calculates the Actual Countdown as (JCC Actual Date + 90) − today, like the sheet', function () {
    // Well 36174: JCC approved 2 Jul → target 30 Sep → −43 on 12 Nov
    expect($this->svc->actualCountdownDays(Carbon::parse('2026-07-02')))->toBe(-43);
    // Well 30091: JCC approved 17 Jun → −58
    expect($this->svc->actualCountdownDays(Carbon::parse('2026-06-17')))->toBe(-58);
    // Well 36028: JCC approved 5 Aug → −9
    expect($this->svc->actualCountdownDays(Carbon::parse('2026-08-05')))->toBe(-9);
    expect($this->svc->actualCountdownDays(null))->toBeNull();
});

it('returns both countdowns for a well, each with its own status', function () {
    $well = new App\Models\ProjectWell(['e_pad_closeout' => null]);
    $well->setRelation('sapTracking', new App\Models\SapTracking([
        'earliest_start_date' => '2026-01-05',
        'earliest_end_date'   => '2026-01-22',
        'commissioning_date'  => '2026-02-19',
        'jcc_actual_date'     => '2026-10-01',
    ]));

    expect($this->svc->computeForWell($well))->toMatchArray([
        'sap_kpi'                 => 17,
        'countdown_days'          => -176,
        'countdown_status'        => 'overdue',
        'actual_countdown_days'   => 48,
        'actual_countdown_status' => 'ok',
    ]);
});
