<?php
 
namespace App\Observers;
 
use App\Models\SapTracking;
use App\Services\AuditService;
use App\Services\CountdownService;
 
class SapTrackingObserver
{
    public function __construct(
        private AuditService $audit,
        private CountdownService $countdown
    ) {}
 
    public function updating(SapTracking $model): void
    {
        // Recompute SAP KPI before saving
        // KPI = days remaining until EarliestEndDate (the start date is not an input)
        $kpi = $this->countdown->computeSapKpi($model->earliest_end_date);
        $model->sap_kpi = $kpi;
    }
 
    public function updated(SapTracking $model): void
    {
        $dirty    = $model->getDirty();
        $original = $model->getOriginal();
 
        $this->audit->logChanges($model, $original, $dirty, $model->project_well_id);
    }
 
    public function created(SapTracking $model): void
    {
        // KPI = days remaining until EarliestEndDate (the start date is not an input)
        $kpi = $this->countdown->computeSapKpi($model->earliest_end_date);
        if ($kpi !== null) {
            $model->updateQuietly(['sap_kpi' => $kpi]);
        }
    }
}