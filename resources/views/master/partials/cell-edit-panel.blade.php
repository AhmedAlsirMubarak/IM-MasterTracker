{{-- Rendered inside masterGrid x-data context --}}
<div x-data="cellEditPanel()"
     class="flex flex-col flex-1 min-h-0">

    {{-- Panel header --}}
    <div class="flex items-center gap-3 px-4 py-2.5 border-b border-slate-100">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-slate-900"
               x-text="editCell?.display_label + ' — Well ' + (editWell?.well_number || '')"></p>
            <p class="text-xs text-slate-400"
               x-text="{ project_wells:'Fixed columns', sap_tracking:'SAP Tracking', dc_tracking:'DC Tracking' }[editCell?.source_table] || ''"></p>
        </div>
        <span class="ml-auto text-xs font-semibold px-2 py-0.5 rounded"
            :style="`background:${sourceColor(editCell?.source_table)}22; color:${sourceColor(editCell?.source_table)}`"
            x-text="{ project_wells:'Super Admin', sap_tracking:'SAP Admin', dc_tracking:'Doc Controller' }[editCell?.source_table] || ''"></span>
        <button @click="closeCell()" class="btn-ghost btn-sm ml-1">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Panel body --}}
    <div class="grid grid-cols-2 gap-4 px-4 py-3 flex-1 overflow-y-auto min-h-0">

        {{-- Left: value picker (super_admin / role editors) OR read-only current value (manager) --}}
        <div>
            <label class="label" x-text="editCell?.display_label"></label>
            @if(auth()->user()->isManager())
            {{-- Manager: read-only value display, comment only --}}
            <p class="text-sm text-slate-700 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 min-h-9" x-text="getCurrent() || '—'"></p>
            <p class="text-xs text-amber-600 mt-1">View only — add a note below</p>
            @else

            {{-- Date input --}}
            <template x-if="DATE_COLS.includes(editCell?.source_column)">
                <input x-model="value" type="date" class="input text-sm" />
            </template>

            {{-- Status option buttons --}}
            <template x-if="!DATE_COLS.includes(editCell?.source_column) && options.length > 0">
                <div class="space-y-1 max-h-36 overflow-y-auto scrollbar-thin pr-1">
                    <template x-for="opt in options" :key="opt.option_value">
                        <button @click="value = opt.option_value"
                            class="flex items-center gap-2 w-full px-3 py-1.5 rounded-lg text-xs text-left border transition-colors"
                            :class="value === opt.option_value
                                ? 'bg-brand-50 border-brand-300 text-brand-800 font-medium'
                                : 'border-slate-200 hover:bg-slate-50 text-slate-700'">
                            <span class="w-2 h-2 rounded-full shrink-0"
                                  :style="`background:${opt.option_color}`"></span>
                            <span x-text="opt.option_label"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Well Type select --}}
            <template x-if="editCell?.source_column === 'well_type'">
                <div>
                    <select :value="wellTypeCustom ? '__custom__' : value"
                            @change="if ($event.target.value === '__custom__') { wellTypeCustom = true; value = '' } else { wellTypeCustom = false; value = $event.target.value }"
                            class="select text-sm">
                        <option value="">— Select —</option>
                        <option value="OP">OP</option>
                        <option value="OP FF">OP FF</option>
                        <option value="WI">WI</option>
                        <option value="__custom__">Other / type comment…</option>
                    </select>
                    <input x-show="wellTypeCustom"
                           x-model="value"
                           type="text"
                           class="input mt-1 text-sm"
                           placeholder="Type well type…" />
                </div>
            </template>

            {{-- Responsible Asset select --}}
            <template x-if="editCell?.source_column === 'responsible_asset'">
                <select x-model="value" class="select text-sm">
                    <option value="">— Select —</option>
                    <option value="North">North</option>
                    <option value="EXPLORATION">EXPLORATION</option>
                </select>
            </template>

            {{-- Field select --}}
            <template x-if="editCell?.source_column === 'well_field'">
                <select x-model="value" class="select text-sm">
                    <option value="">— Select —</option>
                    @foreach(config('wells.fields') as $f)
                    <option value="{{ $f }}">{{ $f }}</option>
                    @endforeach
                </select>
            </template>

            {{-- Station Code select --}}
            <template x-if="editCell?.source_column === 'station_code'">
                <select x-model="value" class="select text-sm">
                    <option value="">— Select —</option>
                    <option value="LEK">LEK</option>
                    <option value="USP">USP</option>
                </select>
            </template>

            {{-- Custom date column --}}
            <template x-if="editCell?.source_table === 'custom' && editCell?.column_type === 'date'">
                <input x-model="value" type="date" class="input text-sm" />
            </template>

            {{-- Custom number column --}}
            <template x-if="editCell?.source_table === 'custom' && editCell?.column_type === 'number'">
                <input x-model="value" type="number" class="input text-sm" />
            </template>

            {{-- Well Location select --}}
            <template x-if="editCell?.source_column === 'well_location'">
                <div>
                    <select :value="wellLocationCustom ? '__custom__' : value"
                            @change="if ($event.target.value === '__custom__') { wellLocationCustom = true; value = '' } else { wellLocationCustom = false; value = $event.target.value }"
                            class="select text-sm">
                        <option value="">— Select —</option>
                        <template x-for="loc in filteredLocations(editWell?.well_field)" :key="loc">
                            <option :value="loc" x-text="loc"></option>
                        </template>
                        @if(auth()->user()->isSuperAdmin())
                        <option value="__custom__">Other / type new…</option>
                        @endif
                    </select>
                    @if(auth()->user()->isSuperAdmin())
                    <input x-show="wellLocationCustom"
                           x-model="value"
                           type="text"
                           class="input mt-1 text-sm"
                           placeholder="Type well location…" />
                    @endif
                </div>
            </template>

            {{-- Free text --}}
            <template x-if="!DATE_COLS.includes(editCell?.source_column) && options.length === 0 && editCell?.source_column !== 'well_type' && editCell?.source_column !== 'responsible_asset' && editCell?.source_column !== 'well_field' && editCell?.source_column !== 'well_location' && editCell?.source_column !== 'station_code' && !(editCell?.source_table === 'custom' && (editCell?.column_type === 'date' || editCell?.column_type === 'number'))">
                <input x-model="value" type="text" class="input text-sm" />
            </template>

            <p class="text-xs text-slate-400 mt-1">
                Current: <span class="text-slate-600" x-text="getCurrent() || '—'"></span>
            </p>
            @endif

            {{-- Previous comments for this cell --}}
            <div x-show="comments.length > 0" class="mt-2">
                <p class="text-xs font-medium text-slate-400 mb-1">Previous notes</p>
                <div class="max-h-24 overflow-y-auto scrollbar-thin space-y-1">
                    <template x-for="c in comments" :key="c.id">
                        <div class="text-xs bg-brand-50 border border-brand-100 rounded px-2 py-1">
                            <p class="text-slate-800 leading-snug" x-text="c.comment_text"></p>
                            <p class="text-slate-400 mt-0.5">
                                <span x-text="c.author?.name"></span>
                                <span class="mx-0.5">·</span>
                                <span x-text="fmtDateTime(c.created_at)"></span>
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Right: comment input + recent history --}}
        <div class="flex flex-col gap-2 min-h-0">
            <div>
                <label class="label">Add note</label>
                <textarea x-model="comment" rows="2"
                    class="input text-xs resize-none"
                    placeholder="Add an optional note…"></textarea>
            </div>

            {{-- Audit history --}}
            <div class="flex-1 overflow-y-auto scrollbar-thin min-h-0">
                <p class="text-xs font-medium text-slate-400 mb-1">Recent changes</p>
                <template x-if="auditHistory.length === 0">
                    <p class="text-xs text-slate-300">No changes recorded yet.</p>
                </template>
                <template x-for="entry in auditHistory" :key="entry.id">
                    <div class="flex items-baseline gap-1 text-xs py-0.5 border-b border-slate-50">
                        <span class="font-medium text-slate-700" x-text="entry.user?.name"></span>
                        <span class="text-slate-400" x-text="fmtDateTime(entry.created_at)"></span>
                        <span class="text-slate-400 line-through" x-text="entry.old_value || '—'"></span>
                        <span class="text-slate-300 mx-0.5">→</span>
                        <span class="text-slate-800 font-medium" x-text="entry.new_value || '—'"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Panel footer --}}
    <div class="flex items-center gap-3 px-4 py-2 border-t border-slate-100">
        <button @click="save()" :disabled="saving" class="btn-primary btn-sm">
            <span x-text="saving ? 'Saving…' : '{{ auth()->user()->isManager() ? 'Add note' : 'Save update' }}'"></span>
        </button>
        <button @click="closeCell()" class="btn-secondary btn-sm">Cancel</button>

        @if(auth()->user()->isSuperAdmin())
        {{-- Hide / unhide this cell value --}}
        <button @click="toggleHideCell(editWell, editCell)"
                class="ml-auto flex items-center gap-1.5 btn-sm text-xs transition-colors"
                :class="isCellHidden(editWell, editCell)
                    ? 'text-amber-600 border border-amber-300 bg-amber-50 rounded px-2 py-1 hover:bg-amber-100'
                    : 'text-slate-400 hover:text-slate-600 border border-slate-200 rounded px-2 py-1 hover:bg-slate-50'"
                :title="isCellHidden(editWell, editCell) ? 'Unhide: make value visible to all users' : 'Hide this cell value from non-admins and public links'">
            <template x-if="isCellHidden(editWell, editCell)">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
            </template>
            <template x-if="!isCellHidden(editWell, editCell)">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
            </template>
            <span x-text="isCellHidden(editWell, editCell) ? 'Hidden' : 'Hide cell'"></span>
        </button>
        @endif

        <span x-show="saved" class="text-xs text-green-600 flex items-center gap-1 ml-1">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Saved — master view updated
        </span>
        <span x-show="error" class="text-xs text-red-600" x-text="error"></span>
    </div>
</div>
