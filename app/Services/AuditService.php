<?php
 
namespace App\Services;
 
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
 
class AuditService
{
    public function logChanges(Model $model, array $original, array $dirty, ?int $wellId = null): void
    {
        $userId = auth()->id();
        if (!$userId) return;

        $now       = now();
        $ip        = request()->ip();
        $ua        = request()->userAgent();
        $modelType = get_class($model);
        $modelId   = $model->getKey();
        $wellId    = $wellId ?? ($model->project_well_id ?? $model->id ?? null);

        $rows = [];
        foreach ($dirty as $field => $newValue) {
            if (!array_key_exists($field, $original)) continue;
            $oldValue = $original[$field];
            if ($oldValue === $newValue) continue;

            $rows[] = [
                'user_id'         => $userId,
                'model_type'      => $modelType,
                'model_id'        => $modelId,
                'project_well_id' => $wellId,
                'field_name'      => $field,
                'old_value'       => $oldValue,
                'new_value'       => $newValue,
                'ip_address'      => $ip,
                'user_agent'      => $ua,
                'created_at'      => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('audit_logs')->insert($rows);
        }
    }
 
    public function log(string $modelType, int $modelId, string $field, $old, $new, ?int $wellId = null): void
    {
        AuditLog::create([
            'user_id'         => auth()->id(),
            'model_type'      => $modelType,
            'model_id'        => $modelId,
            'project_well_id' => $wellId,
            'field_name'      => $field,
            'old_value'       => $old,
            'new_value'       => $new,
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
            'created_at'      => now(),
        ]);
    }
}