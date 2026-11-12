<?php
 
namespace App\Http\Controllers\Staff;
 
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectWell;
use App\Models\SapTracking;
use App\Services\CountdownService;
use Illuminate\Http\Request;

class SapTrackingController extends Controller
{
    public function __construct(private CountdownService $countdown) {}
 
    public function index(Project $project, Request $request)
    {
        $query = ProjectWell::where('project_id', $project->id)
            ->with(['sapTracking', 'sapTracking.updatedBy:id,name,role'])
            ->orderByRaw('well_number IS NULL, well_number ASC');
 
        if ($search = $request->input('search')) {
            $query->where(fn($q) => $q
                ->where('well_id', 'like', "%{$search}%")
                ->orWhere('well_number', 'like', "%{$search}%")
            );
        }
 
        $wells = $query->paginate(max(1, min(200, (int) $request->input('per_page', 50))));

        // The stored sap_kpi goes stale every day; send the live value instead
        $wells->getCollection()->each(function ($well) {
            $well->sapTracking?->setAttribute('sap_kpi', $this->countdown->computeSapKpi($well->sapTracking->earliest_end_date));
        });

        return response()->json($wells);
    }
 
    public function update(Request $request, ProjectWell $well)
    {
        // Only real tracking fields; ids, keys and computed values are never client-writable.
        // (collect()->except() works on keys, not values, so it never removed these before.)
        $model    = new SapTracking;
        $editable = array_diff($model->getFillable(), ['project_well_id', 'updated_by', 'sap_kpi']);
        $casts    = $model->getCasts();

        $data = $request->validate(
            collect($editable)
                ->mapWithKeys(fn ($f) => [$f => ($casts[$f] ?? null) === 'date' ? 'nullable|date' : 'nullable'])
                ->all()
        );
 
        // End date may not be before start date (compare against stored values for partial updates)
        $start = array_key_exists('earliest_start_date', $data) ? $data['earliest_start_date'] : $well->sapTracking?->earliest_start_date;
        $end   = array_key_exists('earliest_end_date', $data)   ? $data['earliest_end_date']   : $well->sapTracking?->earliest_end_date;
        $datesTouched = array_key_exists('earliest_start_date', $data) || array_key_exists('earliest_end_date', $data);
        if ($datesTouched && $msg = SapTracking::dateRangeError($start, $end)) {
            return response()->json(['message' => $msg, 'errors' => ['earliest_end_date' => [$msg]]], 422);
        }

        $sap = $well->sapTracking ?? $well->sapTracking()->create(['project_well_id' => $well->id]);

        $sap->fill(array_merge($data, ['updated_by' => auth()->id()]))->save();

        $freshSap = $sap->fresh(['updatedBy:id,name,role']);
        $well->setRelation('sapTracking', $freshSap);

        return response()->json([
            'sap_tracking' => $freshSap,
            'computed'     => $this->countdown->computeForWell($well),
        ]);
    }
 
    public function show(ProjectWell $well)
    {
        return response()->json([
            'well'         => $well->load(['sapTracking.updatedBy:id,name,role']),
            'computed'     => $this->countdown->computeForWell($well),
            'audit'        => $well->auditLogs()
                ->where('model_type', SapTracking::class)
                ->with('user:id,name,role')
                ->limit(20)->get(),
        ]);
    }
}