<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HiddenWellCell;
use App\Models\Project;
use App\Models\ProjectWell;
use Illuminate\Http\Request;

class HiddenCellController extends Controller
{
    public function toggle(Request $request, Project $project, ProjectWell $well)
    {
        abort_if((int) $well->project_id !== (int) $project->id, 404);

        $data = $request->validate([
            'source_table'  => 'required|in:project_wells,sap_tracking,dc_tracking,computed,custom',
            'source_column' => 'required|string|max:100',
        ]);

        $existing = HiddenWellCell::where('well_id', $well->id)
            ->where('source_table', $data['source_table'])
            ->where('source_column', $data['source_column'])
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['hidden' => false]);
        }

        HiddenWellCell::create([
            'project_id'    => $project->id,
            'well_id'       => $well->id,
            'source_table'  => $data['source_table'],
            'source_column' => $data['source_column'],
            'hidden_by'     => auth()->id(),
        ]);

        return response()->json(['hidden' => true]);
    }
}
