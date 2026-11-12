{{-- Audit history drawer — rendered inside masterGrid x-data --}}
<div x-show="auditWell" class="flex flex-col h-full"
     x-data="{
         logs: [], loading: false,
         csrf() { return document.querySelector('meta[name=csrf-token]')?.content ?? '' },
         init() {
             this.$watch('auditWell', async (well) => {
                 if (!well) return
                 this.loading = true
                 try {
                     const res  = await fetch(`/wells/${well.id}/audit`, {
                         headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() }
                     })
                     const data = await res.json()
                     this.logs  = data.data || []
                 } catch { this.logs = [] }
                 this.loading = false
             })
         }
     }">

    <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-200">
        <h3 class="text-sm font-semibold text-slate-900">
            Audit log —
            <span x-text="auditWell?.well_number || ''"></span>
        </h3>
        <button @click="auditWell = null" class="ml-auto btn-ghost btn-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="flex-1 overflow-y-auto scrollbar-thin p-3">
        <div x-show="loading" class="text-center py-8 text-slate-400 text-sm">Loading…</div>

        <div x-show="!loading && logs.length === 0" class="text-center py-8 text-slate-400 text-sm">
            No changes recorded.
        </div>

        <template x-for="log in logs" :key="log.id">
            <div class="mb-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                <div class="flex items-center gap-2 mb-1.5">
                    <div class="w-6 h-6 rounded-full bg-brand-100 flex items-center justify-center
                                text-xs font-semibold text-brand-700 shrink-0"
                        x-text="(log.user?.name || '?').split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase()">
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="font-medium text-slate-900" x-text="log.user?.name"></span>
                        <span class="text-slate-400 ml-1 capitalize"
                            x-text="(log.user?.role || '').replace('_',' ')"></span>
                    </div>
                    <span class="text-slate-400 text-xs" :title="log.created_at_relative" x-text="fmtDateTime(log.created_at)"></span>
                </div>
                <div class="pl-8">
                    <span class="text-slate-500 font-medium" x-text="log.field_name"></span>
                    <div class="flex items-center gap-1 mt-0.5 text-xs">
                        <span class="text-slate-400 line-through" x-text="log.old_value || '—'"></span>
                        <svg class="w-3 h-3 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                        <span class="text-slate-800 font-medium" x-text="log.new_value || '—'"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
