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
    'RASHEEQ D4',
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

/**
 * Every validation message from a Laravel error response, one per line,
 * so the user can fix all problems in one go.
 */
const apiError = (data, fallback = 'Something went wrong.') => {
    const msgs = data?.errors ? [...new Set(Object.values(data.errors).flat())] : []
    return msgs.length ? msgs.join('\n') : (data?.message || fallback)
}
window.apiError = apiError

/** ISO / server timestamp → "12 Nov 2026, 16:07" in the viewer's own time zone. */
const fmtDateTime = val => {
    if (!val) return '—'
    const d = new Date(val)
    if (isNaN(d)) return val
    return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
}
window.fmtDateTime = fmtDateTime

/** "1 well", "2 wells" */
window.plural = (n, word, pluralWord = word + 's') => `${n} ${Number(n) === 1 ? word : pluralWord}`

window.sourceColor = src => ({
    project_wells: '#1D9E75',
    sap_tracking:  '#BA7517',
    dc_tracking:   '#D85A30',
    computed:      '#639922',
    custom:        '#7C3AED',
}[src] || '#888780')

// SAP KPI = End − Start (SAP work duration). Keep in sync with CountdownService::sapKpiStatus
window.kpiStatus = days => {
    if (days == null) return 'none'
    if (days <= 30) return 'green'  // done within a month
    if (days <= 60) return 'amber'  // 31–60 days
    return 'red'                    // over 60 days
}

// Countdown = (Commissioning + 90) − today: days left, negative = overdue.
// Keep in sync with CountdownService::countdownStatus
window.countdownStatus = (days, ePad) => {
    if (days == null) return 'none'
    if ((ePad || '').trim().toLowerCase() === 'closed') return 'closed'
    if (days < 0)   return 'overdue'
    if (days <= 10) return 'warning'
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

Alpine.data('contractList', () => ({
    contracts:  [],
    loading:    false,
    showCreate: false,
    showEdit:   false,
    saving:     false,
    error:      '',
    editError:  '',
    editId:     null,
    form:     { name: '', contract_no: '', description: '' },
    editForm: { name: '', contract_no: '', description: '', status: 'active' },

    async init() {
        this.loading = true
        try {
            const res      = await fetch('/contracts/list', { headers: getHeaders() })
            this.contracts = await res.json()
        } catch {}
        this.loading = false
    },

    async create() {
        this.saving = true; this.error = ''
        try {
            const res  = await fetch('/contracts', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(this.form) })
            const data = await res.json()
            if (!res.ok) {
                this.error = apiError(data, 'Error.')
            } else {
                this.contracts.unshift(data)
                this.showCreate = false
                this.form = { name: '', contract_no: '', description: '' }
            }
        } catch { this.error = 'Network error.' }
        this.saving = false
    },

    openEdit(contract) {
        this.editId   = contract.id
        this.editForm = { name: contract.name, contract_no: contract.contract_no || '', description: contract.description || '', status: contract.status || 'active' }
        this.editError = ''
        this.showEdit  = true
    },

    async update() {
        this.saving = true; this.editError = ''
        try {
            const res  = await fetch(`/contracts/${this.editId}`, { method: 'PUT', headers: jsonHeaders(), body: JSON.stringify(this.editForm) })
            const data = await res.json()
            if (!res.ok) {
                this.editError = apiError(data, 'Error.')
            } else {
                const idx = this.contracts.findIndex(c => c.id === this.editId)
                if (idx !== -1) this.contracts[idx] = { ...this.contracts[idx], ...data }
                this.showEdit = false
            }
        } catch { this.editError = 'Network error.' }
        this.saving = false
    },

    async deleteContract(contract) {
        if (!confirm(`Delete "${contract.name}"?\n\nThis will permanently remove the contract and ALL its projects and wells.`)) return
        const res = await fetch(`/contracts/${contract.id}`, { method: 'DELETE', headers: jsonHeaders() })
        if (res.ok) this.contracts = this.contracts.filter(c => c.id !== contract.id)
    },
}))

Alpine.data('contractDetail', (contractId) => ({
    projects:   [],
    loading:    false,
    showCreate: false,
    saving:     false,
    error:      '',
    form: { name: '', description: '' },

    async init() {
        this.loading = true
        try {
            const res     = await fetch(`/contracts/${contractId}/projects`, { headers: getHeaders() })
            this.projects = await res.json()
        } catch {}
        this.loading = false
    },

    async create() {
        this.saving = true; this.error = ''
        try {
            const res  = await fetch(`/contracts/${contractId}/projects`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(this.form) })
            const data = await res.json()
            if (!res.ok) {
                this.error = apiError(data, 'Error.')
            } else {
                this.projects.unshift(data)
                this.showCreate = false
                this.form = { name: '', description: '' }
            }
        } catch { this.error = 'Network error.' }
        this.saving = false
    },

    async deleteProject(project) {
        if (!confirm(`Delete "${project.name}"? This will permanently remove the project and all its wells.`)) return
        const res = await fetch(`/projects/${project.id}`, { method: 'DELETE', headers: jsonHeaders() })
        if (res.ok) this.projects = this.projects.filter(p => p.id !== project.id)
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
                this.error = apiError(data, 'Error.')
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
                this.error = apiError(data, 'Save failed.')
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
    showShareModal:   false,
    shareLink:        null,
    shareLoading:     false,
    shareCopied:      false,
    showFilters:      false,
    filterDateFrom:   '',
    filterDateTo:     '',
    filterWellId:     '',
    filterEpadStatus: '',
    filterSapStatus:  '',
    filterDelayed:    false,
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
        // Mark ready AFTER options are populated — the @change guard uses this.
        // x-model set the value before the year options existed, so the browser fell back
        // to showing "All years" while the grid was filtered to one year; re-sync it here.
        this.$nextTick(() => {
            if (this.$refs.yearSelect) this.$refs.yearSelect.value = this.filterYear
            this.yearsReady = true
        })
    },

    async load() {
        this.loading = true
        const p = new URLSearchParams({ page: this.page })
        if (this.filterYear)      p.set('year',          this.filterYear)
        if (this.shiftedOnly)     p.set('shifted_only',  '1')
        if (this.search)          p.set('search',        this.search)
        if (this.filterType)      p.set('well_type',     this.filterType)
        if (this.filterDateFrom)  p.set('date_from',     this.filterDateFrom)
        if (this.filterDateTo)    p.set('date_to',       this.filterDateTo)
        if (this.filterWellId)    p.set('well_id_f',     this.filterWellId)
        if (this.filterEpadStatus) p.set('epad_status',  this.filterEpadStatus)
        if (this.filterSapStatus)  p.set('sap_status',   this.filterSapStatus)
        if (this.filterDelayed)    p.set('delayed_only', '1')
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

    get activeFilterCount() {
        return [this.filterDateFrom, this.filterDateTo, this.filterWellId,
                this.filterEpadStatus, this.filterSapStatus, this.filterDelayed].filter(Boolean).length
    },

    resetFilters() {
        this.filterDateFrom = ''; this.filterDateTo = ''; this.filterWellId = ''
        this.filterEpadStatus = ''; this.filterSapStatus = ''; this.filterDelayed = false
        this.page = 1; this.load()
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
        if (col.source_table === 'project_wells')  return isSuperAdmin
        if (col.source_table === 'sap_tracking')   return isSuperAdmin || role === 'sap_admin'
        if (col.source_table === 'dc_tracking')    return isSuperAdmin || role === 'dc'
        return false
    },

    canCommentOnly(col, role) {
        return role === 'manager' && col.source_table !== 'computed'
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
            if (!res.ok) { this.addColError = apiError(data, 'Failed to create column.'); return }

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

    async openShareModal() {
        this.showShareModal = true
        this.shareLoading   = true
        try {
            const res  = await fetch(`/projects/${projectId}/share`, { headers: getHeaders() })
            this.shareLink = res.ok ? await res.json() : null
        } catch {}
        this.shareLoading = false
    },

    async generateShare() {
        this.shareLoading = true
        try {
            const res  = await fetch(`/projects/${projectId}/share`, { method: 'POST', headers: jsonHeaders() })
            if (res.ok) this.shareLink = await res.json()
        } catch {}
        this.shareLoading = false
    },

    copyShareLink(url) {
        const tryClipboard = () => {
            const el = document.createElement('textarea')
            el.value = url
            el.style.position = 'fixed'
            el.style.opacity  = '0'
            document.body.appendChild(el)
            el.focus()
            el.select()
            document.execCommand('copy')
            document.body.removeChild(el)
        }
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).catch(tryClipboard)
        } else {
            tryClipboard()
        }
        this.shareCopied = true
        setTimeout(() => { this.shareCopied = false }, 2000)
    },

    async revokeShare() {
        if (!confirm('Revoke this link? Anyone with the current URL will lose access immediately.')) return
        this.shareLoading = true
        try {
            const res = await fetch(`/projects/${projectId}/share`, { method: 'DELETE', headers: jsonHeaders() })
            if (res.ok) this.shareLink = null
        } catch {}
        this.shareLoading = false
    },

    async toggleHideCell(well, col) {
        const res = await fetch(`/projects/${projectId}/wells/${well.id}/hide-cell`, {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ source_table: col.source_table, source_column: col.source_column }),
        })
        if (res.ok) {
            const data = await res.json()
            if (!well.hidden_cells) well.hidden_cells = []
            const key = `${col.source_table}.${col.source_column}`
            if (data.hidden) {
                if (!well.hidden_cells.includes(key)) well.hidden_cells.push(key)
            } else {
                well.hidden_cells = well.hidden_cells.filter(k => k !== key)
            }
        }
    },

    isCellHidden(well, col) {
        return Array.isArray(well.hidden_cells) &&
               well.hidden_cells.includes(`${col.source_table}.${col.source_column}`)
    },

    async toggleHideColumn(col) {
        const res = await fetch(`/projects/${projectId}/column-config/${col.id}/hidden`, {
            method: 'PATCH', headers: jsonHeaders(),
        })
        if (res.ok) {
            const data = await res.json()
            col.is_hidden = data.is_hidden
        }
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
            if (!res.ok) { this.saveError = apiError(data, 'Error saving.'); this.saving = false; return }
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
            if (!res.ok) { this.saveError = apiError(data, 'Error saving.'); this.saving = false; return }
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
    analysis:        null,
    loadingPreview:  false,
    replaceExisting: false,
    result:          null,
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
            this.selectedSheet  = this.bestSheet()
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

    /** Excel header → system field, for every header that matches a known field or alias. */
    matchHeaders(headers) {
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

        const result = {}
        for (const header of headers) {
            const n = norm(header)
            if (n in lookup) result[header] = lookup[n]
        }
        return result
    },

    autoMap() {
        const matched = this.matchHeaders(this.headers)
        // Clear then re-set in-place so Alpine x-model bindings stay live
        for (const k of Object.keys(this.mapping)) delete this.mapping[k]
        for (const [header, field] of Object.entries(matched)) this.mapping[header] = field
    },

    /** The data sheet is the one whose headers match the most fields (not a pivot/dashboard tab). */
    bestSheet() {
        let best = '', bestScore = -1
        for (const [name, headers] of Object.entries(this.sheets)) {
            const matched = Object.values(this.matchHeaders(headers || []))
            const hasId   = matched.includes('well_id') || matched.includes('well_number')
            const score   = matched.length + (hasId ? 1000 : 0)
            if (score > bestScore) { best = name; bestScore = score }
        }
        return best
    },

    sheetSelected() {
        this.headers      = this.sheets[this.selectedSheet] || []
        this.fieldOptions = this.getFieldOptions(this.targetTable)
        // Defer autoMap until Alpine has flushed the DOM — options must exist
        // in <select> before x-model can sync the selected value correctly.
        this.$nextTick(() => this.autoMap())
    },

    get mappedCount() {
        return Object.values(this.mapping).filter(v => v).length
    },

    get hasIdentifier() {
        const vals = Object.values(this.mapping)
        return vals.includes('well_id') || vals.includes('well_number')
    },

    get duplicateMappings() {
        const counts = {}
        for (const v of Object.values(this.mapping)) {
            if (v) counts[v] = (counts[v] || 0) + 1
        }
        return Object.keys(counts).filter(k => counts[k] > 1)
    },

    fieldLabel(value) {
        return this.fieldOptions.find(f => f.value === value)?.label ?? value
    },

    async loadPreview() {
        this.error = ''
        this.loadingPreview = true
        try {
            const res  = await fetch(`/import/${this.sessionId}/preview`, {
                method: 'POST', headers: jsonHeaders(),
                body: JSON.stringify({ sheet: this.selectedSheet, mapping: this.mapping, target_table: this.targetTable }),
            })
            const data = await res.json()
            if (!res.ok) {
                this.error = apiError(data, `Preview error ${res.status}.`)
                this.loadingPreview = false
                return
            }
            this.preview  = data.preview || []
            this.analysis = data.analysis || null
            this.step     = 3
        } catch { this.error = 'Preview failed — check your connection.' }
        this.loadingPreview = false
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
                this.error = apiError(data, `Confirm error ${res.status}.`)
                return
            }
        } catch { this.error = 'Confirm failed — check your connection.'; return }
        this.step     = 4
        this.status   = 'pending'
        this.progress = 0
        this.result   = null
        window.dispatchEvent(new CustomEvent('import-updated'))
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
            } else {
                this.result = data
                // Refresh the history table below without a page reload
                window.dispatchEvent(new CustomEvent('import-updated'))
            }
        } catch {}
    },

    /** Plain-language one-line result, in wells rather than rows. */
    resultSummary(r) {
        if (!r) return ''
        const parts = [`${plural(r.created_wells || 0, 'well')} created`, `${r.updated_wells || 0} updated`]
        let text = parts.join(', ') + ` from ${plural(r.total_rows || 0, 'row')}.`
        if (r.merged_rows) text += ` ${plural(r.merged_rows, 'row')} repeated an earlier Well ID and ${r.merged_rows === 1 ? 'was' : 'were'} combined into that well.`
        if (r.skipped_rows) text += ` ${plural(r.skipped_rows, 'row')} could not be imported.`
        return text
    },

    messageLines(text) {
        return (text || '').split('\n').filter(Boolean)
    },
}))

Alpine.data('importHistory', (projectId) => ({
    sessions: [],
    loading:  false,

    openId:   null,

    init() {
        this.loading = true
        this.refresh()
        window.addEventListener('import-updated', () => this.refresh())
    },

    async refresh() {
        try {
            const res  = await fetch(`/projects/${projectId}/import-history`, { headers: getHeaders() })
            const data = await res.json()
            this.sessions = Array.isArray(data) ? data : (data.data ?? [])
        } catch {}
        this.loading = false
        // Keep the table live while any import is still running
        clearTimeout(this._timer)
        if (this.sessions.some(s => s.status === 'pending' || s.status === 'processing')) {
            this._timer = setTimeout(() => this.refresh(), 3000)
        }
    },

    lines(text) {
        return (text || '').split('\n').filter(Boolean)
    },

    /** Imports run before well counts were recorded only have row counts. */
    isLegacy(s) {
        return !s.created_wells && !s.updated_wells && s.processed_rows > 0
    },

    statusBadge(status) {
        return {
            completed:  'badge-green',
            failed:     'badge-red',
            processing: 'badge-amber',
            pending:    'badge-blue',
            mapping:    'badge-gray',
        }[status] ?? 'badge-gray'
    },

    tableLabel(t) {
        return { project_wells: 'Wells', sap_tracking: 'SAP', dc_tracking: 'DC', all: 'All' }[t] ?? t
    },
}))

Alpine.data('sharedGrid', (token) => ({
    token,
    wells:      [],
    columns:    [],
    pagination: null,
    loading:    false,
    search:     '',
    filterYear: String(new Date().getFullYear()),
    filterType: '',
    page:       1,
    debouncedLoad: null,

    init() {
        this.debouncedLoad = window.debounce(() => { this.page = 1; this.load() }, 400)
        this.load()
    },

    async load() {
        this.loading = true
        const p = new URLSearchParams({ page: this.page })
        if (this.filterYear) p.set('year',      this.filterYear)
        if (this.search)     p.set('search',    this.search)
        if (this.filterType) p.set('well_type', this.filterType)
        try {
            const res  = await fetch(`/share/${this.token}/wells?${p}`)
            const data = await res.json()
            this.wells      = data.wells?.data ?? []
            this.columns    = data.columns     ?? []
            this.pagination = data.wells       ?? null
        } catch {}
        this.loading = false
    },

    sourceColor(src) {
        return { project_wells:'#1D9E75', sap_tracking:'#BA7517', dc_tracking:'#D85A30', computed:'#639922', custom:'#7C3AED' }[src] ?? '#6B7280'
    },

    kpiStatus(days) {
        return window.kpiStatus(days)
    },

    badgeClass(status) {
        return { red:'badge-red', amber:'badge-amber', green:'badge-green', overdue:'badge-red', warning:'badge-amber', ok:'badge-green', closed:'badge-gray', none:'badge-gray' }[status] ?? 'badge-gray'
    },

    getCellValue(well, col) {
        if (col.source_table === 'sap_tracking') return well.sap_tracking?.[col.source_column] ?? ''
        if (col.source_table === 'dc_tracking')  return well.dc_tracking?.[col.source_column]  ?? ''
        if (col.source_table === 'computed')      return well.computed?.[col.source_column]     ?? ''
        if (col.source_table === 'custom')        return well.custom?.[col.source_column]       ?? ''
        return well[col.source_column] ?? ''
    },
}))

Alpine.data('letterManager', (contractId, isSuperAdmin = false) => ({
    letters:      [],
    tab:          'outgoing',
    loading:      false,
    showForm:     false,
    showThread:   false,
    threadLetter: null,
    editId:       null,
    saving:       false,
    error:        '',
    refSearch:    '',
    search:       '',
    form: {
        ctp_number: '', reference_no: '', submission_date: '',
        description: '', direction: 'outgoing', reply_ref_no: '',
        related_refs: [], required_action: false, status: 'open', remarks: '',
    },

    get filteredRefOptions() {
        const q = this.refSearch.toLowerCase()
        return this.letters.filter(l =>
            l.reference_no !== this.form.reference_no &&
            !(this.form.related_refs || []).includes(l.reference_no) &&
            (!q || l.reference_no.toLowerCase().includes(q))
        )
    },

    addRelatedRef(ref) {
        if (!this.form.related_refs) this.form.related_refs = []
        if (!this.form.related_refs.includes(ref)) this.form.related_refs.push(ref)
        this.refSearch = ''
    },

    removeRelatedRef(ref) {
        this.form.related_refs = (this.form.related_refs || []).filter(r => r !== ref)
    },

    openThread(letter) {
        this.threadLetter = letter
        this.showThread   = true
    },

    threadLetters(letter) {
        if (!letter) return []
        const visited = new Set()
        const queue   = [letter.reference_no]
        if (letter.reply_ref_no)  queue.push(letter.reply_ref_no)
        ;(letter.related_refs || []).forEach(r => queue.push(r))

        while (queue.length) {
            const ref = queue.shift()
            if (!ref || visited.has(ref)) continue
            visited.add(ref)
            this.letters.forEach(l => {
                if (l.reference_no === ref) {
                    if (l.reply_ref_no && !visited.has(l.reply_ref_no))   queue.push(l.reply_ref_no)
                    ;(l.related_refs || []).filter(r => !visited.has(r)).forEach(r => queue.push(r))
                }
                if (l.reply_ref_no === ref && !visited.has(l.reference_no)) queue.push(l.reference_no)
            })
        }

        return this.letters
            .filter(l => visited.has(l.reference_no))
            .sort((a, b) => new Date(a.submission_date) - new Date(b.submission_date))
    },

    threadCount(letter) {
        return this.threadLetters(letter).length
    },

    outgoingRemarkPresets: [
        'CH advised by mail to conduct a meeting',
        'Engineering Manager - following up with PDO',
        'Closed',
        'Withdrawal - closed',
    ],
    incomingRemarkPresets: [
        'Follow-up With HSE',
        'No letter in Mirsal-Received',
        'This NTF shall be implemented across all ongoing and future projects. ',
        'Will discussed in Leadership meeting',
        'Closed',
        'Withdrawal - closed',
    ],
    get remarkPresets() {
        return this.form.direction === 'incoming' ? this.incomingRemarkPresets : this.outgoingRemarkPresets
    },
    appendRemark(text) {
        this.form.remarks = this.form.remarks
            ? this.form.remarks.trimEnd() + '\n' + text
            : text
    },

    matchesSearch(l) {
        const q = this.search.trim().toLowerCase()
        if (!q) return true
        return (l.reference_no || '').toLowerCase().includes(q)
            || (l.description  || '').toLowerCase().includes(q)
    },

    get outgoing()     { return this.letters.filter(l => l.direction === 'outgoing' && this.matchesSearch(l)) },
    get incoming()     { return this.letters.filter(l => l.direction === 'incoming' && this.matchesSearch(l)) },
    get alertLetters() {
        return this.letters.filter(l => l.direction === 'outgoing' && l.status === 'open' && l.days_elapsed >= 10)
    },

    linkedIncoming(out) {
        return this.incoming.find(l => l.reply_ref_no === out.reference_no) || null
    },
    linkedOutgoing(inc) {
        return this.outgoing.find(l => l.reference_no === inc.reply_ref_no) || null
    },

    async init() {
        this.loading = true
        try {
            const res    = await fetch(`/contracts/${contractId}/letters-data`, { headers: getHeaders() })
            this.letters = await res.json()
        } catch {}
        this.loading = false
    },

    openCreate(direction = 'outgoing') {
        this.editId   = null
        this.error    = ''
        this.refSearch = ''
        this.form     = {
            ctp_number: '', reference_no: '',
            submission_date: new Date().toISOString().slice(0, 10),
            description: '', direction, reply_ref_no: '',
            related_refs: [], required_action: false, status: 'open', remarks: '',
        }
        this.showForm = true
    },

    openEdit(letter) {
        this.editId    = letter.id
        this.error     = ''
        this.refSearch = ''
        this.form      = {
            ctp_number:      letter.ctp_number      || '',
            reference_no:    letter.reference_no,
            submission_date: letter.submission_date,
            description:     letter.description     || '',
            direction:       letter.direction,
            reply_ref_no:    letter.reply_ref_no    || '',
            related_refs:    letter.related_refs    ? [...letter.related_refs] : [],
            required_action: letter.required_action,
            status:          letter.status,
            remarks:         letter.remarks         || '',
        }
        this.showForm = true
    },

    async save() {
        this.saving = true; this.error = ''
        const url    = this.editId ? `/contracts/${contractId}/letters/${this.editId}` : `/contracts/${contractId}/letters`
        const method = this.editId ? 'PUT' : 'POST'
        try {
            const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(this.form) })
            const data = await res.json()
            if (!res.ok) {
                this.error = apiError(data, 'Error.')
            } else {
                if (this.editId) {
                    const idx = this.letters.findIndex(l => l.id === this.editId)
                    if (idx !== -1) this.letters[idx] = data
                } else {
                    this.letters.unshift(data)
                }
                this.showForm = false
            }
        } catch { this.error = 'Network error.' }
        this.saving = false
    },

    async deleteLetter(letter) {
        if (!confirm(`Delete letter "${letter.reference_no}"? This cannot be undone.`)) return
        const res = await fetch(`/contracts/${contractId}/letters/${letter.id}`, { method: 'DELETE', headers: jsonHeaders() })
        if (res.ok) this.letters = this.letters.filter(l => l.id !== letter.id)
    },

    fmtDate(d) {
        if (!d) return '—'
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    },
}))

Alpine.data('cvManager', (contractId, isSuperAdmin = false) => ({
    cvs:        [],
    tab:        'outgoing',
    loading:    false,
    showForm:   false,
    showThread: false,
    threadCv:   null,
    editId:     null,
    saving:     false,
    error:      '',
    refSearch:  '',
    search:     '',
    form: {
        ctp_number: '', reference_no: '', submission_date: '',
        description: '', direction: 'outgoing', reply_ref_no: '',
        related_refs: [], person_name: '', position: '',
        approval_status: 'pending', validity_value: null, validity_unit: 'months',
        remarks: '', hde_remarks: '',
    },

    remarkPresets: [
        'Awaiting PDO reply',
        'Approved — renewal letter sent to PDO',
        'Final approval — correspondence closed',
        'Rejected — resubmission required',
        'Closed',
    ],
    appendRemark(text) {
        this.form.remarks = this.form.remarks
            ? this.form.remarks.trimEnd() + '\n' + text
            : text
    },

    get computedExpiry() {
        if (!this.form.submission_date || !this.form.validity_value) return null
        const d = new Date(this.form.submission_date + 'T00:00:00')
        if (this.form.validity_unit === 'days') {
            d.setDate(d.getDate() + Number(this.form.validity_value))
        } else {
            d.setMonth(d.getMonth() + Number(this.form.validity_value))
        }
        return d.toISOString().slice(0, 10)
    },

    get filteredRefOptions() {
        const q = this.refSearch.toLowerCase()
        return this.cvs.filter(c =>
            c.reference_no !== this.form.reference_no &&
            !(this.form.related_refs || []).includes(c.reference_no) &&
            (!q || c.reference_no.toLowerCase().includes(q))
        )
    },

    addRelatedRef(ref) {
        if (!this.form.related_refs) this.form.related_refs = []
        if (!this.form.related_refs.includes(ref)) this.form.related_refs.push(ref)
        this.refSearch = ''
    },

    removeRelatedRef(ref) {
        this.form.related_refs = (this.form.related_refs || []).filter(r => r !== ref)
    },

    openThread(cv) {
        this.threadCv   = cv
        this.showThread = true
    },

    threadCvs(cv) {
        if (!cv) return []
        const visited = new Set()
        const queue   = [cv.reference_no]
        if (cv.reply_ref_no) queue.push(cv.reply_ref_no)
        ;(cv.related_refs || []).forEach(r => queue.push(r))

        while (queue.length) {
            const ref = queue.shift()
            if (!ref || visited.has(ref)) continue
            visited.add(ref)
            this.cvs.forEach(c => {
                if (c.reference_no === ref) {
                    if (c.reply_ref_no && !visited.has(c.reply_ref_no)) queue.push(c.reply_ref_no)
                    ;(c.related_refs || []).filter(r => !visited.has(r)).forEach(r => queue.push(r))
                }
                if (c.reply_ref_no === ref && !visited.has(c.reference_no)) queue.push(c.reference_no)
            })
        }

        return this.cvs
            .filter(c => visited.has(c.reference_no))
            .sort((a, b) => new Date(a.submission_date) - new Date(b.submission_date))
    },

    threadCount(cv) {
        return this.threadCvs(cv).length
    },

    matchesSearch(c) {
        const q = this.search.trim().toLowerCase()
        if (!q) return true
        return (c.reference_no || '').toLowerCase().includes(q)
            || (c.description  || '').toLowerCase().includes(q)
            || (c.person_name  || '').toLowerCase().includes(q)
    },

    get outgoing() { return this.cvs.filter(c => c.direction === 'outgoing' && this.matchesSearch(c)) },
    get incoming() { return this.cvs.filter(c => c.direction === 'incoming' && this.matchesSearch(c)) },
    get alertCvs() {
        return this.cvs.filter(c => c.approval_status === 'approved_temporary'
            && (c.countdown_status === 'yellow' || c.countdown_status === 'red'))
    },

    linkedIncoming(out) {
        return this.cvs.find(c => c.direction === 'incoming' && c.reply_ref_no === out.reference_no) || null
    },
    linkedOutgoing(inc) {
        return this.cvs.find(c => c.direction === 'outgoing' && c.reference_no === inc.reply_ref_no) || null
    },

    async init() {
        this.loading = true
        try {
            const res = await fetch(`/contracts/${contractId}/cvs-data`, { headers: getHeaders() })
            this.cvs  = await res.json()
        } catch {}
        this.loading = false
    },

    openCreate(direction = 'outgoing') {
        this.editId    = null
        this.error     = ''
        this.refSearch = ''
        this.form      = {
            ctp_number: '', reference_no: '',
            submission_date: new Date().toISOString().slice(0, 10),
            description: '', direction, reply_ref_no: '',
            related_refs: [], person_name: '', position: '',
            approval_status: 'pending', validity_value: null, validity_unit: 'months',
            remarks: '', hde_remarks: '',
        }
        this.showForm = true
    },

    openEdit(cv) {
        this.editId    = cv.id
        this.error     = ''
        this.refSearch = ''
        this.form      = {
            ctp_number:      cv.ctp_number      || '',
            reference_no:    cv.reference_no,
            submission_date: cv.submission_date,
            description:     cv.description     || '',
            direction:       cv.direction,
            reply_ref_no:    cv.reply_ref_no    || '',
            related_refs:    cv.related_refs    ? [...cv.related_refs] : [],
            person_name:     cv.person_name,
            position:        cv.position        || '',
            approval_status: cv.approval_status,
            validity_value:  cv.validity_value,
            validity_unit:   cv.validity_unit   || 'months',
            remarks:         cv.remarks         || '',
            hde_remarks:     cv.hde_remarks      || '',
        }
        this.showForm = true
    },

    async save() {
        this.saving = true; this.error = ''
        const url    = this.editId ? `/contracts/${contractId}/cvs/${this.editId}` : `/contracts/${contractId}/cvs`
        const method = this.editId ? 'PUT' : 'POST'
        try {
            const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(this.form) })
            const data = await res.json()
            if (!res.ok) {
                this.error = apiError(data, 'Error.')
            } else {
                if (this.editId) {
                    const idx = this.cvs.findIndex(c => c.id === this.editId)
                    if (idx !== -1) this.cvs[idx] = data
                } else {
                    this.cvs.unshift(data)
                }
                this.showForm = false
            }
        } catch { this.error = 'Network error.' }
        this.saving = false
    },

    async deleteCv(cv) {
        if (!confirm(`Delete CV letter "${cv.reference_no}" (${cv.person_name})? This cannot be undone.`)) return
        const res = await fetch(`/contracts/${contractId}/cvs/${cv.id}`, { method: 'DELETE', headers: jsonHeaders() })
        if (res.ok) this.cvs = this.cvs.filter(c => c.id !== cv.id)
    },

    fmtDate(d) {
        if (!d) return '—'
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    },
}))

Alpine.data('contractImport', (contractId, type) => ({
    open:       false,
    stage:      'upload',
    dragging:   false,
    error:      '',
    uploading:  false,
    submitting: false,
    token:      null,
    headers:    [],
    sampleRows: [],
    fields:     [],
    mapping:    {},
    totalRows:  0,
    result:     null,

    show() {
        this.open  = true
        this.error = ''
        this.reset()
    },

    close() {
        this.open = false
    },

    reset() {
        this.stage      = 'upload'
        this.token      = null
        this.headers    = []
        this.sampleRows = []
        this.fields     = []
        this.mapping    = {}
        this.result     = null
        this.error      = ''
    },

    handleFile(event) {
        const file = event.target.files[0]
        if (file) this.upload(file)
    },

    handleDrop(event) {
        this.dragging = false
        const file = event.dataTransfer.files[0]
        if (file) this.upload(file)
    },

    async upload(file) {
        this.uploading = true
        this.error     = ''

        const form = new FormData()
        form.append('type', type)
        form.append('file', file)

        try {
            const res = await fetch(`/contracts/${contractId}/import/upload`, {
                method:  'POST',
                headers: getHeaders(),
                body:    form,
            })
            const data = await res.json()
            if (!res.ok) {
                this.error = apiError(data, 'Upload failed.')
            } else {
                this.token      = data.token
                this.headers    = data.headers
                this.sampleRows = data.sample_rows
                this.fields     = data.fields
                this.mapping    = {}
                this.totalRows  = data.total_rows
                this.stage      = 'map'
                // Defer until Alpine flushes the DOM — options must exist in
                // the <select> before x-model can sync the selected value.
                this.$nextTick(() => this.autoMap())
            }
        } catch {
            this.error = 'Network error.'
        }
        this.uploading = false
    },

    autoMap() {
        const norm = s => (s ?? '').toLowerCase().trim()
            .replace(/[\s\/\-\.]+/g, '_')
            .replace(/[^a-z0-9_]/g, '')
            .replace(/_+/g, '_')
            .replace(/^_+|_+$/g, '')

        // null-prototype object avoids false matches against Object.prototype properties
        const lookup = Object.create(null)
        for (const f of this.fields) {
            lookup[f.value]       = f.value
            lookup[norm(f.label)] = f.value
            for (const a of (f.aliases ?? [])) lookup[norm(a)] = f.value
        }

        this.headers.forEach((header, idx) => {
            const n = norm(header)
            if (n in lookup) this.mapping[lookup[n]] = idx
        })
    },

    previewValue(fieldValue, row) {
        const idx = this.mapping[fieldValue]
        if (idx === undefined || idx === null || idx === '') return ''
        const v = row[idx]
        return v === null || v === undefined ? '' : v
    },

    get missingRequired() {
        return this.fields.filter(f => f.required && (this.mapping[f.value] === undefined || this.mapping[f.value] === ''))
    },

    async confirm() {
        if (this.missingRequired.length) {
            this.error = `Please map: ${this.missingRequired.map(f => f.label).join(', ')}`
            return
        }
        this.submitting = true
        this.error      = ''
        try {
            const res  = await fetch(`/contracts/${contractId}/import/confirm`, {
                method:  'POST',
                headers: jsonHeaders(),
                body:    JSON.stringify({ type, token: this.token, mapping: this.mapping }),
            })
            const data = await res.json()
            if (!res.ok) {
                this.error = apiError(data, 'Import failed.')
            } else {
                this.result = data
                this.stage  = 'result'
                this.$dispatch(type === 'cvs' ? 'cvs-imported' : 'letters-imported')
            }
        } catch {
            this.error = 'Network error.'
        }
        this.submitting = false
    },
}))

window.Alpine = Alpine
Alpine.start()
