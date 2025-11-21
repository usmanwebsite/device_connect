@extends('layout.main_layout')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="content-card">
                {{-- Header similar to image --}}
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="mb-1">ACCESS LOGS REPORT</h2>
                        <p class="text-muted mb-0">Staff Access History</p>
                    </div>
                </div>

                {{-- Filter Form --}}
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="date" class="form-label">Select Date</label>
                        <input type="date" class="form-control" id="date" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="location" class="form-label">Select Location</label>
                        <select class="form-control" id="location">
                            <option value="">-- Select Location --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->name }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="loadReport()">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                </div>

                {{-- Loading Spinner --}}
                <div id="loadingSpinner" class="text-center d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading report data...</p>
                </div>

                {{-- Report Summary Cards --}}
                <div id="reportSummary" class="row mb-4 d-none">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h2 id="totalStaffCount">0</h2>
                            <p>Total Staff</p>
                        </div>
                    </div>
                    <div class="col-md-9 d-flex align-items-center">
                        <p class="mb-0" id="reportInfo"></p>
                    </div>
                </div>

                {{-- Main Table similar to image --}}
                <div class="table-responsive d-none" id="staffTableContainer">
                    <table class="table table-dark table-hover" id="staffTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Staff Number</th>
                                <th>Total Access</th>
                                <th>First Access</th>
                                <th>Last Access</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staffTableBody">
                            {{-- Dynamic content --}}
                        </tbody>
                    </table>
                </div>

                {{-- Pagination and Footer similar to image --}}
                <div id="tableFooter" class="row mt-3 d-none">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-3">Showing </span>
                            <select class="form-select form-select-sm" style="width: auto;" id="itemsPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span class="text-muted ms-2">items per page</span>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div id="paginationInfo" class="text-muted"></div>
                    </div>
                </div>

                {{-- No Data Message --}}
                <div id="noDataMessage" class="text-center d-none">
                    <div class="alert alert-info">
                        <h5>No data found</h5>
                        <p>No access logs found for the selected date and location.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Staff Movement Modal --}}
    <div class="modal fade" id="staffMovementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="movementModalTitle">Staff Movement History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="movementLoading" class="text-center d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading movement history...</p>
                    </div>
                    <div id="movementContent" class="d-none">
                        <p><strong>Staff No:</strong> <span id="modalStaffNo"></span></p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Access</th>
                                        <th>Reason</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="movementTableBody">
                                    {{-- Dynamic content --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="noMovementMessage" class="text-center d-none">
                        <p>No movement history found for this staff.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let itemsPerPage = 10;
let allStaffData = [];

function loadReport() {
    const date = document.getElementById('date').value;
    const location = document.getElementById('location').value;

    if (!date || !location) {
        alert('Please select both date and location');
        return;
    }

    // Show loading
    document.getElementById('loadingSpinner').classList.remove('d-none');
    document.getElementById('staffTableContainer').classList.add('d-none');
    document.getElementById('noDataMessage').classList.add('d-none');
    document.getElementById('reportSummary').classList.add('d-none');
    document.getElementById('tableFooter').classList.add('d-none');

    // Make API call
    fetch('{{ route("reports.access-logs.data") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            date: date,
            location: location
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').classList.add('d-none');

        if (data.success) {
            displayReportData(data, date, location);
        } else {
            showNoData();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingSpinner').classList.add('d-none');
        alert('Error loading report data');
    });
}

function displayReportData(data, date, location) {
    const staffList = data.staff_list;
    const accessLogs = data.access_logs;
    const totalStaff = data.total_staff;

    if (totalStaff === 0) {
        showNoData();
        return;
    }

    // Prepare staff data with access information
    allStaffData = staffList.map(staffNo => {
        const staffLogs = accessLogs[staffNo] || [];
        const accessTimes = staffLogs.map(log => new Date(log.created_at));
        
        return {
            staffNo: staffNo,
            totalAccess: staffLogs.length,
            firstAccess: accessTimes.length > 0 ? new Date(Math.min(...accessTimes)) : null,
            lastAccess: accessTimes.length > 0 ? new Date(Math.max(...accessTimes)) : null,
            logs: staffLogs
        };
    });

    // Update summary
    document.getElementById('totalStaffCount').textContent = totalStaff;
    document.getElementById('reportInfo').textContent = 
        `Showing ${totalStaff} staff members for ${date} at ${location}`;
    document.getElementById('reportSummary').classList.remove('d-none');

    // Display first page
    currentPage = 1;
    displayCurrentPage();
    document.getElementById('tableFooter').classList.remove('d-none');
}

function displayCurrentPage() {
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentData = allStaffData.slice(startIndex, endIndex);

    // Populate staff table
    const tableBody = document.getElementById('staffTableBody');
    tableBody.innerHTML = '';

    currentData.forEach((staff, index) => {
        const rowNumber = startIndex + index + 1;
        const row = document.createElement('tr');
        row.className = 'staff-row';
        
        row.innerHTML = `
            <td>${rowNumber}</td>
            <td>${staff.staffNo}</td>
            <td>${staff.totalAccess}</td>
            <td>${staff.firstAccess ? formatDateTime(staff.firstAccess) : 'N/A'}</td>
            <td>${staff.lastAccess ? formatDateTime(staff.lastAccess) : 'N/A'}</td>
            <td>
                <button class="btn btn-sm btn-info" onclick="viewStaffMovement('${staff.staffNo}')">
                    <i class="fas fa-eye"></i> View Movement
                </button>
            </td>
        `;
        tableBody.appendChild(row);
    });

    // Update pagination info
    updatePaginationInfo();
    document.getElementById('staffTableContainer').classList.remove('d-none');
}

function formatDateTime(date) {
    return new Date(date).toLocaleString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    }).replace(',', '');
}

function updatePaginationInfo() {
    const totalItems = allStaffData.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    
    document.getElementById('paginationInfo').textContent = 
        `Showing ${startItem} to ${endItem} of ${totalItems} entries`;
}

function showNoData() {
    document.getElementById('noDataMessage').classList.remove('d-none');
    document.getElementById('staffTableContainer').classList.add('d-none');
    document.getElementById('reportSummary').classList.add('d-none');
    document.getElementById('tableFooter').classList.add('d-none');
}

function viewStaffMovement(staffNo) {
    // Show modal and loading
    const modal = new bootstrap.Modal(document.getElementById('staffMovementModal'));
    document.getElementById('movementModalTitle').textContent = `Movement History - ${staffNo}`;
    document.getElementById('modalStaffNo').textContent = staffNo;
    
    document.getElementById('movementLoading').classList.remove('d-none');
    document.getElementById('movementContent').classList.add('d-none');
    document.getElementById('noMovementMessage').classList.add('d-none');

    modal.show();

    // Fetch movement data
    fetch(`/reports/staff-movement/${staffNo}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('movementLoading').classList.add('d-none');

            if (data.success && data.movement_history.length > 0) {
                displayMovementHistory(data.movement_history);
            } else {
                document.getElementById('noMovementMessage').classList.remove('d-none');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('movementLoading').classList.add('d-none');
            document.getElementById('noMovementMessage').classList.remove('d-none');
        });
}

function displayMovementHistory(movementHistory) {
    const tableBody = document.getElementById('movementTableBody');
    tableBody.innerHTML = '';

    movementHistory.forEach(movement => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${movement.date_time}</td>
            <td>${movement.location}</td>
            <td>
                <span class="badge ${movement.access_granted === 'Yes' ? 'bg-success' : 'bg-danger'}">
                    ${movement.access_granted}
                </span>
            </td>
            <td>${movement.reason}</td>
            <td>${movement.action}</td>
        `;
        tableBody.appendChild(row);
    });

    document.getElementById('movementContent').classList.remove('d-none');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Items per page change
    document.getElementById('itemsPerPage').addEventListener('change', function() {
        itemsPerPage = parseInt(this.value);
        currentPage = 1;
        if (allStaffData.length > 0) {
            displayCurrentPage();
        }
    });
});
</script>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Font Awesome -->
<script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
@endsection

