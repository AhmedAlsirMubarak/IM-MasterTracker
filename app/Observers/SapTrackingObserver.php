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
        // Recompute SAP KPI before saving: End − Start, same as the tracking sheet
        $model->sap_kpi = $this->countdown->computeSapKpi($model->earliest_start_date, $model->earliest_end_date);
    }
 
    public function updated(SapTracking $model): void
    {
        $dirty    = $model->getDirty();
        $original = $model->getOriginal();
 
        $this->audit->logChanges($model, $original, $dirty, $model->project_well_id);
    }
 
    public function created(SapTracking $model): void
    {
        // SAP KPI = End − Start, same as the tracking sheet
        $kpi = $this->countdown->computeSapKpi($model->earliest_start_date, $model->earliest_end_date);
        if ($kpi !== null) {
            $model->updateQuietly(['sap_kpi' => $kpi]);
        }
    }
}