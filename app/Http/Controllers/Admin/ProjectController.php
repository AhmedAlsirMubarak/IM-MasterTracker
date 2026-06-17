<?php
 
namespace App\Http\Controllers\Admin;
 
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\MasterColumnService;
use Illuminate\Http\Request;
 
class ProjectController extends Controller
{
    public function __construct(private MasterColumnService $columnService) {}
 
    public function index()
    {
        $projects = Project::withCount('wells')
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->get();
 
        return response()->json($projects);
    }
 
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'contract_no' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status'      => 'in:active,archived,closed',
        ]);
 
        $project = Project::create(array_merge($data, ['created_by' => auth()->id()]));
 
        // Seed default pinned columns
        $this->columnService->seedDefaults($project->id);
 
        return response()->json($project->load('createdBy:id,name'), 201);
    }
 
    public function show(Project $project)
    {
        return response()->json($project->load(['createdBy:id,name', 'columnConfigs'])->loadCount('wells'));
    }
 
    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'contract_no' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status'      => 'in:active,archived,closed',
        ]);
 
        $project->update($data);
        return response()->json($project);
    }
 
    public function destroy(Project $project)
    {
        // Soft-delete all associated wells first (cascade doesn't fire on soft-delete)
        $project->wells()->delete();
        $project->delete();
        return response()->json(['message' => 'Project deleted.']);
    }
}