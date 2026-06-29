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

        $wells = $query->paginate($request->input('per_page', 50));

        // Hidden cells for this page (strip values for public view)
        $wellIds = $wells->pluck('id');
        $hiddenByWell = HiddenWellCell::whereIn('well_id', $wellIds)
            ->get()
            ->groupBy('well_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->source_table . '.' . $r->source_column)->values());

        $customByWell = CustomCellValue::whereIn('well_id', $wellIds)
            ->get()
            ->groupBy('well_id');

        $wells->getCollection()->transform(function ($well) use ($customByWell, $hiddenByWell) {
            $well->computed = $this->countdown->computeForWell($well);
            $well->custom   = $customByWell->get($well->id, collect())
                ->pluck('value', 'source_column')
                ->toArray();

            // Strip hidden cell values from the public response
            $hidden = $hiddenByWell->get($well->id, collect())->all();
            if ($hidden) {
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

            return $well;
        });

        $columns = $this->columnService->getPinnedColumns($project->id, false);

        return response()->json([
            'wells'        => $wells,
            'columns'      => $columns,
            'project_name' => $project->name,
        ]);
    }
}
