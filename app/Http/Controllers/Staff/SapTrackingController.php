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
 
        return response()->json($query->paginate($request->input('per_page', 50)));
    }
 
    public function update(Request $request, ProjectWell $well)
    {
        $fillable = (new SapTracking)->getFillable();
        $data = $request->validate(
            collect($fillable)->except(['project_well_id', 'updated_by', 'sap_kpi'])
                ->mapWithKeys(fn($f) => [$f => 'nullable'])
                ->all()
        );
 
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