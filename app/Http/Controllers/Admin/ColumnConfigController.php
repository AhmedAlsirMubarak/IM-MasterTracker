<?php
 
namespace App\Http\Controllers\Admin;
 
use App\Http\Controllers\Controller;
use App\Models\MasterColumnConfig;
use App\Models\ColumnStatusOption;
use App\Models\CustomCellValue;
use App\Models\Project;
use App\Services\MasterColumnService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
 
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
     * Create a new custom column for a project.
     */
    public function storeCustom(Request $request, Project $project)
    {
        $data = $request->validate([
            'label'           => 'required|string|max:100',
            'column_type'     => 'required|in:text,date,number,select',
            'after_config_id' => 'nullable|integer|exists:master_column_configs,id',
        ]);

        $slug = substr(Str::slug($data['label'], '_'), 0, 40) . '_' . Str::random(6);

        if ($data['after_config_id']) {
            $after = MasterColumnConfig::where('project_id', $project->id)
                ->where('id', $data['after_config_id'])
                ->firstOrFail();
            $insertAt = $after->display_order + 1;
            MasterColumnConfig::where('project_id', $project->id)
                ->where('display_order', '>=', $insertAt)
                ->increment('display_order');
        } else {
            $insertAt = (MasterColumnConfig::where('project_id', $project->id)->max('display_order') ?? 0) + 1;
        }

        $config = MasterColumnConfig::create([
            'project_id'    => $project->id,
            'source_table'  => 'custom',
            'source_column' => $slug,
            'display_label' => $data['label'],
            'column_type'   => $data['column_type'],
            'display_order' => $insertAt,
            'is_pinned'     => true,
            'is_locked'     => false,
            'pinned_by'     => auth()->id(),
        ]);

        return response()->json($config, 201);
    }

    /**
     * Delete a custom column and all its stored values.
     */
    public function destroyCustom(Project $project, MasterColumnConfig $config)
    {
        abort_if($config->project_id !== $project->id, 404);
        abort_if($config->source_table !== 'custom', 403, 'Only custom columns can be deleted.');

        CustomCellValue::where('source_column', $config->source_column)->delete();

        ColumnStatusOption::where('source_table', 'custom')
            ->where('source_column', $config->source_column)
            ->delete();

        $config->delete();

        return response()->json(['message' => 'Column deleted.']);
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