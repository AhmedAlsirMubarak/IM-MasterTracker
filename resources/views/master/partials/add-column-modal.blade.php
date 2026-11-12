@if(auth()->user()->isSuperAdmin())
<div x-show="showAddColumn" x-cloak class="modal-backdrop" @click.self="showAddColumn = false; addColError = ''">
    <div class="modal" style="max-width:480px" @click.stop>
        <div class="card-header">
            <h2 class="font-semibold text-slate-900">Add custom column</h2>
            <button @click="showAddColumn = false; addColError = ''" class="ml-auto btn-ghost btn-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="card-body">
            <div x-show="addColError" class="alert-danger mb-4" x-text="addColError"></div>

            <div class="space-y-3">
                {{-- Label --}}
                <div>
                    <label class="label">Column label <span class="text-red-500">*</span></label>
                    <input x-model="addColForm.label" type="text" class="input"
                           placeholder="e.g. Inspection Status" />
                </div>

                {{-- Type --}}
                <div>
                    <label class="label">Column type</label>
                    <select x-model="addColForm.column_type" class="select">
                        <option value="text">Text</option>
                        <option value="date">Date</option>
                        <option value="number">Number</option>
                        <option value="select">Select (dropdown)</option>
                    </select>

                    {{-- Date hint --}}
                    <p x-show="addColForm.column_type === 'date'"
                       class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Cells will show a calendar date picker.
                    </p>
                </div>

                {{-- Select options builder --}}
                <div x-show="addColForm.column_type === 'select'">
                    <label class="label">Dropdown options <span class="text-red-500">*</span></label>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto scrollbar-thin pr-1">
                        <template x-for="(opt, idx) in addColForm.selectOptions" :key="idx">
                            <div class="flex items-center gap-2">
                                <input :value="opt.label"
                                       @input="addColForm.selectOptions[idx].label = $event.target.value"
                                       type="text"
                                       class="input text-sm flex-1"
                                       placeholder="Option label…" />
                                <input :value="opt.color"
                                       @input="addColForm.selectOptions[idx].color = $event.target.value"
                                       type="color"
                                       class="w-8 h-8 rounded border border-slate-300 cursor-pointer p-0.5 shrink-0"
                                       title="Pick a colour" />
                                <button @click="addColForm.selectOptions.splice(idx, 1)"
                                        x-show="addColForm.selectOptions.length > 1"
                                        class="text-slate-400 hover:text-red-500 shrink-0 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    <button @click="addColForm.selectOptions.push({ label: '', color: '#888780' })"
                            class="mt-2 text-xs text-brand-600 hover:text-brand-800 flex items-center gap-1 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add option
                    </button>
                </div>

                {{-- Insert after --}}
                <div>
                    <label class="label">Insert after</label>
                    <select x-model="addColForm.after_config_id" class="select">
                        <option value="">— At the end —</option>
                        <template x-for="col in columns" :key="col.id">
                            <option :value="col.id" x-text="col.display_label"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-5 pt-3 border-t border-slate-100">
                <button @click="showAddColumn = false; addColError = ''" class="btn-secondary">Cancel</button>
                <button @click="addColumn()"
                        :disabled="addColSaving || !addColForm.label.trim() || (addColForm.column_type === 'select' && addColForm.selectOptions.every(o => !o.label.trim()))"
                        class="btn-primary">
                    <span x-text="addColSaving ? 'Creating…' : 'Create column'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
