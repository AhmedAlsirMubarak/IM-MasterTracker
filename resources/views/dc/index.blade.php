@extends('layouts.app', ['project' => $project])
@section('title', 'DC Tracking — ' . $project->name)

@section('content')
<div class="flex flex-col h-full" x-data="dcGrid({{ $project->id }}, @json(auth()->user()->isSuperAdmin()))">

    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0">
        <div class="w-2 h-5 rounded bg-orange-500"></div>
        <h2 class="text-sm font-semibold text-gray-900">DC Detailed Tracking</h2>
        <div class="flex-1"></div>
        <input x-model="search" @input="debounce(() => { page=1; load() }, 300)()"
            class="input-sm w-44" placeholder="Search wells…" />

        <a href="/projects/{{ $project->id }}/export/dc"
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
                    {{-- Non-DC columns (read-only) — stone tint --}}
                    <th class="bg-stone-100 text-stone-400">Well No.</th>
                    <th class="bg-stone-100 text-stone-400">Well ID</th>
                    <th class="bg-stone-100 text-stone-400 border-r-2 border-stone-300">Type</th>
                    {{-- DC-owned columns --}}
                    <th>ASB Document Status</th><th>ASB Receive Date</th>
                    <th>ASB Submission Date</th><th>ASB TR No</th>
                    <th>AFC Upload Status</th><th>RLMU Upload Status</th>
                    <th>IM Health Check</th>
                    {{-- Trailing info columns — stone tint --}}
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
                        {{-- Non-DC / read-only cells --}}
                        <td class="bg-stone-100 text-stone-400 font-medium" x-text="well.well_number"></td>
                        <td class="bg-stone-100 text-stone-400" x-text="well.well_id"></td>
                        <td class="bg-stone-100 border-r-2 border-stone-300">
                            <span class="text-xs font-medium text-stone-400" x-text="well.well_type || '—'"></span>
                        </td>
                        {{-- DC-owned cells --}}
                        <td>
                            <span x-show="well.dc_tracking?.asb_document_status" class="badge"
                                :class="{
                                    'badge-amber': well.dc_tracking?.asb_document_status === 'UNDER REVIEW',
                                    'badge-green': ['Submitted','RLMU','Approved'].includes(well.dc_tracking?.asb_document_status),
                                    'badge-red':   well.dc_tracking?.asb_document_status === 'Rejected',
                                    'badge-gray':  !['UNDER REVIEW','Submitted','RLMU','Approved','Rejected'].includes(well.dc_tracking?.asb_document_status),
                                }"
                                x-text="well.dc_tracking?.asb_document_status"></span>
                            <span x-show="!well.dc_tracking?.asb_document_status" class="text-gray-300">—</span>
                        </td>
                        <td x-text="fmtDate(well.dc_tracking?.asb_receive_date)"></td>
                        <td x-text="fmtDate(well.dc_tracking?.asb_document_submission_date)"></td>
                        <td class="font-mono text-xs" x-text="well.dc_tracking?.asb_tr_no || '—'"></td>
                        <td x-text="well.dc_tracking?.afc_upload_status || '—'"></td>
                        <td x-text="well.dc_tracking?.rlmu_upload_status || '—'"></td>
                        <td x-text="well.dc_tracking?.document_im_health_check || '—'"></td>
                        <td class="bg-stone-100 text-stone-400 text-xs" x-text="well.dc_tracking?.updated_by_name || '—'"></td>
                        <td class="bg-stone-100">
                            <button @click="openEdit(well)"
                                class="opacity-0 group-hover:opacity-100 btn-secondary btn-sm transition-opacity">
                                Edit
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && wells.length === 0">
                    <td colspan="{{ auth()->user()->isSuperAdmin() ? 13 : 12 }}" class="text-center py-10 text-gray-400">No wells found.</td>
                </tr>
            </tbody>
        </table>
    </div>

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
        <div class="modal" @click.stop>
            <div class="card-header">
                <div class="w-2 h-5 rounded bg-orange-500"></div>
                <h2 class="font-semibold text-gray-900">
                    DC Tracking — Well <span x-text="editWell?.well_number"></span>
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
                    $dcFields = [
                        ['asb_document_status','ASB Document Status','select:UNDER REVIEW,Submitted,RLMU,Pending,Rejected,Approved'],
                        ['asb_receive_date','ASB Receive Date','date'],
                        ['asb_document_submission_date','ASB Submission Date','date'],
                        ['asb_tr_no','ASB TR No','text'],
                        ['afc_upload_status','AFC Upload Status','select:Uploaded,Pending,Not Required'],
                        ['final_afc_upload_date','Final AFC Upload Date','date'],
                        ['afc_document_remarks','AFC Remarks','text'],
                        ['rlmu_upload_status','RLMU Upload Status','select:Uploaded,Pending,Not Required'],
                        ['final_rlmu_upload_date','Final RLMU Upload Date','date'],
                        ['well_time_document_status','Well Time Document Status','text'],
                        ['document_im_health_check','IM Health Check','select:Y,N'],
                    ];
                    @endphp
                    @foreach($dcFields as [$field, $label, $type])
                    <div>
                        <label class="label">{{ $label }}</label>
                        @if($type === 'date')
                            <input x-model="editWell.dc_tracking.{{ $field }}" type="date" class="input" />
                        @elseif(str_starts_with($type, 'select:'))
                            <select x-model="editWell.dc_tracking.{{ $field }}" class="select">
                                <option value="">— Select —</option>
                                @foreach(explode(',', substr($type, 7)) as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        @else
                            <input x-model="editWell.dc_tracking.{{ $field }}" type="text" class="input" />
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
