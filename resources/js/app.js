import Alpine from 'alpinejs'
import focus   from '@alpinejs/focus'
import persist from '@alpinejs/persist'

Alpine.plugin(focus)
Alpine.plugin(persist)

// ── Field → Well Location mapping ────────────────────────────────────────────
const FIELD_LOCATIONS = {}

const ALL_LOCATIONS = [
    'LEKHWAIR-UM2',
    'LEKHWAIR-UM',
    'RASHEEQ D3',
    'RASHEEQD4',
    'RASHEEQ D5',
]

window.FIELD_LOCATIONS = FIELD_LOCATIONS
window.ALL_LOCATIONS   = ALL_LOCATIONS

// ── Global helpers ────────────────────────────────────────────────────────────

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

const jsonHeaders = () => ({
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrf(),
})

const getHeaders = () => ({
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrf(),
})

window.sourceColor = src => ({
    project_wells: '#1D9E75',
    sap_tracking:  '#BA7517',
    dc_tracking:   '#D85A30',
    computed:      '#639922',
    custom:        '#7C3AED',
}[src] || '#888780')

window.kpiStatus = days => {
    if (days == null) return 'none'
    if (days <= 30) return 'green'
    if (days <= 60) return 'amber'
    return 'red'
}

window.countdownStatus = (days, ePad) => {
    if (days == null) return 'none'
    if ((ePad || '').toLowerCase() === 'closed') return 'closed'
    if (days > 90) return 'overdue'
    if (days > 75) return 'warning'
    return 'ok'
}

window.badgeClass = status => ({
    green:   'badge-green',
    amber:   'badge-amber',
    red:     'badge-red',
    overdue: 'badge-red',
    warning: 'badge-amber',
    ok:      'badge-green',
    closed:  'badge-green',
    none:    'badge-gray',
}[status] || 'badge-gray')

window.formatDate = d => d
    ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    : '—'

window.debounce = (fn, ms = 300) => {
    let t
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms) }
}

// ── Alpine components ─────────────────────────────────────────────────────────

Alpine.data('projectList', () => ({
    projects:   [],
    loading:    false,
    showCreate: false,
    saving:     false,
    error:      '',
    form: { name: '', contract_no: '', description: '' },

    async init() {
        this.loading = true
        try {
            const res     = await fetch('/projects/list', { headers: getHeaders() })
            this.projects = await res.json()
        } catch {}
        this.loading = false
    },

    async create() {
        this.saving = true
        this.error  = ''
        try {
            const res  = await fetch('/projects', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(this.form) })
            const data = await res.json()
            if (!res.ok) {
                this.error = (data.errors ? Object.values(data.errors)[0]?.[0] : null) || data.message || 'Error.'
            } else {
                this.projects.unshift(data)
                this.showCreate = false
                this.form = { name: '', contract_no: '', description: '' }
            }
        } catch { this.error = 'Network error.' }
        this.saving = false
    },

    async deleteProject(project) {
        if (!confirm(`Delete "${project.name}"? This will permanently remove the project and all its wells.`)) return
        const res = await fetch(`/projects/${project.id}`, { method: 'DELETE', headers: jsonHeaders() })
        if (res.ok) {
            this.projects = this.projects.filter(p => p.id !== project.id)
        }
    },
}))

Alpine.data('userManager', () => ({
    users:    [],
    loading:  false,
    showForm: false,
    saving:   false,
    error:    '',
    form: { id: null, name: '', email: '', password: '', role: 'im_lead', department: '', is_active: true },

    async init() {
        this.loading = true
        try {
            const res   = await fetch('/users/list', { headers: getHeaders() })
            this.users  = await res.json()
        } catch {}
        this.loading = false
    },

    openCreate() {
        this.form = { id: null, name: '', email: '', password: '', role: 'im_lead', department: '', is_active: true }
        this.error = ''
        this.showForm = true
    },

    openEdit(u) {
        this.form = { ...u, password: '' }
        this.error = ''
        this.showForm = true
    },

    async save() {
        this.saving = true
        this.error  = ''
        const isEdit = !!this.form.id
        const url    = isEdit ? `/users/${this.form.id}` : '/users'
        const body   = { ...this.form }
        if (isEdit && !body.password) delete body.password
        try {
            const res  = await fetch(url, { method: isEdit ? 'PUT' : 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
            const data = await res.json()
            if (!res.ok) {
                this.error = (data.errors ? Object.values(data.errors)[0]?.[0] : null) || data.message || 'Error.'
            } else if (isEdit) {
                const idx = this.users.findIndex(u => u.id === data.id)
                if (idx !== -1) this.users[idx] = data; else this.users.push(data)
                this.showForm = false
            } else {
                this.users.push(data)
                this.showForm = false
            }
        } catch { this.error = 'Network error.' }
        this.saving = false
    },

    async deleteUser(id) {
        if (!confirm('Delete this user? This cannot be undone.')) return
        const res = await fetch(`/users/${id}`, { method: 'DELETE', headers: jsonHeaders() })
        if (res.ok) {
            this.users = this.users.filter(u => u.id !== id)
        } else {
            const data = await res.json().catch(() => ({}))
            alert(data.message || 'Error deleting user.')
        }
    },
}))

Alpine.data('cellEditPanel', () => ({
    value:    '',
    comment:  '',
    comments: [],
    options:  [],
    auditHistory: [],
    saving: false,
    saved:  false,
    error:  '',
    wellTypeCustom: false,
    wellLocationCustom: false,
    WELL_TYPE_PRESETS: ['OP', 'OP FF', 'WI'],
    DATE_COLS: ['hua_date','earliest_start_date','earliest_end_date','hook_up_tie_in_completed',
                'commissioning_date','z6_creation_date','z6_awap_date','z6_appr_date',
                'sap_upload_date','bom_linking_date','afc_submitted_date','final_afc_upload_date',
                'final_rlmu_upload_date','asb_receive_date','asb_document_submission_date'],

    csrf() { return document.querySelector('meta[name=csrf-token]')?.content ?? '' },

    filteredLocations(field) {
        return (field && FIELD_LOCATIONS[field]) ? FIELD_LOCATIONS[field] : ALL_LOCATIONS
    },

    init() {
        this.$watch('editCell', async (col) => {
            if (!col) return
            this.value   = this.getCurrent()
            this.comment = ''
            this.error   = ''
            this.saved   = false
            this.comments = []
            this.wellTypeCustom = col.source_column === 'well_type'
                && !!this.value && !this.WELL_TYPE_PRESETS.includes(this.value)
            this.wellLocationCustom = col.source_column === 'well_location'
                && !!this.value && !ALL_LOCATIONS.includes(this.value)
            await Promise.all([this.loadOptions(col), this.loadHistory(col), this.loadComments(col)])
        })
    },

    getCurrent() {
        const col  = this.editCell
        const well = this.editWell
        if (!col || !well) return ''
        if (col.source_table === 'sap_tracking') return well.sap_tracking?.[col.source_column] ?? ''
        if (col.source_table === 'dc_tracking')  return well.dc_tracking?.[col.source_column]  ?? ''
        if (col.source_table === 'custom')       return well.custom?.[col.source_column] ?? ''
        return well[col.source_column] ?? ''
    },

    async loadOptions(col) {
        try {
            const res = await fetch(`/columns/${col.source_table}/${col.source_column}/options`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() }
            })
            this.options = await res.json()
        } catch { this.options = [] }
    },

    async loadHistory(col) {
        const well = this.editWell
        if (!well) return
        try {
            const res  = await fetch(`/wells/${well.id}/audit?field=${encodeURIComponent(col.source_column)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() }
            })
            const data = await res.json()
            this.auditHistory = (data.data || []).slice(0, 5)
        } catch { this.auditHistory = [] }
    },

    async loadComments(col) {
        const well = this.editWell
        if (!well) return
        try {
            const url = `/wells/${well.id}/comments?source_table=${encodeURIComponent(col.source_table)}&source_column=${encodeURIComponent(col.source_column)}`
            const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() } })
            this.comments = await res.json()
        } catch { this.comments = [] }
    },

    async save() {
        const col  = this.editCell
        const well = this.editWell
        if (!col || !well) return
        this.saving = true; this.error = ''
        try {
            const res  = await fetch(`/wells/${well.id}/cell`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                body: JSON.stringify({
                    source_table:  col.source_table,
                    source_column: col.source_column,
                    value:         this.value   || null,
                    comment:       this.comment || null,
                })
            })
            const data = await res.json()
            if (!res.ok) {
                this.error = data.message || 'Save failed.'
            } else {
                this.saved = true
                setTimeout(() => { this.saved = false }, 2500)
                this.onCellSaved(data.well)
            }
        } catch { this.error = 'Network error.' }
        this.saving = false
    },
}))

Alpine.data('auditLog', (dataUrl) => ({
    logs:    [],
    loading: false,

    async init() {
        this.loading = true
        try {
            const res  = await fetch(dataUrl, { headers: getHeaders() })
            const data = await res.json()
            this.logs  = data.data ?? data ?? []
        } catch {}
        this.loading = false
    },
}))

Alpine.data('masterGrid', (projectId, canBulkDelete = false) => ({
    wells:          [],
    columns:        [],
    loading:        false,
    editCell:       null,
    editWell:       null,
    auditWell:      null,
    showAddWell:    false,
    showAddColumn:  false,
    addColForm:     { label: '', column_type: 'text', after_config_id: '', selectOptions: [{ label: '', color: '#888780' }] },
    addColSaving:   false,
    addColError:    '',
    search:         '',
    filterType:     '',
    filterYear:     String(new Date().getFullYear()),
    shiftedOnly:    false,
    shiftedCount:   0,
    showDeparted:   false,
    departedWells:  [],
    departedCount:  0,
    loadingDeparted: false,
    availableYears: [],
    yearsReady:     false,
    page:           1,
    pagination:     null,
    activeWellId:   null,
    debouncedLoad:  null,
    selectedIds:    [],
    canBulkDelete,
    cellTooltip:    { visible: false, comment: null, x: 0, y: 0 },

    init() {
        this.debouncedLoad = window.debounce(() => { this.page = 1; this.load() }, 400)
        this.loadYears()
        this.load()
    },

    async loadYears() {
        try {
            const res   = await fetch(`/projects/${projectId}/wells/years`, { headers: getHeaders() })
            const years = await res.json()
            // Ensure current year is always present so the default selection resolves
            const currentYear = new Date().getFullYear()
            const asStrings   = years.map(String)
            if (!asStrings.includes(String(currentYear))) {
                this.availableYears = [currentYear, ...years]
            } else {
                this.availableYears = years
            }
        } catch {}
        // Mark ready AFTER options are populated — the @change guard uses this
        this.$nextTick(() => { this.yearsReady = true })
    },

    async load() {
        this.loading = true
        const p = new URLSearchParams({ page: this.page })
        if (this.filterYear)  p.set('year',         this.filterYear)
        if (this.shiftedOnly) p.set('shifted_only', '1')
        if (this.search)      p.set('search',       this.search)
        if (this.filterType)  p.set('well_type',    this.filterType)
        try {
            const res  = await fetch(`/projects/${projectId}/wells?${p}`, { headers: getHeaders() })
            const data = await res.json()
            this.wells      = data.wells?.data ?? []
            this.columns    = data.columns     ?? []
            this.pagination = data.wells       ?? null
        } catch {}
        this.loading = false
        // One cheap request for both badge counts
        this.loadCounts()
        // Reload departed list if panel is already open
        if (this.showDeparted) this.loadDepartedWells()
    },

    async loadCounts() {
        try {
            const p = new URLSearchParams()
            if (this.filterYear) p.set('year', this.filterYear)
            const res  = await fetch(`/projects/${projectId}/wells/counts?${p}`, { headers: getHeaders() })
            const data = await res.json()
            this.shiftedCount  = data.shifted  ?? 0
            this.departedCount = data.departed ?? 0
        } catch {}
    },

    async loadDepartedWells() {
        if (this.filterYear === 'all') { this.departedWells = []; this.departedCount = 0; return }
        this.loadingDeparted = true
        try {
            const res  = await fetch(`/projects/${projectId}/wells/departed?year=${this.filterYear}`, { headers: getHeaders() })
            const rows = await res.json()
            this.departedWells  = Array.isArray(rows) ? rows : []
            this.departedCount  = this.departedWells.length
        } catch {}
        this.loadingDeparted = false
    },

    toggleShiftedOnly() {
        this.shiftedOnly = !this.shiftedOnly
        this.page = 1
        this.load()
    },

    async toggleDeparted() {
        this.showDeparted = !this.showDeparted
        if (this.showDeparted) this.loadDepartedWells()
    },

    openCell(well, col) {
        this.editWell    = well
        this.editCell    = col
        this.activeWellId = well.id
    },

    closeCell() { this.editCell = null; this.editWell = null },

    openAudit(well) { this.auditWell = well },

    onCellSaved(updatedWell) {
        // Capture before closeCell() clears editCell
        const savedYearCell = this.editCell?.source_column === 'year'
        const idx = this.wells.findIndex(w => w.id === updatedWell.id)
        if (idx !== -1) this.wells[idx] = updatedWell
        this.closeCell()

        if (savedYearCell) {
            // Refresh the year dropdown so the new year is immediately selectable,
            // then reload the grid so the well moves out of the current year view if needed.
            this.loadYears()
            this.load()
        }
    },

    canEdit(col, role, isSuperAdmin) {
        if (col.source_table === 'computed')       return false
        if (col.source_table === 'custom')         return isSuperAdmin
        if (col.source_table === 'project_wells')  return isSuperAdmin || role === 'manager'
        if (col.source_table === 'sap_tracking')   return isSuperAdmin || role === 'sap_admin'
        if (col.source_table === 'dc_tracking')    return isSuperAdmin || role === 'dc'
        return false
    },

    getCellValue(well, col) {
        let val
        if (col.source_table === 'sap_tracking')   val = well.sap_tracking?.[col.source_column]
        else if (col.source_table === 'dc_tracking') val = well.dc_tracking?.[col.source_column]
        else if (col.source_table === 'computed')    val = well.computed?.[col.source_column]
        else if (col.source_table === 'custom')      val = well.custom?.[col.source_column]
        else val = well[col.source_column]
        if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) return val.slice(0, 10)
        return val
    },

    async addColumn() {
        this.addColSaving = true; this.addColError = ''
        try {
            const res = await fetch(`/projects/${projectId}/custom-columns`, {
                method: 'POST', headers: jsonHeaders(),
                body: JSON.stringify({
                    label:           this.addColForm.label,
                    column_type:     this.addColForm.column_type,
                    after_config_id: this.addColForm.after_config_id || null,
                }),
            })
            const data = await res.json()
            if (!res.ok) { this.addColError = data.message || 'Failed to create column.'; return }

            // If select, persist options immediately
            if (this.addColForm.column_type === 'select') {
                const valid = this.addColForm.selectOptions.filter(o => o.label.trim())
                if (valid.length) {
                    await fetch(`/columns/custom/${data.source_column}/options`, {
                        method: 'POST', headers: jsonHeaders(),
                        body: JSON.stringify({
                            options: valid.map((o, i) => ({
                                option_value: o.label.trim(),
                                option_label: o.label.trim(),
                                option_color: o.color || '#888780',
                                sort_order:   i,
                            })),
                        }),
                    })
                }
            }

            this.showAddColumn = false
            this.addColForm = { label: '', column_type: 'text', after_config_id: '', selectOptions: [{ label: '', color: '#888780' }] }
            this.load()
        } catch { this.addColError = 'Network error.' }
        this.addColSaving = false
    },

    async deleteColumn(col) {
        if (!confirm(`Delete column "${col.display_label}"?\n\nAll stored values will be permanently removed.`)) return
        const res = await fetch(`/projects/${projectId}/custom-columns/${col.id}`, {
            method: 'DELETE', headers: jsonHeaders(),
        })
        if (res.ok) this.load()
    },

    getUpdatedBy(well, col) {
        if (col.source_table === 'sap_tracking') return well.sap_tracking?.updated_by?.name ?? null
        if (col.source_table === 'dc_tracking')  return well.dc_tracking?.updated_by?.name  ?? null
        return null
    },

    getCellComment(well, col) {
        return well.cell_comments?.[`${col.source_table}.${col.source_column}`] ?? null
    },

    showCellTooltip(event, well, col) {
        const comment = this.getCellComment(well, col)
        if (!comment) return
        const rect = event.currentTarget.getBoundingClientRect()
        this.cellTooltip = { visible: true, comment, x: rect.left, y: rect.bottom + 6 }
    },

    hideCellTooltip() { this.cellTooltip.visible = false },

    toggleSelect(id) {
        const idx = this.selectedIds.indexOf(id)
        if (idx === -1) this.selectedIds.push(id)
        else this.selectedIds.splice(idx, 1)
    },
    isSelected(id)        { return this.selectedIds.includes(id) },
    allOnPageSelected()   { return this.wells.length > 0 && this.wells.every(w => this.selectedIds.includes(w.id)) },
    selectAll() {
        const ids = this.wells.map(w => w.id)
        if (ids.every(id => this.selectedIds.includes(id)))
            this.selectedIds = this.selectedIds.filter(id => !ids.includes(id))
        else
            this.selectedIds = [...new Set([...this.selectedIds, ...ids])]
    },
    clearSelection() { this.selectedIds = [] },
    async deleteSelected() {
        if (!confirm(`Delete ${this.selectedIds.length} well(s)? This cannot be undone.`)) return
        const res = await fetch(`/projects/${projectId}/wells/bulk`, {
            method: 'DELETE', headers: jsonHeaders(),
            body: JSON.stringify({ ids: this.selectedIds }),
        })
        if (res.ok) { this.selectedIds = []; this.page = 1; this.load() }
    },
}))

Alpine.data('sapGrid', (projectId, canBulkDelete = false) => ({
    wells:        [],
    loading:      false,
    search:       '',
    page:         1,
    pagination:   null,
    editWell:     null,
    saving:       false,
    saveError:    '',
    customFields: {},
    selectedIds:  [],
    canBulkDelete,

    fmtDate(val) {
        if (!val) return '—'
        if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) return val.slice(0, 10)
        return val
    },

    STATUS_PRESETS: ['SAP PM Completed','In Progress','Pending','On Hold','Not Started','N/A'],

    isCustom(field) {
        return !!this.customFields[field]
    },
    selectStatus(field, value) {
        if (value === '__custom__') {
            this.customFields[field] = true
            this.editWell.sap_tracking[field] = ''
        } else {
            this.customFields[field] = false
            this.editWell.sap_tracking[field] = value
        }
    },

    init() { this.load() },

    async load() {
        this.loading = true
        const p = new URLSearchParams({ page: this.page })
        if (this.search) p.set('search', this.search)
        try {
            const res  = await fetch(`/projects/${projectId}/sap-data?${p}`, { headers: getHeaders() })
            const data = await res.json()
            this.wells      = data.data ?? []
            this.pagination = data      ?? null
        } catch {}
        this.loading = false
    },

    openEdit(well) {
        this.editWell = JSON.parse(JSON.stringify(well))
        if (!this.editWell.sap_tracking) this.editWell.sap_tracking = {}
        this.saveError = ''
        this.customFields = {}
        for (const [k, v] of Object.entries(this.editWell.sap_tracking)) {
            if (v && !this.STATUS_PRESETS.includes(v)) this.customFields[k] = true
        }
    },

    closeEdit() { this.editWell = null },

    toggleSelect(id) {
        const idx = this.selectedIds.indexOf(id)
        if (idx === -1) this.selectedIds.push(id)
        else this.selectedIds.splice(idx, 1)
    },
    isSelected(id)        { return this.selectedIds.includes(id) },
    allOnPageSelected()   { return this.wells.length > 0 && this.wells.every(w => this.selectedIds.includes(w.id)) },
    selectAll() {
        const ids = this.wells.map(w => w.id)
        if (ids.every(id => this.selectedIds.includes(id)))
            this.selectedIds = this.selectedIds.filter(id => !ids.includes(id))
        else
            this.selectedIds = [...new Set([...this.selectedIds, ...ids])]
    },
    clearSelection() { this.selectedIds = [] },
    async deleteSelected() {
        if (!confirm(`Delete ${this.selectedIds.length} well(s)? This cannot be undone.`)) return
        const res = await fetch(`/projects/${projectId}/wells/bulk`, {
            method: 'DELETE', headers: jsonHeaders(),
            body: JSON.stringify({ ids: this.selectedIds }),
        })
        if (res.ok) { this.selectedIds = []; this.page = 1; this.load() }
    },

    async save() {
        this.saving    = true
        this.saveError = ''
        try {
            const res  = await fetch(`/wells/${this.editWell.id}/sap`, {
                method: 'PUT', headers: jsonHeaders(),
                body: JSON.stringify(this.editWell.sap_tracking),
            })
            const data = await res.json()
            if (!res.ok) { this.saveError = data.message || 'Error saving.'; this.saving = false; return }
            const idx = this.wells.findIndex(w => w.id === this.editWell.id)
            if (idx !== -1) {
                this.wells[idx].sap_tracking = data.sap_tracking
                if (data.computed) this.wells[idx].computed = data.computed
            }
            this.editWell = null
        } catch { this.saveError = 'Network error.' }
        this.saving = false
    },
}))

Alpine.data('dcGrid', (projectId, canBulkDelete = false) => ({
    wells:       [],
    loading:     false,
    search:      '',
    page:        1,
    pagination:  null,
    editWell:    null,
    saving:      false,
    saveError:   '',
    selectedIds: [],
    canBulkDelete,

    fmtDate(val) {
        if (!val) return '—'
        if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) return val.slice(0, 10)
        return val
    },

    init() { this.load() },

    async load() {
        this.loading = true
        const p = new URLSearchParams({ page: this.page })
        if (this.search) p.set('search', this.search)
        try {
            const res  = await fetch(`/projects/${projectId}/dc-data?${p}`, { headers: getHeaders() })
            const data = await res.json()
            this.wells      = data.data ?? []
            this.pagination = data      ?? null
        } catch {}
        this.loading = false
    },

    openEdit(well) {
        this.editWell = JSON.parse(JSON.stringify(well))
        if (!this.editWell.dc_tracking) this.editWell.dc_tracking = {}
        this.saveError = ''
    },

    closeEdit() { this.editWell = null },

    toggleSelect(id) {
        const idx = this.selectedIds.indexOf(id)
        if (idx === -1) this.selectedIds.push(id)
        else this.selectedIds.splice(idx, 1)
    },
    isSelected(id)        { return this.selectedIds.includes(id) },
    allOnPageSelected()   { return this.wells.length > 0 && this.wells.every(w => this.selectedIds.includes(w.id)) },
    selectAll() {
        const ids = this.wells.map(w => w.id)
        if (ids.every(id => this.selectedIds.includes(id)))
            this.selectedIds = this.selectedIds.filter(id => !ids.includes(id))
        else
            this.selectedIds = [...new Set([...this.selectedIds, ...ids])]
    },
    clearSelection() { this.selectedIds = [] },
    async deleteSelected() {
        if (!confirm(`Delete ${this.selectedIds.length} well(s)? This cannot be undone.`)) return
        const res = await fetch(`/projects/${projectId}/wells/bulk`, {
            method: 'DELETE', headers: jsonHeaders(),
            body: JSON.stringify({ ids: this.selectedIds }),
        })
        if (res.ok) { this.selectedIds = []; this.page = 1; this.load() }
    },

    async save() {
        this.saving    = true
        this.saveError = ''
        try {
            const res  = await fetch(`/wells/${this.editWell.id}/dc`, {
                method: 'PUT', headers: jsonHeaders(),
                body: JSON.stringify(this.editWell.dc_tracking),
            })
            const data = await res.json()
            if (!res.ok) { this.saveError = data.message || 'Error saving.'; this.saving = false; return }
            const idx = this.wells.findIndex(w => w.id === this.editWell.id)
            if (idx !== -1) this.wells[idx].dc_tracking = data
            this.editWell = null
        } catch { this.saveError = 'Network error.' }
        this.saving = false
    },
}))

Alpine.data('columnConfig', (projectId) => ({
    sources:      {},
    activeSource: 'project_wells',
    filter:       '',
    loading:      false,

    get filteredCols() {
        const cols = this.sources[this.activeSource] || []
        if (!this.filter) return cols
        const q = this.filter.toLowerCase()
        return cols.filter(c => c.label.toLowerCase().includes(q))
    },

    get pinnedCount() {
        return Object.values(this.sources).flat().filter(c => c.is_pinned).length
    },

    async init() {
        this.loading = true
        try {
            const res    = await fetch(`/projects/${projectId}/column-config`, { headers: getHeaders() })
            this.sources = await res.json()
        } catch {}
        this.loading = false
    },

    async toggle(col) {
        col.is_pinned = !col.is_pinned
        try {
            await fetch(`/projects/${projectId}/column-config/toggle`, {
                method: 'POST', headers: jsonHeaders(),
                body: JSON.stringify({
                    source_table:  this.activeSource,
                    source_column: col.column,
                    is_pinned:     col.is_pinned,
                    display_label: col.label,
                }),
            })
        } catch { col.is_pinned = !col.is_pinned }
    },
}))

Alpine.data('importWizard', (projectId) => ({
    step:            1,
    error:           '',
    uploading:       false,
    uploadProgress:  0,
    sessionId:       null,
    sheets:          {},
    selectedSheet:   '',
    headers:         [],
    mapping:         {},
    fieldOptions:    [],
    targetTable:     'all',
    preview:         [],
    replaceExisting: false,
    status:          '',
    progress:        0,
    processedRows:   0,
    skippedRows:     0,

    uploadFile(event) {
        const file = event.target.files[0]
        if (!file) return
        this.uploading      = true
        this.uploadProgress = 0
        this.error          = ''

        const form = new FormData()
        form.append('file', file)
        form.append('project_id', projectId)
        form.append('_token', csrf())

        const xhr = new XMLHttpRequest()

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                this.uploadProgress = Math.min(99, Math.round((e.loaded / e.total) * 100))
            }
        }

        xhr.onload = () => {
            let data
            try { data = JSON.parse(xhr.responseText) } catch {
                console.error('Non-JSON response:', xhr.responseText.substring(0, 800))
                this.error = `Server error ${xhr.status}: ${xhr.responseText.substring(0, 200)}`
                this.uploading = false
                return
            }
            if (xhr.status >= 400) {
                const firstErr = data.errors ? Object.values(data.errors)[0]?.[0] : null
                this.error = firstErr || data.message || `Server error ${xhr.status}.`
                this.uploading = false
                return
            }
            this.uploadProgress = 100
            this.sessionId      = data.session_id
            this.sheets         = data.sheets ?? {}
            this.selectedSheet  = Object.keys(this.sheets)[0] || ''
            this.sheetSelected()
            this.uploading = false
            this.step      = 2
        }

        xhr.onerror = () => {
            this.error     = 'Connection failed — server unreachable.'
            this.uploading = false
        }

        xhr.open('POST', '/import/upload')
        xhr.setRequestHeader('Accept', 'application/json')
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf())
        xhr.send(form)
    },

    init() {
        this.fieldOptions = this.getFieldOptions(this.targetTable)
    },

    getFieldOptions(table) {
        const id = [
            { value: 'well_id',     label: 'Well ID',     aliases: ['well id', 'wellid', 'wellno', 'well no'] },
            { value: 'well_number', label: 'Well Number',  aliases: ['well no', 'well num', 'wellnumber', 'wellno', 'well name', 'wellname', 'well_name'] },
        ]
        const wells = [...id,
            { value: 'well_type',          label: 'Well Type',          aliases: ['welltype', 'pdo well type', 'pdowelltype', 'type'] },
            { value: 'well_field',         label: 'Field',              aliases: ['field', 'well field', 'wellfield'] },
            { value: 'well_location',      label: 'Well Location',      aliases: ['well location', 'welllocation', 'well_location', 'location', 'well loc', 'well loc.'] },
            { value: 'contract_no',        label: 'Contract No',        aliases: ['contract no', 'contract number', 'contract_number', 'contractno'] },
            { value: 'scope',              label: 'Scope',              aliases: [] },
            { value: 'hua_date',           label: 'HUA Date',           aliases: ['hua'] },
            { value: 'ramz_id',            label: 'Ramz ID',            aliases: ['ramz', 'ramzid'] },
            { value: 'year',               label: 'Year',               aliases: ['year', 'plan year', 'planyear'] },
            { value: 'responsible_asset',  label: 'Responsible Asset',  aliases: ['asset', 'resp asset', 'responsible asset'] },
            { value: 'cims_upload_status', label: 'CIMS Upload Status', aliases: ['cims status'] },
            { value: 'spir_status',        label: 'SPIR Status',        aliases: ['spir'] },
            { value: 'asb_edw_status',     label: 'ASB EDW Status',     aliases: ['edw asb status', 'edw asb- status', 'asb edw'] },
            { value: 'e_pad_closeout',     label: 'E-Pad Closeout',     aliases: ['epad', 'e pad', 'e-pad closout', 'e pad closout', 'epad closout', 'e pad close out'] },
            { value: 'rlmu_status',        label: 'RLMU Status',        aliases: ['rlmu'] },
        ]
        const sap = [...id,
            { value: 'earliest_start_date',      label: 'Earliest Start Date',      aliases: ['start date', 'est start', 'earlieststartdate', 'earlieststartdate2'] },
            { value: 'earliest_end_date',        label: 'Earliest End Date',        aliases: ['end date', 'est end', 'earliestenddate', 'earliestenddate2', 'earliest end date 2'] },
            { value: 'hook_up_tie_in_completed', label: 'Tie-in Completed',         aliases: ['tie in', 'hookup', 'hook up', 'tie-in', 'hook up tie in', 'hook up and tie in completd', 'hook up and tie in completed', 'hookup and tie in', 'hook up tie in completd'] },
            { value: 'commissioning_date',       label: 'Commissioning Date',       aliases: ['commissioning', 'commissioning date'] },
            { value: 'z6_creation_date',         label: 'Z6 Creation Date',         aliases: ['z6 created', 'z6 creation'] },
            { value: 'z6_awap_date',             label: 'Z6 AWAP Date',             aliases: ['awap', 'z6 awap'] },
            { value: 'z6_appr_date',             label: 'Z6 Appr Date',             aliases: ['z6 approval', 'z6 appr'] },
            { value: 'sap_upload_date',          label: 'SAP Upload Date',          aliases: ['sap upload'] },
            { value: 'bom_linking_date',         label: 'BOM Linking Date',         aliases: ['bom date', 'bom linking'] },
            { value: 'afc_submitted_date',       label: 'AFC Submitted Date',       aliases: ['afc submit', 'afc submitted'] },
            { value: 'afc_edw_completed_date',   label: 'AFC EDW Completed Date',   aliases: ['afc edw completed', 'afc completed'] },
            { value: 'asb_edw_sent_date',        label: 'ASB EDW Sent Date',        aliases: ['asb edw sent', 'asb sent'] },
            { value: 'inst_manufacturer_date',   label: 'Inst Manufacturer Date',   aliases: ['inst manufacturer', 'instrument manufacturer'] },
            { value: 'mech_manufacturer_date',   label: 'Mech Manufacturer Date',   aliases: ['mech manufacturer', 'mechanical manufacturer'] },
            { value: 'rv_mfr_details_date',      label: 'RV MFR Details Date',      aliases: ['rv mfr', 'rv manufacturer'] },
            { value: 'esp_req_date',             label: 'ESP Req Date',             aliases: ['esp', 'esp req', 'esp request'] },
            { value: 'z6_number',                label: 'Z6 Number',                aliases: ['z6 no', 'z6no', 'sap z6 number', 'sap z6 no', 'sap z6'] },
            { value: 'sap_status',               label: 'SAP Status',               aliases: ['sap stat'] },
            { value: 'upload_status',            label: 'Upload Status',            aliases: [] },
            { value: 'afc_document_spo_status',  label: 'AFC Document SPO Status',  aliases: ['afc spo status', 'afc spo', 'afc document spo'] },
            { value: 'afc_edw_status',           label: 'AFC EDW Status',           aliases: ['edw afc status', 'edw afc', 'afc edw'] },
            { value: 'asb_edw_status',           label: 'ASB EDW Status',           aliases: ['edw asb status', 'edw asb- status', 'edw asb', 'asb edw'] },
            { value: 'asb_edw_transmittal',      label: 'ASB EDW Transmittal',      aliases: ['asb edw tr', 'asb transmittal'] },
            { value: 'bom_linking_status',       label: 'BOM Linking Status',       aliases: ['bom status', 'bom linking stat'] },
            { value: 'cims_document_number',     label: 'CIMS Document Number',     aliases: ['cims doc', 'cims doc number', 'cims doc no', 'cims document no'] },
            { value: 'cims_upload_status',       label: 'CIMS Upload Status',       aliases: ['cims status', 'cims upload'] },
            { value: 'cims_transmittal_no',      label: 'CIMS Transmittal No',      aliases: ['cims tr no', 'cims transmittal'] },
            { value: 'spir_status',              label: 'SPIR Status',              aliases: ['spir'] },
            { value: 'im_health_check_tags',     label: 'IM Health Check Tags',     aliases: ['im health check', 'im health', 'health check', 'im healthcheck'] },
            { value: 'asset_register_report',    label: 'Asset Register Report',    aliases: ['asset register', 'asset report'] },
            { value: 'sap_screenshot',           label: 'SAP Screenshot',           aliases: ['screenshot', 'sap screen'] },
            { value: 'elec_isolator',            label: 'Elec Isolator',            aliases: ['electrical isolator', 'elec iso'] },
            { value: 'db_field',                 label: 'DB Field',                 aliases: ['db', 'database field'] },
            { value: 'edw_remark',               label: 'EDW Remark',               aliases: ['edw remarks', 'edw remark', 'edw note', 'edw notes'] },
        ]
        const dc = [...id,
            { value: 'well_time_document_status',    label: 'Well Time Document Status',    aliases: ['well time doc', 'well time document', 'time document status'] },
            { value: 'afc_upload_status',            label: 'AFC Upload Status',            aliases: ['afc upload', 'afc status'] },
            { value: 'final_afc_upload_date',        label: 'Final AFC Upload Date',        aliases: ['final afc', 'afc final upload'] },
            { value: 'afc_document_remarks',         label: 'AFC Document Remarks',         aliases: ['afc remarks', 'afc doc remarks', 'afc remark'] },
            { value: 'earliest_start_date',          label: 'Earliest Start Date',          aliases: ['start date', 'est start', 'earlieststartdate', 'earlieststartdate2'] },
            { value: 'earliest_end_date',            label: 'Earliest End Date',            aliases: ['end date', 'est end', 'earliestenddate', 'earliestenddate2', 'earliest end date 2'] },
            { value: 'rlmu_upload_status',           label: 'RLMU Upload Status',           aliases: ['rlmu status', 'rlmu upload', 'rlmu', 'RLMU Status(Ready/Not Ready) 10 days', 'rlmu statusready not ready 10 days', 'rlmu status ready not ready'] },
            { value: 'final_rlmu_upload_date',       label: 'Final RLMU Upload Date',       aliases: ['final rlmu', 'rlmu final', 'rlmu upload date'] },
            { value: 'asb_receive_date',             label: 'ASB Receive Date',             aliases: ['asb received', 'asb receive'] },
            { value: 'asb_document_status',          label: 'ASB Document Status',          aliases: ['asb doc status', 'asb status', 'asb document'] },
            { value: 'asb_document_submission_date', label: 'ASB Document Submission Date', aliases: ['asb submission', 'asb submit date', 'asb doc submission'] },
            { value: 'asb_tr_no',                    label: 'ASB TR No',                    aliases: ['asb tr', 'asb tr no', 'asb transmittal no', 'asb transmittal number'] },
            { value: 'document_im_health_check',     label: 'Document IM Health Check',     aliases: ['doc im health', 'document health check', 'im health check doc'] },
        ]
        // "all" — combine all three lists, deduplicate by value (first occurrence wins)
        const all = (() => {
            const seen = new Set()
            return [...wells, ...sap, ...dc].filter(f => {
                if (seen.has(f.value)) return false
                seen.add(f.value)
                return true
            })
        })()

        return { project_wells: wells, sap_tracking: sap, dc_tracking: dc, all }[table] ?? all
    },

    autoMap() {
        const norm = s => (s ?? '').toLowerCase().trim()
            .replace(/[\s\/\-\.]+/g, '_')
            .replace(/[^a-z0-9_]/g, '')
            .replace(/_+/g, '_')
            .replace(/^_+|_+$/g, '')

        const fields = this.getFieldOptions(this.targetTable)

        // null-prototype object avoids false matches against Object.prototype properties
        const lookup = Object.create(null)
        for (const f of fields) {
            lookup[f.value]       = f.value
            lookup[norm(f.label)] = f.value
            for (const a of (f.aliases ?? [])) lookup[norm(a)] = f.value
        }

        // Clear then re-set in-place so Alpine x-model bindings stay live
        for (const k of Object.keys(this.mapping)) delete this.mapping[k]
        for (const header of this.headers) {
            const n = norm(header)
            if (n in lookup) this.mapping[header] = lookup[n]
        }
    },

    sheetSelected() {
        this.headers      = this.sheets[this.selectedSheet] || []
        this.fieldOptions = this.getFieldOptions(this.targetTable)
        this.autoMap()
    },

    async loadPreview() {
        this.error = ''
        try {
            const res  = await fetch(`/import/${this.sessionId}/preview`, {
                method: 'POST', headers: jsonHeaders(),
                body: JSON.stringify({ sheet: this.selectedSheet, mapping: this.mapping, target_table: this.targetTable }),
            })
            const data = await res.json()
            if (!res.ok) {
                const firstErr = data.errors ? Object.values(data.errors)[0]?.[0] : null
                this.error = firstErr || data.message || `Preview error ${res.status}.`
                return
            }
            this.preview = data.preview || []
            this.step    = 3
        } catch { this.error = 'Preview failed — check your connection.' }
    },

    async confirm() {
        this.error = ''
        try {
            const res  = await fetch(`/import/${this.sessionId}/confirm`, {
                method: 'POST', headers: jsonHeaders(),
                body: JSON.stringify({ mapping: this.mapping, target_table: this.targetTable, sheet: this.selectedSheet, replace_existing: this.replaceExisting }),
            })
            const data = await res.json()
            if (!res.ok) {
                const firstErr = data.errors ? Object.values(data.errors)[0]?.[0] : null
                this.error = firstErr || data.message || `Confirm error ${res.status}.`
                return
            }
        } catch { this.error = 'Confirm failed — check your connection.'; return }
        this.step     = 4
        this.status   = 'pending'
        this.progress = 0
        this.pollStatus()
    },

    async pollStatus() {
        try {
            const res  = await fetch(`/import/${this.sessionId}/status`, { headers: getHeaders() })
            const data = await res.json()
            this.status        = data.status
            this.processedRows = data.processed_rows || 0
            this.skippedRows   = data.skipped_rows   || 0
            const done  = this.processedRows + this.skippedRows
            const total = data.total_rows || 0
            if (data.status === 'completed' || data.status === 'failed') {
                this.progress = 100
            } else {
                this.progress = total > 0 ? Math.min(99, Math.round((done / total) * 100)) : 0
            }
            if (data.status !== 'completed' && data.status !== 'failed') {
                setTimeout(() => this.pollStatus(), 2000)
            }
        } catch {}
    },
}))

window.Alpine = Alpine
Alpine.start()
