<?php
 
namespace App\Services;
 
use App\Models\ProjectWell;
use Carbon\Carbon;
 
class CountdownService
{
    /**
     * SAP KPI: remaining days from today until EarliestEndDate.
     * Positive = days still remaining. Negative = past due.
     */
    public function computeSapKpi(?Carbon $endDate): ?int
    {
        if (!$endDate) return null;
        return (int) now()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false);
    }

    public function sapKpiStatus(?int $kpi): string
    {
        if ($kpi === null) return 'none';
        if ($kpi <= 3)  return 'red';   // 3 days or less (including past due)
        if ($kpi <= 10) return 'amber'; // 4–10 days left
        return 'green';                 // more than 10 days remaining
    }
 
    /**
     * Countdown: days elapsed since Commissioning Date.
     * Counting stops (closed) when E-Pad is closed.
     * Red   = over 90 days elapsed
     * Amber = 81–90 days elapsed (10 days left)
     * Green = 80 days or fewer elapsed
     */
    public function countdownDays(?Carbon $commissioningDate): ?int
    {
        if (!$commissioningDate) return null;
        return (int) $commissioningDate->diffInDays(now());
    }

    public function countdownStatus(?int $days, ?string $ePadCloseout): string
    {
        if ($days === null) return 'none';
        $isClosed = strtolower(trim($ePadCloseout ?? '')) === 'closed';
        if ($isClosed) return 'closed';   // stopped — E-Pad closed
        if ($days > 90) return 'overdue'; // RED  — over 90 days
        if ($days > 80) return 'warning'; // AMBER — 10 days left
        return 'ok';                      // GREEN
    }
 
    /**
     * Returns full computed summary for a well.
     */
    public function computeForWell(ProjectWell $well): array
    {
        $sap = $well->sapTracking;
 
        $kpi     = $this->computeSapKpi($sap?->earliest_end_date);
        $cdDays  = $this->countdownDays($sap?->commissioning_date);
        $cdStatus = $this->countdownStatus($cdDays, $well->e_pad_closeout);
 
        return [
            'sap_kpi'            => $kpi,
            'sap_kpi_status'     => $this->sapKpiStatus($kpi),
            'countdown_days'     => $cdDays,
            'countdown_status'   => $cdStatus,
        ];
    }
}