@extends('layouts.app', ['project' => $project])
@section('title', 'Master View — ' . $project->name)

@section('content')
<div class="flex flex-col h-full"
     x-data="masterGrid({{ $project->id }}, @json(auth()->user()->isSuperAdmin()))"
     @cell-saved.window="onCellSaved($event.detail)">

    {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
    <div class="page-header flex-wrap">
        <h2 class="page-title">IM-Master Track Overall</h2>

        {{-- Year selector — yearsReady guard prevents a spurious load() on the initial option render --}}
        <select x-ref="yearSelect" x-model="filterYear" @change="if (yearsReady) { page=1; load() }" class="input-sm w-24">
            <option value="all">All years</option>
            <template x-for="y in availableYears" :key="y">
                <option :value="String(y)" x-text="y"></option>
            </template>
        </select>

        <div class="flex-1"></div>

        {{-- Advanced filters toggle --}}
        <button @click="showFilters = !showFilters"
            :class="activeFilterCount > 0 ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'"
            class="flex items-center gap-1.5 relative">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span>Filters</span>
            <span x-show="activeFilterCount > 0"
                  x-text="activeFilterCount"
                  class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-500 text-white text-[10px] flex items-center justify-center font-bold leading-none"></span>
        </button>

        <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"
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
        {{-- Share button --}}
        <button @click="openShareModal()" class="btn-secondary btn-sm flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
            </svg>
            Share
        </button>
        <button @click="showAddColumn = true" class="btn-secondary btn-sm flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            Add column
        </button>
        <button @click="showAddWell = true" class="btn-primary btn-sm flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add well
        </button>
        @endif
    </div>

    {{-- ── Advanced filter panel ─────────────────────────────────────────────── --}}
    <div x-show="showFilters" x-cloak
         class="px-4 py-3 bg-brand-50 border-b border-brand-200 shrink-0">
        <div class="flex flex-wrap items-end gap-3">

            {{-- Earliest Start Date --}}
            <div class="flex items-center gap-1.5">
                <label class="text-2xs font-medium text-slate-600 whitespace-nowrap">Earliest Start ≥</label>
                <input x-model="filterDateFrom" @change="page=1; load()" type="date"
                       class="input-sm w-36" />
            </div>
            {{-- Earliest End Date --}}
            <div class="flex items-center gap-1.5">
                <label class="text-2xs font-medium text-slate-600 whitespace-nowrap">Earliest End ≤</label>
                <input x-model="filterDateTo" @change="page=1; load()" type="date"
                       class="input-sm w-36" />
            </div>

            {{-- Well ID --}}
            <div class="flex items-center gap-1.5">
                <label class="text-2xs font-medium text-slate-600 whitespace-nowrap">Well ID</label>
                <input x-model="filterWellId" @input="debouncedLoad()" type="text"
                       placeholder="e.g. W-123" class="input-sm w-28" />
            </div>

            {{-- Status (E-Pad) --}}
            <div class="flex items-center gap-1.5">
                <label class="text-2xs font-medium text-slate-600 whitespace-nowrap">Status</label>
                <select x-model="filterEpadStatus" @change="page=1; load()" class="input-sm w-28">
                    <option value="">All</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            {{-- SAP Status --}}
            <div class="flex items-center gap-1.5">
                <label class="text-2xs font-medium text-slate-600 whitespace-nowrap">SAP Status</label>
                <select x-model="filterSapStatus" @change="page=1; load()" class="input-sm w-36">
                    <option value="">All</option>
                    <option value="SAP PM Completed">SAP PM Completed</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Pending">Pending</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Not Started">Not Started</option>
                </select>
            </div>

            {{-- Delayed commissioning --}}
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input x-model="filterDelayed" @change="page=1; load()" type="checkbox"
                       class="w-3.5 h-3.5 rounded text-red-600" />
                <span class="text-2xs font-medium text-slate-700">Commission overdue (&gt;90 days)</span>
            </label>

            {{-- Reset --}}
            <button x-show="activeFilterCount > 0"
                    @click="resetFilters()"
                    class="btn-secondary btn-sm flex items-center gap-1 ml-auto text-red-600 border-red-200 hover:bg-red-50">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Clear filters
            </button>
        </div>
    </div>

    {{-- ── Source legend ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-5 px-4 py-1.5 bg-slate-50 border-b border-slate-200 text-2xs text-slate-500 shrink-0 overflow-x-auto whitespace-nowrap scrollbar-thin">
        @foreach([['project_wells','Fixed (Super Admin / Manager)','#1D9E75'],['sap_tracking','SAP Tracking','#BA7517'],['dc_tracking','DC Tracking','#D85A30'],['computed','Computed (auto)','#639922'],['custom','Custom','#7C3AED']] as [$key,$label,$color])
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
            <div class="flex items-center gap-2 text-slate-500 text-sm">
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
                    <th class="w-10 text-center bg-slate-50" style="border-top:2.5px solid transparent">
                        <input type="checkbox" class="rounded border-slate-300 text-brand-600"
                            :checked="allOnPageSelected()"
                            x-effect="$el.indeterminate = selectedIds.length > 0 && !allOnPageSelected()"
                            @change="selectAll()">
                    </th>
                    @endif
                    <template x-for="col in columns" :key="col.source_column">
                        <th :style="`border-top: 2.5px solid ${col.is_hidden ? '#d1d5db' : sourceColor(col.source_table)}`"
                            :class="col.is_hidden ? 'opacity-40 italic' : ''"
                            class="group/th relative">
                            <span x-text="col.display_label"></span>
                            @if(auth()->user()->isSuperAdmin())
                            {{-- Hide / show toggle --}}
                            <button @click.stop="toggleHideColumn(col)"
                                    :class="col.is_hidden ? 'opacity-100 text-brand-400' : 'opacity-0 group-hover/th:opacity-100 text-slate-400 hover:text-brand-500'"
                                    class="absolute top-0.5 left-0.5 transition-opacity p-0.5 rounded"
                                    :title="col.is_hidden ? 'Show column for all users' : 'Hide column from non-admins'">
                                {{-- eye-slash when hidden --}}
                                <template x-if="col.is_hidden">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </template>
                                {{-- eye when visible --}}
                                <template x-if="!col.is_hidden">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </template>
                            </button>
                            {{-- Delete button (custom columns only) --}}
                            <button x-show="col.source_table === 'custom'"
                                    @click.stop="deleteColumn(col)"
                                    class="absolute top-0.5 right-0.5 opacity-0 group-hover/th:opacity-100 transition-opacity text-red-400 hover:text-red-600 p-0.5 rounded"
                                    title="Delete this column">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                            @endif
                        </th>
                    </template>
                    <th class="w-8 bg-slate-50" style="border-top:2.5px solid transparent"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="well in wells" :key="well.id">
                    <tr :class="[activeWellId === well.id ? 'active-row' : '', isSelected(well.id) ? 'bg-brand-50' : '', well.previous_year ? 'ring-inset ring-1 ring-amber-300' : '']" class="group">

                        @if(auth()->user()->isSuperAdmin())
                        <td class="w-10 text-center" @click.stop>
                            <input type="checkbox" class="rounded border-slate-300 text-brand-600"
                                :checked="isSelected(well.id)"
                                @change="toggleSelect(well.id)">
                        </td>
                        @endif

                        <template x-for="col in columns" :key="col.source_column">
                            <td
                                :class="[
                                    (canEdit(col, '{{ auth()->user()->role }}', {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}) || canCommentOnly(col, '{{ auth()->user()->role }}'))
                                        ? 'cursor-pointer hover:bg-brand-50 group/cell'
                                        : 'text-slate-400',
                                    col.is_hidden ? 'opacity-30 select-none' : ''
                                ]"
                                @click="(canEdit(col, '{{ auth()->user()->role }}', {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}) || canCommentOnly(col, '{{ auth()->user()->role }}')) && openCell(well, col)"
                                @mouseenter="showCellTooltip($event, well, col)"
                                @mouseleave="hideCellTooltip()">

                                {{-- SAP KPI computed --}}
                                <template x-if="col.source_column === 'sap_kpi'">
                                    <span class="badge"
                                        :class="badgeClass(kpiStatus(well.computed?.sap_kpi))"
                                        x-text="well.computed?.sap_kpi != null ? well.computed.sap_kpi + 'd' : '—'"></span>
                                </template>

                                {{-- Countdown / Actual Countdown computed (days left to the 90-day target) --}}
                                <template x-if="['countdown_days', 'actual_countdown_days'].includes(col.source_column)">
                                    <span class="badge flex items-center gap-1"
                                        x-data="{ get st() { return well.computed?.[col.source_column.replace('_days', '_status')] }, get days() { return well.computed?.[col.source_column] } }"
                                        :class="badgeClass(st)"
                                        :title="st === 'overdue' ? 'Past the 90-day target — E-Pad not closed' : ''">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                            :class="{
                                                'bg-red-500':   st === 'overdue',
                                                'bg-amber-500': st === 'warning',
                                                'bg-green-500': ['ok','closed'].includes(st),
                                                'bg-slate-400':  !st || st === 'none',
                                            }"></span>
                                        <span x-text="days != null ? days + 'd' : '—'"></span>
                                    </span>
                                </template>

                                {{-- Regular cell --}}
                                <template x-if="!['sap_kpi', 'countdown_days', 'actual_countdown_days'].includes(col.source_column)">
                                    <span class="flex items-center gap-1">
                                        {{-- Hidden cell: super admin sees value with eye-slash + italic; others see nothing (stripped server-side) --}}
                                        @if(auth()->user()->isSuperAdmin())
                                        <template x-if="isCellHidden(well, col)">
                                            <span class="flex items-center gap-1 opacity-40 italic text-slate-500 select-none" title="Hidden from non-admins">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                                </svg>
                                                <span x-text="getCellValue(well, col) || '—'"></span>
                                            </span>
                                        </template>
                                        <template x-if="!isCellHidden(well, col)">
                                            <span x-text="getCellValue(well, col) || '—'"></span>
                                        </template>
                                        @else
                                        <span x-text="getCellValue(well, col) || '—'"></span>
                                        @endif
                                        {{-- Comment indicator --}}
                                        <svg x-show="getCellComment(well, col)"
                                            class="w-3 h-3 text-brand-400 shrink-0"
                                            fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z"/>
                                        </svg>
                                        {{-- Edit pencil --}}
                                        <svg x-show="canEdit(col, '{{ auth()->user()->role }}', {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}) || canCommentOnly(col, '{{ auth()->user()->role }}')"
                                            class="w-3 h-3 text-slate-300 opacity-0 group-hover/cell:opacity-100 transition-opacity shrink-0"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </span>
                                    {{-- Updated-by micro-text --}}
                                    <span x-show="getUpdatedBy(well, col)"
                                        class="block text-2xs text-slate-400 leading-none mt-0.5"
                                        x-text="getUpdatedBy(well, col)"></span>
                                </template>
                            </td>
                        </template>

                        {{-- Audit icon + shifted badge --}}
                        <td class="w-8">
                            <div class="flex items-center gap-1">
                                <button @click.stop="openAudit(well)"
                                    class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-brand-600 p-1">
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
                    <td :colspan="columns.length + {{ auth()->user()->isSuperAdmin() ? 2 : 1 }}" class="text-center py-12 text-slate-400">
                        No wells found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Pagination ───────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between px-4 py-2 border-t border-slate-200 bg-white text-xs text-slate-500 shrink-0">
        <span x-text="plural(pagination?.total || 0, 'well')"></span>
        <div class="flex gap-1">
            <template x-for="p in Array.from({length: pagination?.last_page || 1}, (_,i) => i+1)" :key="p">
                <button @click="page = p; load()"
                    class="w-7 h-7 rounded text-xs transition-colors"
                    :class="p === page ? 'bg-brand-600 text-white' : 'hover:bg-slate-100 text-slate-600'"
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
                        <td class="px-4 py-1.5 text-slate-700" x-text="w.well_id || '—'"></td>
                        <td class="px-4 py-1.5 text-slate-700" x-text="w.well_number || '—'"></td>
                        <td class="px-4 py-1.5 text-slate-500" x-text="w.well_type || '—'"></td>
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

    {{-- ── Add Column Modal ───────────────────────────────────────────────────── --}}
    @include('master.partials.add-column-modal')
    @include('master.partials.share-modal')

    {{-- ── Bulk Delete Action Bar ─────────────────────────────────────────────── --}}
    @if(auth()->user()->isSuperAdmin())
    <div x-show="selectedIds.length > 0"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-3 bg-slate-900 text-white px-5 py-3 rounded-xl shadow-2xl z-50 text-sm">
        <span x-text="selectedIds.length + ' well(s) selected'"></span>
        <button @click="clearSelection()" class="text-slate-400 hover:text-white px-2 transition-colors">Clear</button>
        <div class="h-4 border-l border-slate-600"></div>
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
         class="fixed z-9999 bg-slate-900 text-white text-xs rounded-lg px-3 py-2 max-w-xs shadow-xl pointer-events-none"
         style="display:none">
        <p class="leading-relaxed" x-text="cellTooltip.comment?.comment_text"></p>
        <p class="text-slate-400 mt-1" x-text="cellTooltip.comment?.author?.name ? '— ' + cellTooltip.comment.author.name : ''"></p>
    </div>

</div>
@endsection
