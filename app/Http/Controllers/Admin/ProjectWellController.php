<?php
 
namespace App\Http\Controllers\Admin;
 
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectWell;
use App\Models\CellComment;
use App\Models\AuditLog;
use App\Models\CustomCellValue;
use App\Models\HiddenWellCell;
use App\Services\AuditService;
use App\Services\CountdownService;
use App\Services\MasterColumnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
 
class ProjectWellController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private CountdownService $countdown,
        private MasterColumnService $columnService
    ) {}
 
    /**
     * Returns distinct years to show in the dropdown.
     * Merges both current well years AND years wells departed FROM,
     * so a year remains selectable even after all its wells moved away.
     */
    public function years(Project $project)
    {
        $current  = ProjectWell::where('project_id', $project->id)
            ->whereNotNull('year')
            ->distinct()
            ->pluck('year');

        $departed = ProjectWell::where('project_id', $project->id)
            ->whereNotNull('previous_year')
            ->distinct()
            ->pluck('previous_year');

        $years = $current->merge($departed)
            ->map(fn($y) => (int) $y)
            ->unique()
            ->sortDesc()
            ->values();

        return response()->json($years);
    }

    /**
     * Returns shift counts in a single cheap query pair (no eager loads).
     * shifted  = wells in this year that arrived from another year
     * departed = wells that used to be in this year but moved away
     */
    public function counts(Project $project, Request $request)
    {
        $year = $request->input('year', (string) date('Y'));

        $base = ProjectWell::where('project_id', $project->id);
        if ($year !== 'all') {
            $base->where('year', (int) $year);
        }

        $shifted = (clone $base)->whereNotNull('previous_year')->count();

        $departed = ($year !== 'all')
            ? ProjectWell::where('project_id', $project->id)
                ->where('previous_year', (int) $year)
                ->where('year', '!=', (int) $year)
                ->count()
            : 0;

        return response()->json(['shifted' => $shifted, 'departed' => $departed]);
    }

    /**
     * Returns wells that were shifted OUT of a given year (they left that year via re-import).
     * Only returns columns needed by the UI — no tracking relation joins.
     */
    public function departed(Project $project, Request $request)
    {
        $year = $request->input('year', (string) date('Y'));

        $wells = ProjectWell::where('project_id', $project->id)
            ->where('previous_year', (int) $year)
            ->where('year', '!=', (int) $year)
            ->select(['id', 'well_id', 'well_number', 'well_type', 'year', 'previous_year'])
            ->orderByRaw('well_number IS NULL, well_number ASC')
            ->get();

        return response()->json($wells);
    }

    /**
     * Master grid — returns wells with all pinned column data joined.
     * Defaults to current year. Pass year=all for no year filter.
     */
    public function index(Project $project, Request $request)
    {
        $query = ProjectWell::where('project_id', $project->id)
            ->with(['sapTracking.updatedBy:id,name', 'dcTracking.updatedBy:id,name', 'cellComments.author:id,name'])
            ->orderByRaw('well_number IS NULL, well_number ASC');

        // Year filter — default to current year
        $year = $request->input('year', (string) date('Y'));
        if ($year !== 'all') {
            $query->where('year', (int) $year);
        }

        // Shifted-only filter
        if ($request->boolean('shifted_only')) {
            $query->whereNotNull('previous_year');
        }
 
        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('well_id', 'like', "%{$search}%")
                  ->orWhere('well_number', 'like', "%{$search}%")
                  ->orWhere('ramz_id', 'like', "%{$search}%");
            });
        }
 
        // Filter by well type
        if ($type = $request->input('well_type')) {
            $query->where('well_type', $type);
        }

        // Well ID filter (dedicated filter field, separate from text search)
        if ($wellId = $request->input('well_id_f')) {
            $query->where('well_id', 'like', "%{$wellId}%");
        }

        // Date range filter (SAP earliest start / end date)
        if ($from = $request->input('date_from')) {
            $query->whereHas('sapTracking', fn ($q) => $q->whereDate('earliest_start_date', '>=', $from));
        }
        if ($to = $request->input('date_to')) {
            $query->whereHas('sapTracking', fn ($q) => $q->whereDate('earliest_end_date', '<=', $to));
        }

        // E-Pad closeout status filter
        if ($epad = $request->input('epad_status')) {
            if ($epad === 'closed') {
                $query->where('e_pad_closeout', 'Closed');
            } else {
                $query->where(fn ($q) => $q->whereNull('e_pad_closeout')
                    ->orWhere('e_pad_closeout', '!=', 'Closed'));
            }
        }

        // SAP status filter (via sap_tracking join)
        if ($sapStatus = $request->input('sap_status')) {
            $query->whereHas('sapTracking', fn ($q) => $q->where('sap_status', $sapStatus));
        }

        // Delayed commissioning: elapsed days > 90 AND e-pad not closed
        if ($request->boolean('delayed_only')) {
            $cutoff = now()->subDays(90)->toDateString();
            $query->whereHas('sapTracking', fn ($q) => $q->whereDate('commissioning_date', '<=', $cutoff))
                  ->where(fn ($q) => $q->whereNull('e_pad_closeout')
                      ->orWhereRaw("LOWER(TRIM(COALESCE(e_pad_closeout,''))) != 'closed'"));
        }

        $wells = $query->paginate($request->input('per_page', 50));

        // Load custom cell values and hidden cells for all wells on this page
        $wellIds      = $wells->pluck('id');
        $isSuperAdmin = auth()->user()->isSuperAdmin();

        $customByWell = CustomCellValue::whereIn('well_id', $wellIds)
            ->get()
            ->groupBy('well_id');

        $hiddenByWell = HiddenWellCell::whereIn('well_id', $wellIds)
            ->get()
            ->groupBy('well_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->source_table . '.' . $r->source_column)->values());

        // Attach computed fields and latest comment per column
        $wells->getCollection()->transform(function ($well) use ($customByWell, $hiddenByWell, $isSuperAdmin) {
            $well->computed = $this->countdown->computeForWell($well);
            $well->custom   = $customByWell->get($well->id, collect())
                ->pluck('value', 'source_column')
                ->toArray();

            $hidden = $hiddenByWell->get($well->id, collect())->all();
            if ($isSuperAdmin) {
                // Super admin sees all values but gets the list of hidden keys for UI indicators
                $well->hidden_cells = $hidden;
            } else {
                // Non-admin: strip hidden values so they never reach the client
                foreach ($hidden as $key) {
                    [$table, $col] = explode('.', $key, 2);
                    if ($table === 'sap_tracking' && $well->sapTracking) {
                        $well->sapTracking->$col = null;
                    } elseif ($table === 'dc_tracking' && $well->dcTracking) {
                        $well->dcTracking->$col = null;
                    } elseif ($table === 'project_wells') {
                        $well->$col = null;
                    } elseif ($table === 'custom') {
                        unset($well->custom[$col]);
                    }
                }
            }

            $well->cell_comments = $well->cellComments
                ->groupBy(fn($c) => "{$c->source_table}.{$c->source_column}")
                ->map(fn($group) => [
                    'comment_text' => $group->first()->comment_text,
                    'author'       => $group->first()->author ? ['name' => $group->first()->author->name] : null,
                    'created_at'   => $group->first()->created_at,
                ]);

            unset($well->cellComments);
            return $well;
        });
 
        $columns = $this->columnService->getPinnedColumns($project->id, auth()->user()->isSuperAdmin());
 
        return response()->json([
            'wells'   => $wells,
            'columns' => $columns,
        ]);
    }
 
    public function store(Request $request, Project $project)
    {
 
        $data = $request->validate([
            'contract_no'      => 'nullable|string',
            'scope'            => 'nullable|string',
            'hua_date'         => 'nullable|date',
            'well_id'          => 'required|string',
            'well_number'      => 'nullable|string',
            'well_type'        => 'nullable|string',
            'well_field'       => 'nullable|string',
            'well_location'    => 'nullable|string',
            'ramz_id'          => 'nullable|string',
            'year'             => 'nullable|integer|digits:4',
            'responsible_asset'=> 'nullable|string',
        ]);
 
        $well = ProjectWell::create(array_merge($data, [
            'project_id' => $project->id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));
 
        // Create empty SAP and DC tracking rows
        $well->sapTracking()->create(['project_well_id' => $well->id]);
        $well->dcTracking()->create(['project_well_id' => $well->id]);
 
        return response()->json($well->load(['sapTracking', 'dcTracking']), 201);
    }
 
    /**
     * Inline cell update — any user can update their authorized columns.
     */
    public function updateCell(Request $request, ProjectWell $well)
    {
        $data = $request->validate([
            'source_table'  => 'required|in:project_wells,sap_tracking,dc_tracking,custom',
            'source_column' => 'required|string',
            'value'         => 'nullable',
            'comment'       => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        // Manager role: comment-only, no value editing
        if ($user->role === 'manager') {
            if ($data['source_table'] === 'computed') {
                return response()->json(['message' => 'Cannot comment on computed columns.'], 403);
            }
            if (!empty($data['comment'])) {
                CellComment::create([
                    'project_well_id' => $well->id,
                    'source_table'    => $data['source_table'],
                    'source_column'   => $data['source_column'],
                    'comment_text'    => $data['comment'],
                    'commented_by'    => auth()->id(),
                ]);
            }
            $well->refresh()->load(['sapTracking.updatedBy:id,name', 'dcTracking.updatedBy:id,name', 'cellComments.author:id,name']);
            $well->computed      = $this->countdown->computeForWell($well);
            $well->custom        = CustomCellValue::where('well_id', $well->id)->get()->pluck('value', 'source_column')->toArray();
            $well->cell_comments = $well->cellComments
                ->groupBy(fn($c) => "{$c->source_table}.{$c->source_column}")
                ->map(fn($group) => [
                    'comment_text' => $group->first()->comment_text,
                    'author'       => $group->first()->author ? ['name' => $group->first()->author->name] : null,
                    'created_at'   => $group->first()->created_at,
                ]);
            unset($well->cellComments);
            return response()->json(['well' => $well, 'message' => 'Comment saved.']);
        }

        if (!$user->canEditSource($data['source_table'])) {
            return response()->json(['message' => 'You do not have permission to edit this column.'], 403);
        }

        // Extra guard: fixed columns only for super_admin
        if ($data['source_table'] === 'project_wells'
            && in_array($data['source_column'], ProjectWell::$fixedColumns)
            && !$user->canEditFixedColumns()) {
            return response()->json(['message' => 'Only Super Admin can edit fixed columns.'], 403);
        }

        $oldValue = null;

        DB::transaction(function () use ($data, $well, &$oldValue) {
            match ($data['source_table']) {
                'project_wells' => (function () use ($well, $data, &$oldValue) {
                    $oldValue = $well->{$data['source_column']};
                    $payload  = [
                        $data['source_column'] => $data['value'],
                        'updated_by'           => auth()->id(),
                    ];
                    // Track year shifts the same way imports do
                    if ($data['source_column'] === 'year'
                        && $oldValue
                        && $data['value']
                        && (int) $oldValue !== (int) $data['value']) {
                        $payload['previous_year'] = $oldValue;
                    }
                    $well->update($payload);
                })(),

                'sap_tracking' => (function () use ($well, $data, &$oldValue) {
                    $sap = $well->sapTracking ?? $well->sapTracking()->create(['project_well_id' => $well->id]);
                    $oldValue = $sap->{$data['source_column']};
                    $sap->update([
                        $data['source_column'] => $data['value'],
                        'updated_by'           => auth()->id(),
                    ]);
                })(),

                'dc_tracking' => (function () use ($well, $data, &$oldValue) {
                    $dc = $well->dcTracking ?? $well->dcTracking()->create(['project_well_id' => $well->id]);
                    $oldValue = $dc->{$data['source_column']};
                    $dc->update([
                        $data['source_column'] => $data['value'],
                        'updated_by'           => auth()->id(),
                    ]);
                })(),

                'custom' => (function () use ($well, $data, &$oldValue) {
                    $cv = CustomCellValue::firstOrNew([
                        'well_id'       => $well->id,
                        'source_column' => $data['source_column'],
                    ]);
                    $oldValue  = $cv->value;
                    $cv->value      = $data['value'];
                    $cv->updated_by = auth()->id();
                    $cv->project_id = $well->project_id;
                    $cv->save();
                })(),
            };

            // Log audit
            $this->audit->log(
                $data['source_table'], $well->id,
                $data['source_column'], $oldValue, $data['value'], $well->id
            );

            // Save comment if provided
            if (!empty($data['comment'])) {
                CellComment::create([
                    'project_well_id' => $well->id,
                    'source_table'    => $data['source_table'],
                    'source_column'   => $data['source_column'],
                    'comment_text'    => $data['comment'],
                    'commented_by'    => auth()->id(),
                ]);
            }
        });

        // Return updated well with computed fields and latest comment per column
        $well->refresh()->load(['sapTracking.updatedBy:id,name', 'dcTracking.updatedBy:id,name', 'cellComments.author:id,name']);
        $well->computed = $this->countdown->computeForWell($well);
        $well->custom   = CustomCellValue::where('well_id', $well->id)
            ->get()->pluck('value', 'source_column')->toArray();

        $well->cell_comments = $well->cellComments
            ->groupBy(fn($c) => "{$c->source_table}.{$c->source_column}")
            ->map(fn($group) => [
                'comment_text' => $group->first()->comment_text,
                'author'       => $group->first()->author ? ['name' => $group->first()->author->name] : null,
                'created_at'   => $group->first()->created_at,
            ]);
        unset($well->cellComments);

        return response()->json(['well' => $well, 'message' => 'Updated successfully.']);
    }
 
    /**
     * Audit trail for a well.
     */
    public function auditLog(ProjectWell $well)
    {
        $logs = AuditLog::where('project_well_id', $well->id)
            ->with('user:id,name,role')
            ->orderByDesc('created_at')
            ->paginate(30);
 
        return response()->json($logs);
    }
 
    /**
     * Comments for a specific cell.
     */
    public function cellComments(ProjectWell $well, Request $request)
    {
        $comments = CellComment::where('project_well_id', $well->id)
            ->when($request->source_table,  fn($q, $v) => $q->where('source_table', $v))
            ->when($request->source_column, fn($q, $v) => $q->where('source_column', $v))
            ->with('author:id,name,role')
            ->orderByDesc('created_at')
            ->get();
 
        return response()->json($comments);
    }
 
    public function destroy(ProjectWell $well)
    {
        $well->delete();
        return response()->json(['message' => 'Well removed.']);
    }

    public function bulkDestroy(Request $request, Project $project)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $count = ProjectWell::where('project_id', $project->id)
            ->whereIn('id', $data['ids'])
            ->delete();

        return response()->json(['message' => "$count well(s) deleted.", 'count' => $count]);
    }
}