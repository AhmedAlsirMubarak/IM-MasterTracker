<?php
 
namespace App\Observers;
 
use App\Models\DcTracking;
use App\Services\AuditService;
 
class DcTrackingObserver
{
    public function __construct(private AuditService $audit) {}
 
    public function updated(DcTracking $model): void
    {
        $this->audit->logChanges($model, $model->getOriginal(), $model->getDirty(), $model->project_well_id);
    }
}