<?php

namespace App\Services;

use App\Models\ProjectWell;
use Carbon\Carbon;

/**
 * SAP KPI and the 90-day countdown, calculated exactly like the
 * "ALL scope Detailed Tracking" sheet:
 *
 *   SAP KPI   = EarliestEndDate − EarliestStartDate
 *   Countdown = (Commissioning date + 90) − TODAY()
 */
class CountdownService
{
    public const TARGET_DAYS = 90;

    /** Countdown turns amber when this many days (or fewer) are left. */
    public const WARNING_DAYS = 10;

    /**
     * "Today" in the business timezone, so the countdown does not jump a day
     * early or late around midnight while the app itself runs on UTC.
     */
    public function today(): Carbon
    {
        return Carbon::now(config('app.business_timezone', 'Asia/Dubai'))->startOfDay();
    }

    /**
     * SAP KPI: SAP work duration in days (End − Start), same as the sheet.
     * Null until both dates are filled in.
     */
    public function computeSapKpi(?Carbon $startDate, ?Carbon $endDate): ?int
    {
        if (!$startDate || !$endDate) return null;

        return $this->daysBetween($startDate, $endDate);
    }

    /**
     * Badge colour for the SAP KPI duration (the sheet does not colour this column).
     */
    public function sapKpiStatus(?int $kpi): string
    {
        if ($kpi === null) return 'none';
        if ($kpi <= 30) return 'green';  // done within a month
        if ($kpi <= 60) return 'amber';  // 31–60 days
        return 'red';                    // over 60 days
    }

    /**
     * Countdown: days left until Commissioning date + 90 days, same as the sheet.
     * Positive = days still left. Negative = days overdue.
     */
    public function countdownDays(?Carbon $commissioningDate): ?int
    {
        if (!$commissioningDate) return null;

        $target = $commissioningDate->copy()->addDays(self::TARGET_DAYS);

        return $this->daysBetween($this->today(), $target);
    }

    /**
     * Red when negative (as the sheet colours it), amber in the last 10 days,
     * green otherwise. Counting stops (closed) once the E-Pad is closed.
     */
    public function countdownStatus(?int $days, ?string $ePadCloseout): string
    {
        if ($days === null) return 'none';
        $isClosed = strtolower(trim($ePadCloseout ?? '')) === 'closed';
        if ($isClosed) return 'closed';                    // stopped — E-Pad closed
        if ($days < 0) return 'overdue';                   // RED   — past the 90-day target
        if ($days <= self::WARNING_DAYS) return 'warning'; // AMBER — 10 days or fewer left
        return 'ok';                                       // GREEN
    }

    /**
     * Returns full computed summary for a well.
     */
    public function computeForWell(ProjectWell $well): array
    {
        $sap = $well->sapTracking;

        $kpi      = $this->computeSapKpi($sap?->earliest_start_date, $sap?->earliest_end_date);
        $cdDays   = $this->countdownDays($sap?->commissioning_date);
        $cdStatus = $this->countdownStatus($cdDays, $well->e_pad_closeout);

        return [
            'sap_kpi'            => $kpi,
            'sap_kpi_status'     => $this->sapKpiStatus($kpi),
            'countdown_days'     => $cdDays,
            'countdown_status'   => $cdStatus,
        ];
    }

    /**
     * Whole calendar days from $from to $to (negative when $to is earlier),
     * ignoring time of day and timezone, like subtracting two dates in Excel.
     */
    private function daysBetween(Carbon $from, Carbon $to): int
    {
        $a = Carbon::createFromDate($from->year, $from->month, $from->day, 'UTC')->startOfDay();
        $b = Carbon::createFromDate($to->year, $to->month, $to->day, 'UTC')->startOfDay();

        return (int) round($a->diffInDays($b, false));
    }
}
