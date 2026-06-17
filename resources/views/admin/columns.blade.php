@extends('layouts.app', ['project' => $project])
@section('title', 'Column Config — ' . $project->name)

@section('content')
<div class="flex flex-col h-full" x-data="columnConfig({{ $project->id }})">

    <div class="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0">
        <h2 class="text-sm font-semibold text-gray-900">Master view — column configurator</h2>
        <span class="badge badge-purple">Super Admin</span>
        <div class="flex-1"></div>
        <span class="text-xs text-gray-500">
            <span class="font-semibold text-gray-900" x-text="pinnedCount"></span> columns pinned
        </span>
    </div>

    <div class="flex flex-1 overflow-hidden">

        {{-- Source list --}}
        <div class="w-52 shrink-0 border-r border-gray-200 bg-white overflow-y-auto scrollbar-thin">
            <p class="section-label">Data sources</p>
            @foreach([
                ['project_wells', 'Fixed columns',  '#1D9E75', 'Super Admin / Manager'],
                ['sap_tracking',  'SAP Tracking',   '#BA7517', 'SAP Admin'],
                ['dc_tracking',   'DC Tracking',    '#D85A30', 'Doc Controller'],
                ['computed',      'Computed fields', '#639922', 'Auto-calculated'],
            ] as [$key, $label, $color, $owner])
            <button @click="activeSource = '{{ $key }}'"
                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left text-sm transition-colors border-b border-gray-100"
                :class="activeSource === '{{ $key }}' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $color }}"></span>
                <div class="min-w-0">
                    <span class="block font-medium text-sm">{{ $label }}</span>
                    <span class="block text-2xs text-gray-400">{{ $owner }}</span>
                </div>
                <span class="ml-auto text-2xs text-gray-400"
                    x-text="(sources['{{ $key }}'] || []).length + ' cols'"></span>
            </button>
            @endforeach
        </div>

        {{-- Column list --}}
        <div class="flex-1 flex flex-col overflow-hidden">
            <div class="flex items-center gap-3 px-4 py-2.5 border-b border-gray-200 bg-gray-50 shrink-0">
                <span class="text-xs font-medium text-gray-700"
                    x-text="{ project_wells:'Fixed columns', sap_tracking:'SAP Tracking', dc_tracking:'DC Tracking', computed:'Computed fields' }[activeSource]"></span>
                <div class="flex-1"></div>
                <input x-model="filter" class="input-sm w-44" placeholder="Search columns…" />
            </div>

            <div class="flex-1 overflow-y-auto scrollbar-thin">
                <template x-for="col in filteredCols" :key="col.column">
                    <div class="flex items-center gap-3 px-4 py-2.5 border-b border-gray-100 hover:bg-gray-50 group">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900" x-text="col.label"></p>
                            <p class="text-2xs text-gray-400" x-text="col.type"></p>
                        </div>

                        <span x-show="col.is_pinned && !col.locked"
                            class="text-2xs font-semibold text-green-700 bg-green-50 px-1.5 py-0.5 rounded">
                            In master view
                        </span>
                        <span x-show="col.locked" class="text-2xs text-gray-400">Always on</span>

                        {{-- Toggle --}}
                        <template x-if="!col.locked">
                            <button @click="toggle(col)"
                                class="toggle shrink-0"
                                :class="col.is_pinned ? 'toggle-on' : 'toggle-off'"
                                :title="col.is_pinned ? 'Remove from master view' : 'Add to master view'"
                                role="switch" :aria-checked="col.is_pinned">
                                <span class="toggle-knob" :class="col.is_pinned ? 'toggle-knob-on' : 'toggle-knob-off'"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <div x-show="filteredCols.length === 0" class="text-center py-10 text-gray-400 text-sm">
                    No columns match your search.
                </div>
            </div>
        </div>

        {{-- Preview panel --}}
        <div class="w-64 shrink-0 border-l border-gray-200 bg-gray-50 overflow-y-auto scrollbar-thin">
            <p class="section-label">Master view preview</p>
            <div class="px-3 pb-3">
                <template x-for="src in ['project_wells','sap_tracking','dc_tracking','computed']" :key="src">
                    <template x-for="col in (sources[src] || []).filter(c => c.is_pinned)" :key="col.column">
                        <div class="flex items-center gap-2 py-1.5 border-b border-gray-200 text-xs">
                            <span class="w-2 h-2 rounded-full shrink-0"
                                :style="`background:${sourceColor(src)}`"></span>
                            <span class="text-gray-700 flex-1 truncate" x-text="col.label"></span>
                            <span class="text-gray-400 text-2xs shrink-0" x-text="col.type"></span>
                        </div>
                    </template>
                </template>
            </div>
        </div>

    </div>
</div>
@endsection
