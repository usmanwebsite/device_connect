@extends('layout.main_layout')

@section('content')
<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="content-card">
                {{-- Header --}}
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="mb-1">ACCESS LOGS REPORT</h2>
                        <p class="text-muted mb-0">Staff Access History</p>
                    </div>
                </div>

                {{-- Filter Form - RESPONSIVE DESIGN --}}
                <div class="row mb-4 filter-form-mobile">
                    <div class="col-12 col-sm-6 col-md-3 mb-3 mb-md-0">
                        <label for="fromDate" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="fromDate" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-12 col-sm-6 col-md-3 mb-3 mb-md-0">
                        <label for="toDate" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="toDate" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <label class="form-label">Select Locations</label>
                        
                        {{-- SIMPLE DROPDOWN - NO BOOTSTRAP ATTRIBUTES --}}
                        <div class="custom-dropdown" id="locationDropdownContainer">
                            <button class="dropdown-toggle form-control text-start" 
                                    type="button" 
                                    id="locationDropdownBtn">
                                <span class="dropdown-text" id="locationPlaceholder">-- Select Locations --</span>
                                <i class="fas fa-chevron-down float-end"></i>
                            </button>
                            <div class="dropdown-menu" id="locationDropdownMenu">
                                <div class="dropdown-content-report p-3">
                                    {{-- Select All Option --}}
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAllLocations">
                                        <label class="form-check-label fw-bold" for="selectAllLocations">
                                            Select All Locations
                                        </label>
                                    </div>
                                    <hr class="my-2">
                                    
                                    {{-- Location Checkboxes --}}
                                    <div id="locationCheckboxes">
                                        @foreach($locations as $loc)
                                            <div class="form-check">
                                                <input class="form-check-input location-checkbox" 
                                                       type="checkbox" 
                                                       value="{{ $loc->name }}" 
                                                       id="loc_{{ $loc->id }}">
                                                <label class="form-check-label" for="loc_{{ $loc->id }}">
                                                    {{ $loc->name }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Selected Locations Display --}}
                        <div id="selectedLocations" class="mt-2 small text-muted" style="min-height: 20px;"></div>
                    </div>
                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="loadReport()" style="margin-bottom: 30px">
                            <i class="fas fa-search"></i> <span class="d-none d-sm-inline">Generate</span>
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

                {{-- Report Summary Cards - RESPONSIVE --}}
                <div id="reportSummary" class="row mb-4 d-none">
                    <div class="col-12 col-sm-6 col-md-3 mb-3 mb-md-0">
                        <div class="stat-card">
                            <h2 id="totalStaffCount">0</h2>
                            <p>Total VISITOR</p>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-9 d-flex align-items-center">
                        <p class="mb-0 text-center text-md-start" id="reportInfo"></p>
                    </div>
                </div>

                {{-- Main Table with Fixed Header and Proper Scrolling --}}

<div class="table-container-wrapper d-none" id="staffTableContainer">
    <div class="table-responsive">
        <table class="table table-hover table-striped table-fixed-header" id="staffTable">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Code</th>
                    <th>Visitor Name</th>
                    <th>Person Visited</th>
                    <th>Contact No</th>
                    <th>IC No</th>
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
</div>

                {{-- Pagination and Footer - RESPONSIVE --}}
                <div id="tableFooter" class="row mt-3 d-none">
                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                        <div class="d-flex align-items-center justify-content-center justify-content-md-start">
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
                    <div class="col-12 col-md-6 text-center text-md-end">
                        <div id="paginationInfo" class="text-muted"></div>
                    </div>
                </div>

                {{-- No Data Message --}}
                <div id="noDataMessage" class="text-center d-none">
                    <div class="alert alert-info">
                        <h5>No data found</h5>
                        <p>No access logs found for the selected date range and locations.</p>
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
                                        <th>Type</th>
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
@endsection

@section('scripts')
<script>
// Custom Dropdown Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Get dropdown elements
    const dropdownBtn = document.getElementById('locationDropdownBtn');
    const dropdownMenu = document.getElementById('locationDropdownMenu');
    const dropdownContainer = document.getElementById('locationDropdownContainer');
    
    if (dropdownBtn && dropdownMenu) {
        // Toggle dropdown on button click
        dropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Close all other dropdowns
            closeAllDropdownsExcept(this);
            
            // Toggle current dropdown
            const isShowing = dropdownMenu.classList.contains('show');
            if (isShowing) {
                dropdownMenu.classList.remove('show');
                dropdownBtn.classList.remove('active');
            } else {
                dropdownMenu.classList.add('show');
                dropdownBtn.classList.add('active');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdownContainer.contains(e.target)) {
                dropdownMenu.classList.remove('show');
                dropdownBtn.classList.remove('active');
            }
        });
        
        // Prevent dropdown from closing when clicking inside
        dropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdownMenu.classList.remove('show');
                dropdownBtn.classList.remove('active');
            }
        });
    }
    
    function closeAllDropdownsExcept(currentElement) {
        // You can add logic to close other dropdowns if needed
        const allDropdowns = document.querySelectorAll('.dropdown-menu');
        const allButtons = document.querySelectorAll('.dropdown-toggle');
        
        allDropdowns.forEach(menu => {
            if (menu !== dropdownMenu) {
                menu.classList.remove('show');
            }
        });
        
        allButtons.forEach(btn => {
            if (btn !== dropdownBtn) {
                btn.classList.remove('active');
            }
        });
    }
    
    // Update selected locations display
    function updateSelectedLocationsDisplay() {
        const selectedLocations = [];
        document.querySelectorAll('.location-checkbox:checked').forEach(checkbox => {
            selectedLocations.push(checkbox.value);
        });
        
        const displayElement = document.getElementById('selectedLocations');
        const placeholderElement = document.getElementById('locationPlaceholder');
        
        if (selectedLocations.length > 0) {
            const displayText = selectedLocations.length > 2 
                ? `${selectedLocations.length} locations selected` 
                : selectedLocations.join(', ');
            
            displayElement.textContent = displayText;
            placeholderElement.textContent = displayText;
        } else {
            displayElement.textContent = '';
            placeholderElement.textContent = '-- Select Locations --';
        }
    }
    
    // Select All functionality
    const selectAllCheckbox = document.getElementById('selectAllLocations');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.location-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedLocationsDisplay();
        });
    }
    
    // Individual checkbox change
    document.querySelectorAll('.location-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedLocationsDisplay();
            
            // Update "Select All" checkbox state
            const allCheckboxes = document.querySelectorAll('.location-checkbox');
            const selectAll = document.getElementById('selectAllLocations');
            const checkedCount = document.querySelectorAll('.location-checkbox:checked').length;
            
            selectAll.checked = checkedCount === allCheckboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
        });
    });
    
    // Initialize display on page load
    updateSelectedLocationsDisplay();
    
    // Mobile menu toggle functionality
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.sidebar');
        const mobileToggle = document.getElementById('mobileMenuToggle');
        
        if (window.innerWidth <= 768 && 
            !sidebar.contains(e.target) && 
            !mobileToggle.contains(e.target) &&
            sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });
});

// Rest of your existing JavaScript functions
// ... [Keep all your existing functions like loadReport, displayReportData, etc.]

let currentPage = 1;
let itemsPerPage = 10;
let allStaffData = [];
let visitorDetailsCache = {};

// Get selected locations
function getSelectedLocations() {
    const selectedLocations = [];
    document.querySelectorAll('.location-checkbox:checked').forEach(checkbox => {
        selectedLocations.push(checkbox.value);
    });
    return selectedLocations;
}

// Update selected locations display (already defined above)

// Select All functionality (already defined above)

// Individual checkbox change (already defined above)

function loadReport() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const selectedLocations = getSelectedLocations();

    if (!fromDate || !toDate) {
        alert('Please select both from date and to date');
        return;
    }

    if (selectedLocations.length === 0) {
        alert('Please select at least one location');
        return;
    }

    if (new Date(fromDate) > new Date(toDate)) {
        alert('From date cannot be greater than To date');
        return;
    }

    // Show loading
    document.getElementById('loadingSpinner').classList.remove('d-none');
    document.getElementById('staffTableContainer').classList.add('d-none');
    document.getElementById('noDataMessage').classList.add('d-none');
    document.getElementById('reportSummary').classList.add('d-none');
    document.getElementById('tableFooter').classList.add('d-none');

    // Make API call with date range and multiple locations
    fetch('{{ route("reports.access-logs.data") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            from_date: fromDate,
            to_date: toDate,
            locations: selectedLocations
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').classList.add('d-none');

        if (data.success) {
            displayReportData(data, fromDate, toDate, selectedLocations);
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

function displayReportData(data, fromDate, toDate, selectedLocations) {
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
            logs: staffLogs,
            visitorDetails: null
        };
    });

    // Update summary
    document.getElementById('totalStaffCount').textContent = totalStaff;
    
    const locationText = selectedLocations.length > 2 
        ? `${selectedLocations.length} locations` 
        : selectedLocations.join(', ');
    
    document.getElementById('reportSummary').classList.remove('d-none');

    // Fetch visitor details for all staff
    fetchAllVisitorDetails().then(() => {
        currentPage = 1;
        displayCurrentPage();
        document.getElementById('tableFooter').classList.remove('d-none');
    });
}

// Function to fetch visitor details for all staff numbers
async function fetchAllVisitorDetails() {
    const promises = allStaffData.map(async (staff) => {
        if (visitorDetailsCache[staff.staffNo]) {
            staff.visitorDetails = visitorDetailsCache[staff.staffNo];
            return;
        }

        try {
            const response = await fetch(`http://127.0.0.1:8080/api/vendorpass/get-visitor-details?staffNo=${staff.staffNo}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                staff.visitorDetails = data.data;
                visitorDetailsCache[staff.staffNo] = data.data;
            } else {
                staff.visitorDetails = {
                    fullName: 'N/A',
                    personVisited: 'N/A',
                    contactNo: 'N/A',
                    icNo: 'N/A'
                };
            }
        } catch (error) {
            console.error(`Error fetching details for ${staff.staffNo}:`, error);
            staff.visitorDetails = {
                fullName: 'N/A',
                personVisited: 'N/A',
                contactNo: 'N/A',
                icNo: 'N/A'
            };
        }
    });

    await Promise.all(promises);
}

function displayCurrentPage() {
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentData = allStaffData.slice(startIndex, endIndex);

    const tableBody = document.getElementById('staffTableBody');
    tableBody.innerHTML = '';

    if (currentData.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="10" class="text-center">No data available</td>`;
        tableBody.appendChild(row);
        return;
    }

    currentData.forEach((staff, index) => {
        const rowNumber = startIndex + index + 1;
        const row = document.createElement('tr');
        row.className = 'staff-row';
        
        const visitorDetails = staff.visitorDetails || {
            fullName: 'Loading...',
            personVisited: 'Loading...',
            contactNo: 'Loading...',
            icNo: 'Loading...'
        };
        
        row.innerHTML = `
            <td>${rowNumber}</td>
            <td>${staff.staffNo || 'N/A'}</td>
            <td>${visitorDetails.fullName || 'N/A'}</td>
            <td>${visitorDetails.personVisited || 'N/A'}</td>
            <td>${visitorDetails.contactNo || 'N/A'}</td>
            <td>${visitorDetails.icNo || 'N/A'}</td>
            <td>${staff.totalAccess}</td>
            <td>${staff.firstAccess ? formatDateTime(staff.firstAccess) : 'N/A'}</td>
            <td>${staff.lastAccess ? formatDateTime(staff.lastAccess) : 'N/A'}</td>
            <td>
                <button class="btn btn-sm btn-info" onclick="viewStaffMovement('${staff.staffNo}')">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        tableBody.appendChild(row);
    });

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
    const modal = new bootstrap.Modal(document.getElementById('staffMovementModal'));
    document.getElementById('movementModalTitle').textContent = `Movement History - ${staffNo}`;
    document.getElementById('modalStaffNo').textContent = staffNo;
    
    document.getElementById('movementLoading').classList.remove('d-none');
    document.getElementById('movementContent').classList.add('d-none');
    document.getElementById('noMovementMessage').classList.add('d-none');

    modal.show();

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
        
        // Determine badge class based on type
        let badgeClass = 'badge bg-secondary';
        if (movement.type === 'Checkin') {
            badgeClass = 'badge bg-primary';
        } else if (movement.type === 'Checkout') {
            badgeClass = 'badge bg-warning text-dark';
        }
        
        row.innerHTML = `
            <td>${movement.date_time || 'N/A'}</td>
            <td>${movement.location || 'N/A'}</td>
            <td>
                <span class="badge ${movement.access_granted === 'Yes' ? 'bg-success' : 'bg-danger'}">
                    ${movement.access_granted || 'N/A'}
                </span>
            </td>
            <td>${movement.reason || 'N/A'}</td>
            <td>
                <span class="${badgeClass}">
                    ${movement.type || 'N/A'}
                </span>
            </td>
            <td>${movement.action || 'N/A'}</td>
        `;
        tableBody.appendChild(row);
    });

    document.getElementById('movementContent').classList.remove('d-none');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('itemsPerPage').addEventListener('change', function() {
        itemsPerPage = parseInt(this.value);
        currentPage = 1;
        if (allStaffData.length > 0) {
            displayCurrentPage();
        }
    });
});
</script>
@endsection

