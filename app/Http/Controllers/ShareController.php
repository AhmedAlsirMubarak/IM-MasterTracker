<?php

namespace App\Http\Controllers;

use App\Models\CustomCellValue;
use App\Models\HiddenWellCell;
use App\Models\ProjectShare;
use App\Models\ProjectWell;
use App\Services\CountdownService;
use App\Services\MasterColumnService;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    public function __construct(
        private CountdownService $countdown,
        private MasterColumnService $columnService,
    ) {}

    private function resolveShare(string $token): ProjectShare
    {
        $share = ProjectShare::where('token', $token)->firstOrFail();
        abort_if($share->isExpired(), 410, 'This share link has expired.');
        abort_if(!$share->project, 404); // project was deleted
        return $share;
    }

    public function view(string $token)
    {
        $share   = $this->resolveShare($token);
        $project = $share->project;
        $columns = $this->columnService->getPinnedColumns($project->id, false);

        return view('share.master', compact('share', 'project', 'columns'));
    }

    public function wells(string $token, Request $request)
    {
        $share   = $this->resolveShare($token);
        $project = $share->project;

        $query = ProjectWell::where('project_id', $project->id)
            ->with(['sapTracking', 'dcTracking'])
            ->orderByRaw('well_number IS NULL, well_number ASC');

        $year = $request->input('year', (string) date('Y'));
        if ($year !== 'all') {
            $query->where('year', (int) $year);
        }

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q->where('well_id', 'like', "%{$search}%")
                ->orWhere('well_number', 'like', "%{$search}%")
                ->orWhere('ramz_id', 'like', "%{$search}%"));
        }

        if ($type = $request->input('well_type')) {
            $query->where('well_type', $type);
        }

        $wells = $query->paginate(max(1, min(200, (int) $request->input('per_page', 50))));

        // Public link: send only the visible pinned columns, never whole rows
        $columns = $this->columnService->getPinnedColumns($project->id, false);
        $visible = $columns->groupBy('source_table')->map(fn ($cols) => $cols->pluck('source_column')->all());

        $wellIds = $wells->pluck('id');
        $hiddenByWell = HiddenWellCell::whereIn('well_id', $wellIds)
            ->get(['well_id', 'source_table', 'source_column'])
            ->groupBy('well_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->source_table . '.' . $r->source_column)->all());

        $customByWell = CustomCellValue::whereIn('well_id', $wellIds)
            ->get(['well_id', 'source_column', 'value'])
            ->groupBy('well_id');

        $pick = function (array $row, string $table, array $hidden) use ($visible): array {
            $out = [];
            foreach ($visible->get($table, []) as $col) {
                $out[$col] = in_array("{$table}.{$col}", $hidden, true) ? null : ($row[$col] ?? null);
            }
            return $out;
        };

        $wells->setCollection($wells->getCollection()->map(function ($well) use ($pick, $hiddenByWell, $customByWell) {
            $hidden = $hiddenByWell->get($well->id, []);

            return array_merge(
                ['id' => $well->id],
                $pick($well->attributesToArray(), 'project_wells', $hidden),
                [
                    'sap_tracking' => $pick($well->sapTracking?->attributesToArray() ?? [], 'sap_tracking', $hidden),
                    'dc_tracking'  => $pick($well->dcTracking?->attributesToArray() ?? [], 'dc_tracking', $hidden),
                    // Computed values drive the KPI/countdown badges, so all of them are sent (minus hidden cells)
                    'computed'     => collect($this->countdown->computeForWell($well))
                        ->map(fn ($v, $k) => in_array("computed.{$k}", $hidden, true) ? null : $v)
                        ->all(),
                    'custom'       => $pick($customByWell->get($well->id, collect())->pluck('value', 'source_column')->all(), 'custom', $hidden),
                ]
            );
        }));

        return response()->json([
            'wells'        => $wells,
            'columns'      => $columns,
            'project_name' => $project->name,
        ]);
    }
}
