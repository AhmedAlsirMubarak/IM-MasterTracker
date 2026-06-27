@if(auth()->user()->isSuperAdmin())
<div x-show="showAddWell" x-cloak class="modal-backdrop" @click.self="showAddWell = false"
     x-data="{
        form: { contract_no:'', scope:'', hua_date:'', well_id:'', well_number:'', well_type:'', well_field:'', well_location:'', ramz_id:'', year: {{ date('Y') }}, responsible_asset:'' },
        wellTypeCustom: false,
        wellLocationCustom: false,
        saving: false, error: '',
        csrf() { return document.querySelector('meta[name=csrf-token]')?.content ?? '' },
        async save() {
            this.saving = true; this.error = ''
            try {
                const res = await fetch(`/projects/{{ $project->id }}/wells`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify(this.form)
                })
                const data = await res.json()
                if (!res.ok) { this.error = data.message || 'Failed to add well.' }
                else { await this.load(); this.showAddWell = false }
            } catch { this.error = 'Network error.' }
            this.saving = false
        }
     }">
    <div class="modal" @click.stop>
        <div class="card-header">
            <h2 class="font-semibold text-gray-900">Add well</h2>
            <button @click="showAddWell = false" class="ml-auto btn-ghost btn-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="card-body">
            <div x-show="error" class="alert-danger mb-4" x-text="error"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Contract NO</label>
                    <input x-model="form.contract_no" class="input" placeholder="C3100000180" /></div>
                <div><label class="label">Scope</label>
                    <input x-model="form.scope" class="input" placeholder="ODC - LEK" /></div>
                <div><label class="label">Well ID <span class="text-red-500">*</span></label>
                    <input x-model="form.well_id" class="input" placeholder="36174" /></div>
                <div><label class="label">Well Number</label>
                    <input x-model="form.well_number" class="input" placeholder="L-1187" /></div>
                <div><label class="label">Field</label>
                    <select x-model="form.well_field" @change="form.well_location = ''" class="select">
                        <option value="">— Select —</option>
                        @foreach(['AFAR','AL HUSAIN EAST','DAFIQ','DHRUMA','DHULAIMA','DHULAIMA WEST','LEKHWAIR','LEKHWAIR AN','LEKHWAIR AS','LEKHWAIR EAST','LEKHWAIR INTRA','LEKHWAIR - AN','LEKHWAIR - AS','LEKHWAIR - CD','MALAAN','MALAAN WEST','SAYYAH','SAYYAH - NORTH','UFUQ','UFUQ - ARUMA'] as $f)
                        <option value="{{ $f }}">{{ $f }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Well Location</label>
                    <select :value="wellLocationCustom ? '__custom__' : form.well_location"
                            @change="if ($event.target.value === '__custom__') { wellLocationCustom = true; form.well_location = '' } else { wellLocationCustom = false; form.well_location = $event.target.value }"
                            class="select">
                        <option value="">— Select —</option>
                        <template x-for="loc in (window.FIELD_LOCATIONS[form.well_field] || window.ALL_LOCATIONS)" :key="loc">
                            <option :value="loc" x-text="loc"></option>
                        </template>
                        <option value="__custom__">Other / type new…</option>
                    </select>
                    <input x-show="wellLocationCustom"
                           x-model="form.well_location"
                           type="text"
                           class="input mt-1 text-sm"
                           placeholder="Type well location…" />
                </div>
                <div><label class="label">Well Type</label>
                    <select :value="wellTypeCustom ? '__custom__' : form.well_type"
                            @change="if ($event.target.value === '__custom__') { wellTypeCustom = true; form.well_type = '' } else { wellTypeCustom = false; form.well_type = $event.target.value }"
                            class="select">
                        <option value="">— Select —</option>
                        <option value="OP">OP</option>
                        <option value="OP FF">OP FF</option>
                        <option value="WI">WI</option>
                        <option value="__custom__">Other / type comment…</option>
                    </select>
                    <input x-show="wellTypeCustom"
                           x-model="form.well_type"
                           type="text"
                           class="input mt-1 text-sm"
                           placeholder="Type well type…" />
                </div>
                <div><label class="label">HUA Date</label>
                    <input x-model="form.hua_date" type="date" class="input" /></div>
                <div><label class="label">Ramz ID</label>
                    <input x-model="form.ramz_id" class="input" placeholder="NOL0000L2342-EP" /></div>
                <div><label class="label">Year</label>
                    <input x-model="form.year" type="number" class="input" /></div>
                <div class="col-span-2"><label class="label">Responsible Asset</label>
                    <select x-model="form.responsible_asset" class="select">
                        <option value="">— Select —</option>
                        <option value="North">North</option>
                        <option value="EXPLORATION">EXPLORATION</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-100">
                <button @click="showAddWell = false" class="btn-secondary">Cancel</button>
                <button @click="save()" :disabled="saving" class="btn-primary">
                    <span x-text="saving ? 'Adding…' : 'Add well'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
