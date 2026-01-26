@extends('layout.main_layout')

@section('content')
<div class="security-wrapper container-fluid">

    {{-- Header Cards --}}
    <div class="row mb-4 top-stats">
        <div class="col-md-3">
            <div class="stat-card shadow-sm">
                <h6>Total Incidents</h6> 
                <h2>{{ $totalIncidents !== "" ? $totalIncidents : '--' }}</h2>
                {{-- <span class="green">+3%</span> --}}
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card shadow-sm">
                <h6>Unresolved High-Severity</h6>
                <h2>{{ $unresolvedHighSeverity !== "" ? $unresolvedHighSeverity : '--' }}</h2>
                {{-- <span class="red">-1</span> --}}
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card shadow-sm">
                <h6>Unauthorized Access</h6>
                <h2>{{ $unauthorizedAccess }}</h2>
                {{-- <span class="green">+2%</span> --}}
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card shadow-sm">
                <h6>Active Unauthorized Access</h6>
                <h2>{{ $activeSecurity }}</h2>
                {{-- <span class="gray">0%</span> --}}
            </div>
        </div>
    </div>

    <div class="row">
        {{-- LEFT SIDE INCIDENT CARDS --}}
        <div class="col-md-4">
            <div class="incidents-list shadow-sm">
                <h5 class="mb-3">Incidents Feed</h5>
                <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search incident...">
                
                <div class="incident-items" id="incidentList">
                    @foreach($alerts as $alert)
                        <div class="incident-card incidentRow"
                             data-title="{{ strtolower($alert['title']) }}"
                             onclick="loadDetails({{ $alert['id'] }})">
                            <span class="code">{{ $alert['code'] }}</span>
                            <h6 style="color: black">{{ $alert['title'] }}</h6>
                            <span class="severity badge severity-{{ strtolower($alert['severity']) }}">
                                {{ $alert['severity'] }}
                            </span>
                            <div class="small text-muted mt-1">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- RIGHT SIDE TABLE --}}
        <div class="col-md-8">
            <div class="details-box shadow-sm">
                <h5 class="mb-3">Incident Details</h5>
                
                {{-- Regular incidents table --}}
                <table class="table table-striped" id="regularDetailsTable">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Event</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" class="text-center">Select an incident to view details</td>
                        </tr>
                    </tbody>
                </table>
                
                {{-- Unauthorized Access table (initially hidden) --}}
                <div id="unauthorizedTableSection" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 text-danger">
                            <i class="bx bx-shield-x"></i> Unauthorized Access Details
                        </h6>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-danger" onclick="refreshUnauthorizedData()">
                                    <i class="bx bx-refresh"></i> Refresh
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="exportUnauthorizedData()">
                                    <i class="bx bx-download"></i> Export
                                </button>
                            </div>

                            
                    </div>
                    
                    {{-- <div class="alert alert-info alert-sm mb-3">
                        <i class="bx bx-info-circle"></i> Showing <strong>all unauthorized access attempts</strong> from device logs (access_granted = 0)
                    </div> --}}
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered display nowrap" id="unauthorizedDetailsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Event</th>
                                    <th>Staff No</th>
                                    <th>Full Name</th>
                                    <th>IC No</th>
                                    <th>Company</th>
                                    <th>Contact No</th>
                                    <th>Person to Visit</th>
                                    <th>Reason</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody id="unauthorizedDetailsBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    {{-- <i class="bx bx-time"></i> Showing data from: <strong>All time</strong> --}}
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    Total records: <span id="totalRecords" class="badge bg-secondary">0</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>



                {{-- Forced Entry table (initially hidden) --}}
                <div id="forcedEntryTableSection" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 text-danger">
                            <i class="bx bx-shield-x"></i> Forced Entry Details (Acknowledged Logs)
                        </h6>
                        <button class="btn btn-sm btn-outline-danger" onclick="refreshForcedEntryData()">
                            <i class="bx bx-refresh"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered display nowrap" id="forcedEntryDetailsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Event</th>
                                    <th>Staff No</th>
                                    <th>Full Name</th>
                                    <th>IC No</th>
                                    <th>Company</th>
                                    <th>Contact No</th>
                                    <th>Person to Visit</th>
                                    <th>Reason</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody id="forcedEntryDetailsBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Overstay Visitors table (initially hidden) --}}
                <div id="overstayTableSection" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 text-warning">
                            <i class="bx bx-time"></i> Overstay Visitors Details
                        </h6>
                        <button class="btn btn-sm btn-outline-warning" onclick="refreshOverstayData()">
                            <i class="bx bx-refresh"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="alert alert-warning alert-sm mb-3">
                        <i class="bx bx-info-circle"></i> Showing visitors who have overstayed their expected visit time
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-bordered display nowrap" id="overstayDetailsTable">
                            <thead class="table-warning">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Event</th>
                                    <th>Staff No</th>
                                    <th>Full Name</th>
                                    <th>IC No</th>
                                    <th>Company</th>
                                    <th>Contact No</th>
                                    <th>Person to Visit</th>
                                    <th>Overstay Reason</th>
                                    <th>Location</th>
                                    <th>Check-in Time</th>
                                    <th>Overstay Duration</th>
                                </tr>
                            </thead>
                            <tbody id="overstayDetailsBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.severity-critical { background-color: #dc3545; color: white; }
.severity-high { background-color: #fd7e14; color: white; }
.severity-medium { background-color: #ffc107; color: black; }
.severity-low { background-color: #28a745; color: white; }

.green { color: #28a745; }
.red { color: #dc3545; }
.gray { color: #6c757d; }

.incident-card {
    cursor: pointer;
    transition: all 0.2s;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}
.incident-card:hover {
    background-color: #e9ecef;
    transform: translateX(3px);
}
#unauthorizedDetailsTable {
    font-size: 12px;
}
#unauthorizedDetailsTable th {
    white-space: nowrap;
    padding: 8px 5px;
}
#unauthorizedDetailsTable td {
    padding: 6px 5px;
    vertical-align: middle;
}
.table-dark {
    background-color: #dc3545 !important;
    color: white;
}
.alert-sm {
    padding: 8px 12px;
    font-size: 13px;
}
</style>
@endsection

@section('scripts')
<script>
let currentIncidentId = null;
let unauthorizedData = [];
let forcedEntryData = [];
let overstayData = [];


let unauthorizedTable = null;
let forcedEntryTable = null;
let overstayTable = null;

/* Load Incident Details */
function loadDetails(id) {
    currentIncidentId = id;
    
    if (id == 1) { // Forced Entry incident
        showForcedEntrySection();
        loadForcedEntryData();
    } else if (id == 2) { // Unauthorized Access incident
        showUnauthorizedSection();
        loadUnauthorizedData();
    } else if (id == 3) { // Visitor Overstay
        showOverstaySection();
        loadOverstayData();
    } else {
        showRegularSection();
        
        fetch("/vms/security-alerts/details/" + id)
            .then(res => res.json())
            .then(data => {
                let tbody = "";
                if (data && Array.isArray(data)) {
                    data.forEach(row => {
                        tbody += `
                            <tr>
                                <td>${row.time || 'N/A'}</td>
                                <td>${row.event || 'N/A'}</td>
                                <td>${row.user || 'N/A'}</td>
                            </tr>
                        `;
                    });
                } else {
                    tbody = `<tr><td colspan="3" class="text-center">No details available</td></tr>`;
                }
                document.querySelector("#regularDetailsTable tbody").innerHTML = tbody;
            })
            .catch(error => {
                console.error('Error loading details:', error);
                document.querySelector("#regularDetailsTable tbody").innerHTML = 
                    `<tr><td colspan="3" class="text-center text-danger">Error loading details</td></tr>`;
            });
    }
}

function showForcedEntrySection() {
    document.getElementById('regularDetailsTable').style.display = 'none';
    document.getElementById('unauthorizedTableSection').style.display = 'none';
    document.getElementById('overstayTableSection').style.display = 'none';
    document.getElementById('forcedEntryTableSection').style.display = 'block';
    
    // Initialize DataTable if not already done
    if (!forcedEntryTable && document.getElementById('forcedEntryDetailsTable')) {
        initializeForcedEntryTable();
    }
}

function showOverstaySection() {
    document.getElementById('regularDetailsTable').style.display = 'none';
    document.getElementById('unauthorizedTableSection').style.display = 'none';
    document.getElementById('forcedEntryTableSection').style.display = 'none';
    document.getElementById('overstayTableSection').style.display = 'block';
    
    // Initialize DataTable if not already done
    if (!overstayTable && document.getElementById('overstayDetailsTable')) {
        initializeOverstayTable();
    }
}

function showUnauthorizedSection() {
    document.getElementById('regularDetailsTable').style.display = 'none';
    document.getElementById('forcedEntryTableSection').style.display = 'none';
    document.getElementById('overstayTableSection').style.display = 'none';
    document.getElementById('unauthorizedTableSection').style.display = 'block';
    
    // Initialize DataTable if not already done
    if (!unauthorizedTable && document.getElementById('unauthorizedDetailsTable')) {
        initializeUnauthorizedTable();
    }
}

function initializeForcedEntryTable() {
    if ($.fn.DataTable.isDataTable('#forcedEntryDetailsTable')) {
        forcedEntryTable = $('#forcedEntryDetailsTable').DataTable();
        return;
    }
    
    forcedEntryTable = $('#forcedEntryDetailsTable').DataTable({
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        responsive: true,
        order: [[0, 'desc']], // Sort by Date & Time descending
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search forced entry...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });
}




function showRegularSection() {
    document.getElementById('regularDetailsTable').style.display = 'table';
    document.getElementById('unauthorizedTableSection').style.display = 'none';
    document.getElementById('forcedEntryTableSection').style.display = 'none';
    document.getElementById('overstayTableSection').style.display = 'none';
}

function initializeUnauthorizedTable() {
    if ($.fn.DataTable.isDataTable('#unauthorizedDetailsTable')) {
        unauthorizedTable = $('#unauthorizedDetailsTable').DataTable();
        return;
    }
    
    unauthorizedTable = $('#unauthorizedDetailsTable').DataTable({
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        responsive: true,
        order: [[0, 'desc']], // Sort by Date & Time descending
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search unauthorized access...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });
}

function initializeOverstayTable() {
    if ($.fn.DataTable.isDataTable('#overstayDetailsTable')) {
        overstayTable = $('#overstayDetailsTable').DataTable();
        return;
    }
    
    overstayTable = $('#overstayDetailsTable').DataTable({
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        responsive: true,
        order: [[0, 'desc']], // Sort by Date & Time descending
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search overstay visitors...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });
}




// function showRegularSection() {
//     document.getElementById('regularDetailsTable').style.display = 'table';
//     document.getElementById('unauthorizedTableSection').style.display = 'none';
// }

function loadUnauthorizedData() {
    const tbody = document.getElementById('unauthorizedDetailsBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="10" class="text-center">
                <div class="spinner-border spinner-border-sm text-danger" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                Loading unauthorized access data...
            </td>
        </tr>
    `;
    
    fetch('/vms/security-alerts/details/2')
        .then(response => response.json())
        .then(data => {
            unauthorizedData = data;
            
            if (unauthorizedTable) {
                unauthorizedTable.destroy();
                unauthorizedTable = null;
            }
            
            if (data && Array.isArray(data) && data.length > 0) {
                let html = '';
                data.forEach(item => {
                    html += `
                        <tr>
                            <td><small class="text-muted">${item.time || 'N/A'}</small></td>
                            <td><span class="badge bg-danger">${item.event || 'Unauthorized'}</span></td>
                            <td><strong>${item.staff_no || 'N/A'}</strong></td>
                            <td>${item.full_name || 'Unknown'}</td>
                            <td><small>${item.ic_no || 'N/A'}</small></td>
                            <td>${item.company_name || 'N/A'}</td>
                            <td><small>${item.contact_no || 'N/A'}</small></td>
                            <td>${item.person_visited || 'N/A'}</td>
                            <td><small class="text-muted">${item.reason || 'N/A'}</small></td>
                            <td><small>${item.location || 'Unknown'}</small></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                
                // Update total records
                document.getElementById('totalRecords').textContent = data.length;
                
                // Initialize DataTable
                setTimeout(() => {
                    initializeUnauthorizedTable();
                }, 100);
                
                showToast(`Loaded ${data.length} unauthorized access attempts`, 'success');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-muted">
                            <i class="bx bx-check-circle"></i> No unauthorized access attempts found
                        </td>
                    </tr>
                `;
                document.getElementById('totalRecords').textContent = '0';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-danger">
                        Error loading data
                    </td>
                </tr>
            `;
        });
}

function loadForcedEntryData() {
    const tbody = document.getElementById('forcedEntryDetailsBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="10" class="text-center">
                <div class="spinner-border spinner-border-sm text-danger" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                Loading forced entry data (acknowledge = 1)...
            </td>
        </tr>
    `;
    
    fetch('/vms/security-alerts/details/1')
        .then(response => response.json())
        .then(data => {
            forcedEntryData = data;
            
            if (forcedEntryTable) {
                forcedEntryTable.destroy();
                forcedEntryTable = null;
            }
            
            let html = '';
            if (data && Array.isArray(data)) {
                data.forEach(item => {
                    html += `
                        <tr>
                            <td><small class="text-muted">${item.time || 'N/A'}</small></td>
                            <td><span class="badge bg-danger">${item.event || 'Forced Entry'}</span></td>
                            <td><strong>${item.staff_no || 'N/A'}</strong></td>
                            <td>${item.full_name || 'Unknown'}</td>
                            <td><small>${item.ic_no || 'N/A'}</small></td>
                            <td>${item.company_name || 'N/A'}</td>
                            <td><small>${item.contact_no || 'N/A'}</small></td>
                            <td>${item.person_visited || 'N/A'}</td>
                            <td><small class="text-muted">${item.reason || 'N/A'}</small></td>
                            <td><small>${item.location || 'Unknown'}</small></td>
                        </tr>
                    `;
                });
            }
            
            tbody.innerHTML = html || `
                <tr>
                    <td colspan="10" class="text-center text-muted">
                        No forced entry data found (acknowledge = 1)
                    </td>
                </tr>
            `;
            
            // Initialize DataTable
            setTimeout(() => {
                initializeForcedEntryTable();
            }, 100);
            
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-danger">
                        Error loading data
                    </td>
                </tr>
            `;
        });
}

function loadOverstayData() {
    const tbody = document.getElementById('overstayDetailsBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="12" class="text-center">
                <div class="spinner-border spinner-border-sm text-warning" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                Loading overstay visitors data...
            </td>
        </tr>
    `;
    
    fetch('/vms/security-alerts/details/3')
        .then(response => response.json())
        .then(data => {
            overstayData = data;
            
            if (overstayTable) {
                overstayTable.destroy();
                overstayTable = null;
            }
            
            let html = '';
            if (data && Array.isArray(data)) {
                data.forEach(item => {
                    html += `
                        <tr>
                            <td><small class="text-muted">${item.time || 'N/A'}</small></td>
                            <td><span class="badge bg-warning">${item.event || 'Overstay'}</span></td>
                            <td><strong>${item.staff_no || 'N/A'}</strong></td>
                            <td>${item.full_name || 'Unknown'}</td>
                            <td><small>${item.ic_no || 'N/A'}</small></td>
                            <td>${item.company_name || 'N/A'}</td>
                            <td><small>${item.contact_no || 'N/A'}</small></td>
                            <td>${item.person_visited || 'N/A'}</td>
                            <td><small class="text-muted">${item.reason || 'N/A'}</small></td>
                            <td><small>${item.location || 'Unknown'}</small></td>
                            <td><small>${item.check_in_time || 'N/A'}</small></td>
                            <td><small class="text-danger">${item.overstay_duration || 'N/A'}</small></td>
                        </tr>
                    `;
                });
            }
            
            tbody.innerHTML = html || `
                <tr>
                    <td colspan="12" class="text-center text-muted">
                        No overstay visitors found
                    </td>
                </tr>
            `;
            
            // Initialize DataTable
            setTimeout(() => {
                initializeOverstayTable();
            }, 100);
            
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" class="text-center text-danger">
                        Error loading data
                    </td>
                </tr>
            `;
        });
}

/* Refresh Unauthorized Data */
function refreshUnauthorizedData() {
    if (currentIncidentId == 2) {
        showToast('Refreshing unauthorized access data...', 'info');
        loadUnauthorizedData();
    }
}

function refreshForcedEntryData() {
    if (currentIncidentId == 1) {
        showToast('Refreshing forced entry data...', 'info');
        loadForcedEntryData();
    }
}

function refreshOverstayData() {
    if (currentIncidentId == 3) {
        showToast('Refreshing overstay visitors data...', 'info');
        loadOverstayData();
    }
}

function exportUnauthorizedData() {
    if (unauthorizedData.length === 0) {
        showToast('No data to export', 'warning');
        return;
    }
    
    let csv = 'Date & Time,Event,Staff No,Full Name,IC No,Company,Contact No,Person to Visit,Reason,Location\n';
    
    unauthorizedData.forEach(item => {
        csv += `"${item.time || ''}","${item.event || ''}","${item.staff_no || ''}","${item.full_name || ''}","${item.ic_no || ''}","${item.company_name || ''}","${item.contact_no || ''}","${item.person_visited || ''}","${item.reason || ''}","${item.location || ''}"\n`;
    });
    
    downloadCSV(csv, `unauthorized_access_${new Date().toISOString().split('T')[0]}.csv`);
    showToast(`Exported ${unauthorizedData.length} records to CSV`, 'success');
}

function exportForcedEntryData() {
    if (forcedEntryData.length === 0) {
        showToast('No data to export', 'warning');
        return;
    }
    
    let csv = 'Date & Time,Event,Staff No,Full Name,IC No,Company,Contact No,Person to Visit,Reason,Location\n';
    
    forcedEntryData.forEach(item => {
        csv += `"${item.time || ''}","${item.event || ''}","${item.staff_no || ''}","${item.full_name || ''}","${item.ic_no || ''}","${item.company_name || ''}","${item.contact_no || ''}","${item.person_visited || ''}","${item.reason || ''}","${item.location || ''}"\n`;
    });
    
    downloadCSV(csv, `forced_entry_${new Date().toISOString().split('T')[0]}.csv`);
    showToast(`Exported ${forcedEntryData.length} records to CSV`, 'success');
}

function exportOverstayData() {
    if (overstayData.length === 0) {
        showToast('No data to export', 'warning');
        return;
    }
    
    let csv = 'Date & Time,Event,Staff No,Full Name,IC No,Company,Contact No,Person to Visit,Reason,Location,Check-in Time,Overstay Duration\n';
    
    overstayData.forEach(item => {
        csv += `"${item.time || ''}","${item.event || ''}","${item.staff_no || ''}","${item.full_name || ''}","${item.ic_no || ''}","${item.company_name || ''}","${item.contact_no || ''}","${item.person_visited || ''}","${item.reason || ''}","${item.location || ''}","${item.check_in_time || ''}","${item.overstay_duration || ''}"\n`;
    });
    
    downloadCSV(csv, `overstay_visitors_${new Date().toISOString().split('T')[0]}.csv`);
    showToast(`Exported ${overstayData.length} records to CSV`, 'success');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}


/* Search Function */
document.getElementById("searchInput").addEventListener("keyup", function () {
    let search = this.value.toLowerCase();
    document.querySelectorAll(".incidentRow").forEach(row => {
        let title = row.getAttribute("data-title");
        let text = row.textContent.toLowerCase();
        
        if (title.includes(search) || text.includes(search)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});

/* Toast notification function */
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.custom-toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = `custom-toast alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'} alert-dismissible fade show`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
        min-width: 250px;
        max-width: 400px;
    `;
    
    toast.innerHTML = `
        <i class="bx ${type === 'success' ? 'bx-check-circle' : type === 'error' ? 'bx-error' : type === 'warning' ? 'bx-error-circle' : 'bx-info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 4000);
}

// Initialize - Show regular section by default
document.addEventListener('DOMContentLoaded', function() {
    showRegularSection();
});
</script>
@endsection
