<?php
 
namespace App\Services;
 
use App\Models\ProjectWell;
use Carbon\Carbon;
 
class CountdownService
{
    /**
     * SAP KPI: difference in days between EarliestStartDate and EarliestEndDate.
     */
    public function computeSapKpi(?Carbon $startDate, ?Carbon $endDate): ?int
    {
        if (!$startDate || !$endDate) return null;
        return $startDate->diffInDays($endDate);
    }
 
    public function sapKpiStatus(?int $kpi): string
    {
        if ($kpi === null) return 'none';
        if ($kpi <= 30) return 'green';
        if ($kpi <= 60) return 'amber';
        return 'red';
    }
 
    /**
     * Countdown: days elapsed since Hook up & Tie in Completed.
     * Red  = over 90 days AND E-Pad not closed
     * Amber = 75–90 days
     * Green = under 75 days OR E-Pad is closed
     */
    public function countdownDays(?Carbon $tieInDate): ?int
    {
        if (!$tieInDate) return null;
        return (int) $tieInDate->diffInDays(now());
    }
 
    public function countdownStatus(?int $days, ?string $ePadCloseout): string
    {
        if ($days === null) return 'none';
        $isClosed = strtolower(trim($ePadCloseout ?? '')) === 'closed';
        if ($isClosed) return 'closed';   // GREEN even if over 90
        if ($days > 90) return 'overdue'; // RED
        if ($days > 75) return 'warning'; // AMBER
        return 'ok';                       // GREEN
    }
 
    /**
     * Returns full computed summary for a well.
     */
    public function computeForWell(ProjectWell $well): array
    {
        $sap = $well->sapTracking;
 
        $kpi     = $this->computeSapKpi($sap?->earliest_start_date, $sap?->earliest_end_date);
        $cdDays  = $this->countdownDays($sap?->hook_up_tie_in_completed);
        $cdStatus = $this->countdownStatus($cdDays, $well->e_pad_closeout);
 
        return [
            'sap_kpi'            => $kpi,
            'sap_kpi_status'     => $this->sapKpiStatus($kpi),
            'countdown_days'     => $cdDays,
            'countdown_status'   => $cdStatus,
        ];
    }
}