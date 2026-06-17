@extends('layouts.app', ['project' => $project])
@section('title', 'SAP Tracking — ' . $project->name)

@section('content')
<div class="flex flex-col h-full" x-data="sapGrid({{ $project->id }}, @json(auth()->user()->isSuperAdmin()))">

    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0">
        <div class="w-2 h-5 rounded bg-amber-500"></div>
        <h2 class="text-sm font-semibold text-gray-900">SAP Detailed Tracking</h2>
        <div class="flex-1"></div>
        <input x-model="search" @input="debounce(() => { page=1; load() }, 300)()"
            class="input-sm w-44" placeholder="Search wells…" />

        <a href="/projects/{{ $project->id }}/export/sap"
           class="btn-secondary btn-sm flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
            </svg>
            Export CSV
        </a>
    </div>

    <div class="flex-1 overflow-auto scrollbar-thin relative">
        <div x-show="loading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
            <svg class="animate-spin w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    @if(auth()->user()->isSuperAdmin())
                    <th class="w-10 text-center bg-stone-100 text-stone-400">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600"
                            :checked="allOnPageSelected()"
                            x-effect="$el.indeterminate = selectedIds.length > 0 && !allOnPageSelected()"
                            @change="selectAll()">
                    </th>
                    @endif
                    {{-- Non-SAP columns (read-only) — grayed out --}}
                    <th class="bg-stone-100 text-stone-400">Well No.</th>
                    <th class="bg-stone-100 text-stone-400">Well ID</th>
                    <th class="bg-stone-100 text-stone-400 border-r-2 border-stone-300">Type</th>
                    {{-- SAP-owned columns --}}
                    <th>Start Date</th><th>End Date</th>
                    <th>Z6 Number</th><th>SAP Status</th>
                    <th>AFC EDW Status</th><th>Tie-in Completed</th>
                    <th>Commissioning</th><th>SAP KPI</th>
                    {{-- Trailing info columns — grayed out --}}
                    <th class="bg-stone-100 text-stone-400">Updated by</th>
                    <th class="w-20 bg-stone-100 text-stone-400">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="well in wells" :key="well.id">
                    <tr class="group" :class="isSelected(well.id) ? 'bg-blue-50' : ''">
                        @if(auth()->user()->isSuperAdmin())
                        <td class="w-10 text-center" @click.stop>
                            <input type="checkbox" class="rounded border-gray-300 text-blue-600"
                                :checked="isSelected(well.id)"
                                @change="toggleSelect(well.id)">
                        </td>
                        @endif
                        {{-- Non-SAP / read-only cells --}}
                        <td class="bg-stone-100 text-stone-400 font-medium" x-text="well.well_number"></td>
                        <td class="bg-stone-100 text-stone-400" x-text="well.well_id"></td>
                        <td class="bg-stone-100 border-r-2 border-stone-300">
                            <span class="text-xs font-medium text-stone-400" x-text="well.well_type || '—'"></span>
                        </td>
                        {{-- SAP-owned cells --}}
                        <td x-text="fmtDate(well.sap_tracking?.earliest_start_date)"></td>
                        <td x-text="fmtDate(well.sap_tracking?.earliest_end_date)"></td>
                        <td class="font-mono text-xs" x-text="well.sap_tracking?.z6_number || '—'"></td>
                        <td>
                            <span x-show="well.sap_tracking?.sap_status"
                                class="badge"
                                :class="{
                                    'badge-green': well.sap_tracking?.sap_status?.includes('Completed'),
                                    'badge-amber': well.sap_tracking?.sap_status?.includes('Progress'),
                                    'badge-gray':  !well.sap_tracking?.sap_status?.includes('Completed') && !well.sap_tracking?.sap_status?.includes('Progress'),
                                }"
                                x-text="well.sap_tracking?.sap_status"></span>
                            <span x-show="!well.sap_tracking?.sap_status" class="text-gray-300">—</span>
                        </td>
                        <td x-text="well.sap_tracking?.afc_edw_status || '—'"></td>
                        <td x-text="fmtDate(well.sap_tracking?.hook_up_tie_in_completed)"></td>
                        <td x-text="fmtDate(well.sap_tracking?.commissioning_date)"></td>
                        <td>
                            <span x-show="well.sap_tracking?.sap_kpi != null"
                                class="badge"
                                :class="badgeClass(kpiStatus(well.sap_tracking?.sap_kpi))"
                                x-text="well.sap_tracking?.sap_kpi + 'd'"></span>
                            <span x-show="well.sap_tracking?.sap_kpi == null" class="text-gray-300">—</span>
                        </td>
                        <td class="bg-stone-100 text-stone-400 text-xs" x-text="well.sap_tracking?.updated_by_name || '—'"></td>
                        <td class="bg-gray-50">
                            <button @click="openEdit(well)"
                                class="opacity-0 group-hover:opacity-100 btn-secondary btn-sm transition-opacity">
                                Edit
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && wells.length === 0">
                    <td colspan="{{ auth()->user()->isSuperAdmin() ? 14 : 13 }}" class="text-center py-10 text-gray-400">No wells found.</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex items-center justify-between px-4 py-2 border-t border-gray-200 bg-white text-xs text-gray-500 shrink-0">
        <span x-text="(pagination?.total || 0) + ' wells'"></span>
        <div class="flex gap-1">
            <template x-for="p in Array.from({length: pagination?.last_page || 1}, (_,i) => i+1)" :key="p">
                <button @click="page = p; load()" class="w-7 h-7 rounded text-xs transition-colors"
                    :class="p === page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600'"
                    x-text="p"></button>
            </template>
        </div>
    </div>

    {{-- Edit modal --}}
    <template x-if="editWell">
    <div class="modal-backdrop" @click.self="closeEdit()">
        <div class="modal max-w-2xl" @click.stop>
            <div class="card-header">
                <div class="w-2 h-5 rounded bg-amber-500"></div>
                <h2 class="font-semibold text-gray-900">
                    SAP Tracking — Well <span x-text="editWell?.well_number"></span>
                </h2>
                <button @click="closeEdit()" class="ml-auto btn-ghost btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="card-body">
                <div x-show="saveError" class="alert-danger mb-4" x-text="saveError"></div>
                <div class="grid grid-cols-2 gap-3">
                    @php
                    $statusOpts = 'SAP PM Completed,In Progress,Pending,On Hold,Not Started,N/A';
                    $sapFields = [
                        ['earliest_start_date','Earliest Start Date','date'],
                        ['earliest_end_date','Earliest End Date','date'],
                        ['z6_number','Z6 Number','text'],
                        ['sap_status','SAP Status','select:SAP PM Completed,In Progress,Pending,On Hold,Not Started'],
                        ['afc_edw_status','AFC EDW Status','status:'.$statusOpts],
                        ['hook_up_tie_in_completed','Tie-in Completed','date'],
                        ['commissioning_date','Commissioning Date','date'],
                        ['afc_document_spo_status','AFC Document SPO Status','status:'.$statusOpts],
                        ['z6_creation_date','Z6 Creation Date','date'],
                        ['z6_awap_date','Z6 AWAP Date','date'],
                        ['z6_appr_date','Z6 APPR Date','date'],
                        ['sap_upload_date','SAP Upload Date','date'],
                        ['bom_linking_date','BOM Linking Date','date'],
                        ['bom_linking_status','BOM Linking Status','status:'.$statusOpts],
                        ['cims_upload_status','CIMS Upload Status','status:'.$statusOpts],
                        ['spir_status','SPIR Status','status:'.$statusOpts],
                        ['asb_edw_status','ASB EDW Status','status:'.$statusOpts],
                        ['edw_remark','EDW Remark','text'],
                    ];
                    @endphp
                    @foreach($sapFields as [$field, $label, $type])
                    <div>
                        <label class="label">{{ $label }}</label>
                        @if($type === 'date')
                            <input x-model="editWell.sap_tracking.{{ $field }}" type="date" class="input" />
                        @elseif(str_starts_with($type, 'select:'))
                            <select x-model="editWell.sap_tracking.{{ $field }}" class="select">
                                <option value="">— Select —</option>
                                @foreach(explode(',', substr($type, 7)) as $opt)
                                <option value="{{ trim($opt) }}">{{ trim($opt) }}</option>
                                @endforeach
                            </select>
                        @elseif(str_starts_with($type, 'status:'))
                            @php $opts = array_map('trim', explode(',', substr($type, 7))); @endphp
                            <select
                                :value="isCustom('{{ $field }}') ? '__custom__' : (editWell.sap_tracking['{{ $field }}'] ?? '')"
                                @change="selectStatus('{{ $field }}', $event.target.value)"
                                class="select">
                                <option value="">— Select —</option>
                                @foreach($opts as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                                <option value="__custom__">Other / type comment…</option>
                            </select>
                            <input
                                x-show="isCustom('{{ $field }}')"
                                x-model="editWell.sap_tracking['{{ $field }}']"
                                type="text"
                                class="input mt-1 text-sm"
                                placeholder="Type comment or custom value…" />
                        @else
                            <input x-model="editWell.sap_tracking.{{ $field }}" type="text" class="input" />
                        @endif
                    </div>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 pt-4 mt-2 border-t border-gray-100">
                    <button @click="closeEdit()" class="btn-secondary">Cancel</button>
                    <button @click="save()" :disabled="saving" class="btn-primary">
                        <span x-text="saving ? 'Saving…' : 'Save changes'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- ── Bulk Delete Action Bar ─────────────────────────────────────────────── --}}
    @if(auth()->user()->isSuperAdmin())
    <div x-show="selectedIds.length > 0"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-3 bg-gray-900 text-white px-5 py-3 rounded-xl shadow-2xl z-50 text-sm">
        <span x-text="selectedIds.length + ' well(s) selected'"></span>
        <button @click="clearSelection()" class="text-gray-400 hover:text-white px-2 transition-colors">Clear</button>
        <div class="h-4 border-l border-gray-600"></div>
        <button @click="deleteSelected()"
                class="flex items-center gap-1.5 bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Delete selected
        </button>
    </div>
    @endif

</div>
@endsection
