@extends('layouts.app', ['project' => $project])
@section('title', 'Master View — ' . $project->name)

@section('content')
<div class="flex flex-col h-full"
     x-data="masterGrid({{ $project->id }}, @json(auth()->user()->isSuperAdmin()))"
     @cell-saved.window="onCellSaved($event.detail)">

    {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0 flex-wrap">
        <h2 class="text-sm font-semibold text-gray-900">IM-Master Track Overall</h2>

        {{-- Year selector — yearsReady guard prevents a spurious load() on the initial option render --}}
        <select x-model="filterYear" @change="if (yearsReady) { page=1; load() }" class="input-sm w-24">
            <option value="all">All years</option>
            <template x-for="y in availableYears" :key="y">
                <option :value="String(y)" x-text="y"></option>
            </template>
        </select>

        <div class="flex-1"></div>

        <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input x-model="search" @input="debouncedLoad()" placeholder="Search wells…"
                class="input-sm pl-8 w-44"/>
        </div>

        <select x-model="filterType" @change="page=1; load()" class="input-sm w-28">
            <option value="">All types</option>
            <option>OP</option><option>OP FF</option><option>WI</option>
        </select>

        {{-- Shifted-only toggle --}}
        <button @click="toggleShiftedOnly()"
            :class="shiftedOnly ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'"
            class="flex items-center gap-1.5"
            title="Show only wells that were moved to this year from another year">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            <span x-text="'Shifted in' + (shiftedCount > 0 ? ' (' + shiftedCount + ')' : '')"></span>
        </button>

        {{-- Departed toggle --}}
        <button @click="toggleDeparted()"
            :class="showDeparted ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'"
            class="flex items-center gap-1.5"
            title="Show wells that left this year after a re-import">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span x-text="'Shifted out' + (departedCount > 0 ? ' (' + departedCount + ')' : '')"></span>
        </button>

        <a href="/projects/{{ $project->id }}/export/master"
           class="btn-secondary btn-sm flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
            </svg>
            Export CSV
        </a>

        @if(auth()->user()->isSuperAdmin())
        <button @click="showAddWell = true" class="btn-primary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add well
        </button>
        @endif
    </div>

    {{-- ── Source legend ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-5 px-4 py-1.5 bg-gray-50 border-b border-gray-200 text-2xs text-gray-500 shrink-0">
        @foreach([['project_wells','Fixed (Super Admin / Manager)','#1D9E75'],['sap_tracking','SAP Tracking','#BA7517'],['dc_tracking','DC Tracking','#D85A30'],['computed','Computed (auto)','#639922']] as [$key,$label,$color])
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-4 h-0.5 rounded" style="background:{{ $color }}"></span>
            {{ $label }}
        </span>
        @endforeach
    </div>

    {{-- ── Scrollable table ─────────────────────────────────────────────────── --}}
    <div class="flex-1 overflow-auto scrollbar-thin relative" id="master-table-wrap">

        {{-- Loading overlay --}}
        <div x-show="loading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
            <div class="flex items-center gap-2 text-gray-500 text-sm">
                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Loading…
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    @if(auth()->user()->isSuperAdmin())
                    <th class="w-10 text-center bg-gray-50" style="border-top:2.5px solid transparent">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600"
                            :checked="allOnPageSelected()"
                            x-effect="$el.indeterminate = selectedIds.length > 0 && !allOnPageSelected()"
                            @change="selectAll()">
                    </th>
                    @endif
                    <template x-for="col in columns" :key="col.source_column">
                        <th :style="`border-top: 2.5px solid ${sourceColor(col.source_table)}`"
                            x-text="col.display_label"></th>
                    </template>
                    <th class="w-8 bg-gray-50" style="border-top:2.5px solid transparent"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="well in wells" :key="well.id">
                    <tr :class="[activeWellId === well.id ? 'active-row' : '', isSelected(well.id) ? 'bg-blue-50' : '', well.previous_year ? 'ring-inset ring-1 ring-amber-300' : '']" class="group">

                        @if(auth()->user()->isSuperAdmin())
                        <td class="w-10 text-center" @click.stop>
                            <input type="checkbox" class="rounded border-gray-300 text-blue-600"
                                :checked="isSelected(well.id)"
                                @change="toggleSelect(well.id)">
                        </td>
                        @endif

                        <template x-for="col in columns" :key="col.source_column">
                            <td
                                :class="canEdit(col, '{{ auth()->user()->role }}', {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }})
                                        ? 'cursor-pointer hover:bg-blue-50 group/cell'
                                        : 'text-gray-400'"
                                @click="canEdit(col, '{{ auth()->user()->role }}', {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}) && openCell(well, col)"
                                @mouseenter="showCellTooltip($event, well, col)"
                                @mouseleave="hideCellTooltip()">

                                {{-- SAP KPI computed --}}
                                <template x-if="col.source_column === 'sap_kpi'">
                                    <span class="badge"
                                        :class="badgeClass(kpiStatus(well.computed?.sap_kpi))"
                                        x-text="well.computed?.sap_kpi != null ? well.computed.sap_kpi + 'd' : '—'"></span>
                                </template>

                                {{-- Countdown computed --}}
                                <template x-if="col.source_column === 'countdown_days'">
                                    <span class="badge flex items-center gap-1"
                                        :class="badgeClass(well.computed?.countdown_status)"
                                        :title="well.computed?.countdown_status === 'overdue' ? 'Over 90 days — E-Pad not closed' : ''">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                            :class="{
                                                'bg-red-500':   well.computed?.countdown_status === 'overdue',
                                                'bg-amber-500': well.computed?.countdown_status === 'warning',
                                                'bg-green-500': ['ok','closed'].includes(well.computed?.countdown_status),
                                                'bg-gray-400':  !well.computed?.countdown_status || well.computed?.countdown_status === 'none',
                                            }"></span>
                                        <span x-text="well.computed?.countdown_days != null ? well.computed.countdown_days + 'd' : '—'"></span>
                                    </span>
                                </template>

                                {{-- Regular cell --}}
                                <template x-if="col.source_column !== 'sap_kpi' && col.source_column !== 'countdown_days'">
                                    <span class="flex items-center gap-1">
                                        <span x-text="getCellValue(well, col) || '—'"></span>
                                        {{-- Comment indicator --}}
                                        <svg x-show="getCellComment(well, col)"
                                            class="w-3 h-3 text-blue-400 shrink-0"
                                            fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z"/>
                                        </svg>
                                        {{-- Edit pencil --}}
                                        <svg x-show="canEdit(col, '{{ auth()->user()->role }}', {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }})"
                                            class="w-3 h-3 text-gray-300 opacity-0 group-hover/cell:opacity-100 transition-opacity shrink-0"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </span>
                                    {{-- Updated-by micro-text --}}
                                    <span x-show="getUpdatedBy(well, col)"
                                        class="block text-2xs text-gray-400 leading-none mt-0.5"
                                        x-text="getUpdatedBy(well, col)"></span>
                                </template>
                            </td>
                        </template>

                        {{-- Audit icon + shifted badge --}}
                        <td class="w-8">
                            <div class="flex items-center gap-1">
                                <button @click.stop="openAudit(well)"
                                    class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-blue-600 p-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                                <span x-show="well.previous_year"
                                    class="text-2xs font-medium text-amber-700 bg-amber-100 border border-amber-300 rounded px-1 py-0.5 whitespace-nowrap"
                                    :title="'Shifted from ' + well.previous_year"
                                    x-text="'← ' + well.previous_year"></span>
                            </div>
                        </td>
                    </tr>
                </template>

                <tr x-show="!loading && wells.length === 0">
                    <td :colspan="columns.length + {{ auth()->user()->isSuperAdmin() ? 2 : 1 }}" class="text-center py-12 text-gray-400">
                        No wells found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Pagination ───────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between px-4 py-2 border-t border-gray-200 bg-white text-xs text-gray-500 shrink-0">
        <span x-text="(pagination?.total || 0) + ' wells'"></span>
        <div class="flex gap-1">
            <template x-for="p in Array.from({length: pagination?.last_page || 1}, (_,i) => i+1)" :key="p">
                <button @click="page = p; load()"
                    class="w-7 h-7 rounded text-xs transition-colors"
                    :class="p === page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600'"
                    x-text="p"></button>
            </template>
        </div>
    </div>

    {{-- ── Departed Wells Panel ───────────────────────────────────────────────── --}}
    <div x-show="showDeparted"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="border-t border-amber-200 bg-amber-50 shrink-0" style="max-height:220px;overflow-y:auto">
        <div class="flex items-center justify-between px-4 py-2 sticky top-0 bg-amber-50 border-b border-amber-200 z-10">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span class="text-xs font-semibold text-amber-800"
                    x-text="`Wells that left ${filterYear !== 'all' ? filterYear : 'this view'} after re-import`"></span>
                <span class="text-2xs text-amber-600 bg-amber-200 px-1.5 py-0.5 rounded-full" x-text="departedWells.length + ' well(s)'"></span>
            </div>
            <button @click="showDeparted = false" class="text-amber-500 hover:text-amber-800 transition-colors p-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div x-show="loadingDeparted" class="flex items-center justify-center py-6 text-amber-600 text-sm gap-2">
            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Loading…
        </div>
        <div x-show="!loadingDeparted && departedWells.length === 0" class="text-center py-6 text-amber-600 text-sm">
            No wells shifted away from this year.
        </div>
        <table x-show="!loadingDeparted && departedWells.length > 0" class="w-full text-xs">
            <thead>
                <tr class="text-left text-amber-700 border-b border-amber-200">
                    <th class="px-4 py-1.5 font-medium">Well ID</th>
                    <th class="px-4 py-1.5 font-medium">Well #</th>
                    <th class="px-4 py-1.5 font-medium">Type</th>
                    <th class="px-4 py-1.5 font-medium">Was in year</th>
                    <th class="px-4 py-1.5 font-medium">Now in year</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="w in departedWells" :key="w.id">
                    <tr class="border-b border-amber-100 hover:bg-amber-100 transition-colors">
                        <td class="px-4 py-1.5 text-gray-700" x-text="w.well_id || '—'"></td>
                        <td class="px-4 py-1.5 text-gray-700" x-text="w.well_number || '—'"></td>
                        <td class="px-4 py-1.5 text-gray-500" x-text="w.well_type || '—'"></td>
                        <td class="px-4 py-1.5">
                            <span class="font-semibold text-amber-700" x-text="w.previous_year"></span>
                        </td>
                        <td class="px-4 py-1.5">
                            <span class="font-semibold text-green-700 bg-green-100 rounded px-1" x-text="w.year || '—'"></span>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- ── Cell Edit Slide Panel ────────────────────────────────────────────── --}}
    <div class="slide-panel flex flex-col"
         :class="editCell ? '' : 'hidden-panel'"
         x-show="editCell"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         style="height: 320px">
        @include('master.partials.cell-edit-panel')
    </div>

    {{-- ── Audit Drawer ─────────────────────────────────────────────────────── --}}
    <div class="drawer"
         :class="auditWell ? '' : 'hidden-drawer'"
         x-show="auditWell"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0">
        @include('master.partials.audit-drawer')
    </div>

    {{-- ── Add Well Modal ────────────────────────────────────────────────────── --}}
    @include('master.partials.add-well-modal')

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

    {{-- ── Cell Comment Tooltip ────────────────────────────────────────────────── --}}
    <div x-show="cellTooltip.visible"
         :style="`left:${cellTooltip.x}px; top:${cellTooltip.y}px`"
         class="fixed z-9999 bg-gray-900 text-white text-xs rounded-lg px-3 py-2 max-w-xs shadow-xl pointer-events-none"
         style="display:none">
        <p class="leading-relaxed" x-text="cellTooltip.comment?.comment_text"></p>
        <p class="text-gray-400 mt-1" x-text="cellTooltip.comment?.author?.name ? '— ' + cellTooltip.comment.author.name : ''"></p>
    </div>

</div>
@endsection
