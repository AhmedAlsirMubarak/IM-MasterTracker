<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->name }} — Shared View</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex flex-col overflow-hidden bg-slate-100">

{{-- Read-only banner --}}
<div class="shrink-0 bg-amber-50 border-b border-amber-200 px-4 py-2 flex items-center gap-3 text-sm text-amber-800">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
    </svg>
    <span><strong>{{ $project->name }}</strong> — Read-only shared view.
        Expires {{ \Carbon\Carbon::parse($share->expires_at)->diffForHumans() }}.
    </span>
</div>

<div class="flex flex-col flex-1 min-h-0"
     x-data="sharedGrid('{{ $share->token }}')">

    {{-- Toolbar --}}
    <div class="page-header flex-wrap">
        <h2 class="page-title">IM-Master Track — {{ $project->name }}</h2>

        <select x-model="filterYear" @change="page=1; load()" class="input-sm w-24">
            <option value="all">All years</option>
            @foreach(range(date('Y') - 3, date('Y') + 1) as $y)
            <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>

        <div class="flex-1"></div>

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
    </div>

    {{-- Source legend --}}
    <div class="flex items-center gap-5 px-4 py-1.5 bg-slate-50 border-b border-slate-200 text-2xs text-slate-500 shrink-0 overflow-x-auto whitespace-nowrap scrollbar-thin">
        @foreach([['project_wells','Fixed','#1D9E75'],['sap_tracking','SAP Tracking','#BA7517'],['dc_tracking','DC Tracking','#D85A30'],['computed','Computed (auto)','#639922'],['custom','Custom','#7C3AED']] as [$key,$label,$color])
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-4 h-0.5 rounded" style="background:{{ $color }}"></span>
            {{ $label }}
        </span>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="flex-1 overflow-auto scrollbar-thin relative">
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
                    <template x-for="col in columns" :key="col.id">
                        <th :style="`border-top: 2.5px solid ${sourceColor(col.source_table)}`">
                            <span x-text="col.display_label"></span>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-for="well in wells" :key="well.id">
                    <tr class="group">
                        <template x-for="col in columns" :key="col.id">
                            <td class="text-slate-700">
                                <template x-if="col.source_column === 'sap_kpi'">
                                    <span class="badge" :class="badgeClass(kpiStatus(well.computed?.sap_kpi))"
                                          x-text="well.computed?.sap_kpi != null ? well.computed.sap_kpi + 'd' : '—'"></span>
                                </template>
                                <template x-if="col.source_column === 'countdown_days'">
                                    <span class="badge flex items-center gap-1" :class="badgeClass(well.computed?.countdown_status)">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                            :class="{
                                                'bg-red-500':   well.computed?.countdown_status === 'overdue',
                                                'bg-amber-500': well.computed?.countdown_status === 'warning',
                                                'bg-green-500': ['ok','closed'].includes(well.computed?.countdown_status),
                                                'bg-slate-400':  !well.computed?.countdown_status || well.computed?.countdown_status === 'none',
                                            }"></span>
                                        <span x-text="well.computed?.countdown_days != null ? well.computed.countdown_days + 'd' : '—'"></span>
                                    </span>
                                </template>
                                <template x-if="col.source_column !== 'sap_kpi' && col.source_column !== 'countdown_days'">
                                    <span x-text="getCellValue(well, col) || '—'"></span>
                                </template>
                            </td>
                        </template>
                    </tr>
                </template>
                <template x-if="!loading && wells.length === 0">
                    <tr><td :colspan="columns.length" class="text-center text-slate-400 py-8">No wells found.</td></tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex items-center gap-3 px-4 py-2 bg-white border-t border-slate-200 text-xs text-slate-500 shrink-0">
        <span x-text="`${pagination?.total ?? 0} wells`"></span>
        <div class="flex-1"></div>
        <button @click="page--; load()" :disabled="page <= 1" class="btn-secondary btn-sm">←</button>
        <span x-text="`Page ${page} of ${pagination?.last_page ?? 1}`"></span>
        <button @click="page++; load()" :disabled="!pagination || page >= pagination.last_page" class="btn-secondary btn-sm">→</button>
    </div>
</div>

</body>
</html>
