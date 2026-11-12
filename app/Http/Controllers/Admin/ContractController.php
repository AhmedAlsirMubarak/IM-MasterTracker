<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Project;
use App\Services\MasterColumnService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function __construct(private MasterColumnService $columnService) {}

    public function index()
    {
        return response()->json(
            Contract::withCount('projects')
                ->with('createdBy:id,name')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'contract_no' => ['nullable', 'string', 'max:100', $this->uniqueContractNo()],
            'description' => 'nullable|string',
            'status'      => 'in:active,archived,closed',
        ], $this->messages());

        $contract = Contract::create(array_merge(['status' => 'active'], $data, ['created_by' => auth()->id()]));

        return response()->json($contract->fresh()->load('createdBy:id,name')->loadCount('projects'), 201);
    }

    public function update(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'contract_no' => ['nullable', 'string', 'max:100', $this->uniqueContractNo($contract->id)],
            'description' => 'nullable|string',
            'status'      => 'in:active,archived,closed',
        ], $this->messages());

        $contract->update($data);
        return response()->json($contract);
    }

    public function destroy(Contract $contract)
    {
        // Cascade: delete all projects and their wells
        foreach ($contract->projects as $project) {
            $project->wells()->delete();
            $project->delete();
        }
        $contract->delete();
        return response()->json(['message' => 'Contract deleted.']);
    }

    // List projects within a contract
    public function projects(Contract $contract)
    {
        return response()->json(
            $contract->projects()
                ->withCount('wells')
                ->with('createdBy:id,name')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    // Create a project within a contract
    public function storeProject(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'in:active,archived,closed',
        ]);

        $project = Project::create(array_merge(['status' => 'active'], $data, [
            'contract_id' => $contract->id,
            'created_by'  => auth()->id(),
        ]));

        $this->columnService->seedDefaults($project->id);

        return response()->json($project->load('createdBy:id,name')->loadCount('wells'), 201);
    }

    /** contract_no must be unique among contracts that have not been deleted. */
    private function uniqueContractNo(?int $ignoreId = null)
    {
        return Rule::unique('contracts', 'contract_no')
            ->whereNull('deleted_at')
            ->ignore($ignoreId);
    }

    private function messages(): array
    {
        return ['contract_no.unique' => 'A contract with this number already exists.'];
    }
}
