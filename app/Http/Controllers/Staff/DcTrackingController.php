<?php
 
namespace App\Http\Controllers\Staff;
 
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectWell;
use App\Models\DcTracking;
use Illuminate\Http\Request;

class DcTrackingController extends Controller
{
    public function __construct() {}
 
    public function index(Project $project, Request $request)
    {
        $query = ProjectWell::where('project_id', $project->id)
            ->with(['dcTracking', 'dcTracking.updatedBy:id,name,role'])
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
        $fillable = (new DcTracking)->getFillable();
        $data = $request->validate(
            collect($fillable)->except(['project_well_id', 'updated_by'])
                ->mapWithKeys(fn($f) => [$f => 'nullable'])
                ->all()
        );
 
        $dc = $well->dcTracking ?? $well->dcTracking()->create(['project_well_id' => $well->id]);
        $dc->fill(array_merge($data, ['updated_by' => auth()->id()]))->save();
 
        return response()->json($dc->fresh(['updatedBy:id,name,role']));
    }
}