<?php
 
namespace App\Http\Controllers\Admin;
 
use App\Http\Controllers\Controller;
use App\Models\MasterColumnConfig;
use App\Models\ColumnStatusOption;
use App\Models\Project;
use App\Services\MasterColumnService;
use Illuminate\Http\Request;
 
class ColumnConfigController extends Controller
{
    public function __construct(private MasterColumnService $service) {}
 
    /**
     * List all available columns + current pinned state for a project.
     */
    public function index(Project $project)
    {
        $available = $this->service->availableColumns();
        $pinned = MasterColumnConfig::where('project_id', $project->id)->get()
            ->keyBy(fn($c) => $c->source_table . '.' . $c->source_column);
 
        foreach ($available as $source => &$cols) {
            foreach ($cols as &$col) {
                $key = $source . '.' . $col['column'];
                $config = $pinned->get($key);
                $col['is_pinned']      = $config ? $config->is_pinned : false;
                $col['display_order']  = $config ? $config->display_order : 999;
                $col['display_label']  = $config ? $config->display_label : $col['label'];
                $col['config_id']      = $config?->id;
            }
        }
 
        return response()->json($available);
    }
 
    /**
     * Toggle a column pinned/unpinned in the master view.
     */
    public function toggle(Request $request, Project $project)
    {
        $data = $request->validate([
            'source_table'  => 'required|in:project_wells,sap_tracking,dc_tracking,computed',
            'source_column' => 'required|string',
            'is_pinned'     => 'required|boolean',
            'display_label' => 'nullable|string',
            'display_order' => 'nullable|integer',
        ]);
 
        $config = MasterColumnConfig::updateOrCreate(
            ['project_id' => $project->id, 'source_table' => $data['source_table'], 'source_column' => $data['source_column']],
            [
                'is_pinned'     => $data['is_pinned'],
                'display_label' => $data['display_label'] ?? $data['source_column'],
                'display_order' => $data['display_order'] ?? 999,
                'pinned_by'     => auth()->id(),
            ]
        );
 
        return response()->json($config);
    }
 
    /**
     * Reorder pinned columns via drag-and-drop.
     */
    public function reorder(Request $request, Project $project)
    {
        $data = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer', // config ids in new order
        ]);
 
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $project) {
            foreach ($data['order'] as $position => $configId) {
                MasterColumnConfig::where('id', $configId)
                    ->where('project_id', $project->id)
                    ->update(['display_order' => $position + 1]);
            }
        });

        return response()->json(['message' => 'Order saved.']);
    }
 
    /**
     * Get/update status options for a column.
     */
    public function statusOptions(string $sourceTable, string $sourceColumn)
    {
        $options = ColumnStatusOption::where('source_table', $sourceTable)
            ->where('source_column', $sourceColumn)
            ->orderBy('sort_order')
            ->get();
 
        return response()->json($options);
    }
 
    public function saveStatusOptions(Request $request, string $sourceTable, string $sourceColumn)
    {
        $data = $request->validate([
            'options'              => 'required|array',
            'options.*.option_value' => 'required|string',
            'options.*.option_label' => 'required|string',
            'options.*.option_color' => 'nullable|string|max:7',
            'options.*.sort_order'   => 'nullable|integer',
        ]);

        ColumnStatusOption::where('source_table', $sourceTable)
            ->where('source_column', $sourceColumn)
            ->delete();

        $now  = now();
        $rows = [];
        foreach ($data['options'] as $i => $opt) {
            $rows[] = [
                'source_table'  => $sourceTable,
                'source_column' => $sourceColumn,
                'option_value'  => $opt['option_value'],
                'option_label'  => $opt['option_label'],
                'option_color'  => $opt['option_color'] ?? '#888780',
                'sort_order'    => $opt['sort_order'] ?? $i,
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if (!empty($rows)) {
            ColumnStatusOption::insert($rows);
        }

        return response()->json(['message' => 'Status options saved.']);
    }
}