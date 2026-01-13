@extends('layout.main_layout')

@section('content')
@php
    $domain = request()->getSchemeAndHttpHost();
@endphp

<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="content-card" style="height: 100%">
                {{-- Header --}}
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="mb-1">ACCESS LOGS REPORT</h2>
                        <p class="text-muted mb-0">Staff Access History</p>
                    </div>
                </div>

                <div class="row mb-4 filter-form-mobile">
                    {{-- From Date with Time --}}
                    <div class="col-12 col-sm-6 col-md-3 mb-3 mb-md-0">
                        <label for="fromDate" class="form-label">From Date & Time</label>
                        <input type="datetime-local" class="form-control datetime-picker" id="fromDate" 
                            value="{{ now()->format('Y-m-d\T00:00') }}">
                        <small class="text-muted">Start date and time</small>
                    </div>
                    
                    {{-- To Date with Time --}}
                    <div class="col-12 col-sm-6 col-md-3 mb-3 mb-md-0">
                        <label for="toDate" class="form-label">To Date & Time</label>
                        <input type="datetime-local" class="form-control datetime-picker" id="toDate" 
                            value="{{ now()->format('Y-m-d\T23:59') }}">
                        <small class="text-muted">End date and time</small>
                    </div>
                    
                    {{-- Locations Dropdown (Same as before) --}}
                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <label class="form-label">Select Locations</label>
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
                {{-- Main Table with DataTable --}}
                <div class="table-container-wrapper d-none" id="staffTableContainer">
                    <div class="table-responsive" style="height: 1500px !important">
                        <table class="table table-hover table-striped" id="staffTable">
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
                    <div class="visitor-info-section mb-3" id="visitorInfoSection" style="display: none;">
                        <p><strong>Full Name:</strong> <span id="modalVisitorName"></span></p>
                        <p><strong>IC No:</strong> <span id="modalVisitorIC"></span></p>
                    </div>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="width: 150px !important; height: 47px">Close</button>
                <button type="button" class="btn btn-primary" id="modalViewChronologyBtn" style="width: 200px !important; height: 47px">View Chronology</button>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@section('scripts')
<script>
    const API_BASE = window.location.protocol + '//' + window.location.hostname + ':8080';
</script>
<script>
// Global Variables
let dataTable = null;
let allStaffData = [];
let visitorDetailsCache = {};
let currentModalStaffNo = null;
let currentModalVisitorDetails = null;

// Request tracking variables
let pendingRequests = new Map();
let lastDrawTime = 0;
const DRAW_COOLDOWN = 1000; // 1 second cooldown between draw calls

// Custom Dropdown Functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropdownBtn = document.getElementById('locationDropdownBtn');
    const dropdownMenu = document.getElementById('locationDropdownMenu');
    const dropdownContainer = document.getElementById('locationDropdownContainer');
    
    if (dropdownBtn && dropdownMenu) {
        dropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeAllDropdownsExcept(this);
            
            const isShowing = dropdownMenu.classList.contains('show');
            if (isShowing) {
                dropdownMenu.classList.remove('show');
                dropdownBtn.classList.remove('active');
            } else {
                dropdownMenu.classList.add('show');
                dropdownBtn.classList.add('active');
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!dropdownContainer.contains(e.target)) {
                dropdownMenu.classList.remove('show');
                dropdownBtn.classList.remove('active');
            }
        });
        
        dropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdownMenu.classList.remove('show');
                dropdownBtn.classList.remove('active');
            }
        });
    }
    
    function closeAllDropdownsExcept(currentElement) {
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
    
    document.querySelectorAll('.location-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedLocationsDisplay();
            
            const allCheckboxes = document.querySelectorAll('.location-checkbox');
            const selectAll = document.getElementById('selectAllLocations');
            const checkedCount = document.querySelectorAll('.location-checkbox:checked').length;
            
            selectAll.checked = checkedCount === allCheckboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
        });
    });
    
    updateSelectedLocationsDisplay();
    
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        });
    }
    
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

function getSelectedLocations() {
    const selectedLocations = [];
    document.querySelectorAll('.location-checkbox:checked').forEach(checkbox => {
        selectedLocations.push(checkbox.value);
    });
    return selectedLocations;
}

function formatDateTimeDisplay(datetimeString) {
    const date = new Date(datetimeString);
    return date.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
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

function initializeDataTable() {
    if (dataTable) {
        dataTable.destroy();
        dataTable = null;
    }
    
    dataTable = $('#staffTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": '{{ route("reports.access-logs.data") }}',
            "type": "POST",
            "data": function (d) {
                // Add your custom filters
                d.from_date = document.getElementById('fromDate').value;
                d.to_date = document.getElementById('toDate').value;
                d.locations = getSelectedLocations();
                d._token = '{{ csrf_token() }}';
            },
            "error": function (xhr, error, thrown) {
                console.error('DataTables Ajax Error:', xhr, error, thrown);
                alert('Error loading data. Please try again.');
            }
        },
        "columns": [
            { 
                "data": "DT_RowIndex",
                "name": "DT_RowIndex",
                "orderable": false,
                "searchable": false 
            },
            { 
                "data": "staff_no",
                "name": "staff_no" 
            },
            { 
                "data": "full_name",
                "name": "full_name",
                "orderable": false,
                "searchable": false 
            },
            { 
                "data": "person_visited",
                "name": "person_visited",
                "orderable": false,
                "searchable": false 
            },
            { 
                "data": "contact_no",
                "name": "contact_no",
                "orderable": false,
                "searchable": false 
            },
            { 
                "data": "ic_no",
                "name": "ic_no",
                "orderable": false,
                "searchable": false 
            },
            { 
                "data": "total_access",
                "name": "total_access" 
            },
            { 
                "data": "first_access",
                "name": "first_access" 
            },
            { 
                "data": "last_access",
                "name": "last_access" 
            },
            { 
                "data": null,
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    return `
                        <button class="btn btn-sm btn-info staff-movement-btn" data-staff-no="${row.staff_no}">
                            <i class="fas fa-eye"></i>
                        </button>
                    `;
                }
            }
        ],
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "order": [[1, 'asc']],
        "language": {
            "search": "Search records:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "Showing 0 to 0 of 0 entries",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "zeroRecords": "No matching records found",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "drawCallback": function(settings) {
            // Debounce the draw callback
            const now = Date.now();
            if (now - lastDrawTime < DRAW_COOLDOWN) {
                return;
            }
            lastDrawTime = now;
            
            // Update visitor details for current page
            updateVisitorDetailsForCurrentPage();
            
            // Re-attach event listeners
            $('.staff-movement-btn').off('click').on('click', function() {
                const staffNo = $(this).data('staff-no');
                viewStaffMovement(staffNo);
            });
        }
    });
}

async function updateVisitorDetailsForCurrentPage() {
    try {
        const rows = dataTable.rows({ page: 'current' }).nodes();
        
        if (!rows || rows.length === 0) {
            return;
        }
        
        // Collect all staff numbers that need to be fetched
        const staffNosToFetch = [];
        const rowStaffMap = new Map();
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            
            if (!row || !$(row).is('tr')) {
                continue;
            }
            
            // Get staff_no from the row (second column)
            const staffNoCell = $(row).find('td:nth-child(2)');
            if (!staffNoCell.length) {
                continue;
            }
            
            const staffNo = staffNoCell.text().trim();
            
            if (!staffNo || staffNo === 'N/A' || staffNo === 'Loading...') {
                continue;
            }
            
            // Check if we already have the details
            if (!visitorDetailsCache[staffNo]) {
                // Check if a request is already pending for this staffNo
                if (!pendingRequests.has(staffNo)) {
                    staffNosToFetch.push(staffNo);
                    pendingRequests.set(staffNo, true);
                }
                rowStaffMap.set(row, staffNo);
            } else {
                // We already have the details, update the row
                updateRowDetails(row, visitorDetailsCache[staffNo]);
            }
        }
        
        // If there are staff numbers to fetch, do it in a single batch
        if (staffNosToFetch.length > 0) {
            await fetchVisitorDetailsBatch(staffNosToFetch, rowStaffMap);
        }
        
    } catch (error) {
        console.error('Error in updateVisitorDetailsForCurrentPage:', error);
    }
}

async function fetchVisitorDetailsBatch(staffNos, rowStaffMap) {
    try {
        // Create a batch request URL
        const batchUrl = `${API_BASE}/api/vendorpass/get-visitor-details?icNo=${staffNo}`;
        
        // Check if batch endpoint exists, otherwise fall back to individual requests
        const response = await fetch(batchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ staffNos: staffNos })
        });
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.status === 'success' && data.data) {
                // Process batch response
                data.data.forEach(visitorData => {
                    if (visitorData && visitorData.icNo) {
                        visitorDetailsCache[visitorData.icNo] = visitorData;
                    }
                });
            }
        } else {
            // Fall back to individual requests
            await fetchVisitorDetailsIndividually(staffNos);
        }
        
        // Update all rows with fetched data
        rowStaffMap.forEach((staffNo, row) => {
            if (visitorDetailsCache[staffNo]) {
                updateRowDetails(row, visitorDetailsCache[staffNo]);
            }
        });
        
    } catch (error) {
        console.error('Error in fetchVisitorDetailsBatch:', error);
        // Fall back to individual requests
        await fetchVisitorDetailsIndividually(staffNos, rowStaffMap);
    } finally {
        // Clear pending requests
        staffNos.forEach(staffNo => {
            pendingRequests.delete(staffNo);
        });
    }
}

async function fetchVisitorDetailsIndividually(staffNos, rowStaffMap) {
    const fetchPromises = staffNos.map(async (staffNo) => {
        try {
            const response = await fetch(`${API_BASE}/api/vendorpass/get-visitor-details?icNo=${staffNo}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success' && data.data) {
                visitorDetailsCache[staffNo] = data.data;
            } else {
                // Cache a default object
                visitorDetailsCache[staffNo] = {
                    fullName: 'N/A',
                    personVisited: 'N/A',
                    contactNo: 'N/A',
                    icNo: staffNo
                };
            }
        } catch (error) {
            console.error(`Error fetching details for ${staffNo}:`, error);
            // Cache a default object to prevent repeated failed requests
            visitorDetailsCache[staffNo] = {
                fullName: 'N/A',
                personVisited: 'N/A',
                contactNo: 'N/A',
                icNo: staffNo
            };
        } finally {
            pendingRequests.delete(staffNo);
        }
    });
    
    await Promise.all(fetchPromises);
    
    // Update rows with fetched data
    if (rowStaffMap) {
        rowStaffMap.forEach((staffNo, row) => {
            if (visitorDetailsCache[staffNo]) {
                updateRowDetails(row, visitorDetailsCache[staffNo]);
            }
        });
    }
}

function updateRowDetails(row, visitorDetails) {
    try {
        // Update using jQuery selectors
        const cells = $(row).find('td');
        
        if (cells.length >= 6) {
            // Update column 3 (index 2) - Visitor Name
            $(cells[2]).text(visitorDetails.fullName || 'N/A');
            
            // Update column 4 (index 3) - Person Visited
            $(cells[3]).text(visitorDetails.personVisited || 'N/A');
            
            // Update column 5 (index 4) - Contact No
            $(cells[4]).text(visitorDetails.contactNo || 'N/A');
            
            // Update column 6 (index 5) - IC No
            $(cells[5]).text(visitorDetails.icNo || 'N/A');
        }
    } catch (error) {
        console.error('Error updating row details:', error);
    }
}

function loadReport() {
    const fromDateTime = document.getElementById('fromDate').value;
    const toDateTime = document.getElementById('toDate').value;
    const selectedLocations = getSelectedLocations();

    if (!fromDateTime || !toDateTime) {
        alert('Please select both from date-time and to date-time');
        return;
    }

    if (selectedLocations.length === 0) {
        alert('Please select at least one location');
        return;
    }

    const fromDateObj = new Date(fromDateTime);
    const toDateObj = new Date(toDateTime);

    if (fromDateObj > toDateObj) {
        alert('From date-time cannot be greater than To date-time');
        return;
    }

    document.getElementById('loadingSpinner').classList.remove('d-none');
    document.getElementById('staffTableContainer').classList.add('d-none');
    document.getElementById('noDataMessage').classList.add('d-none');
    document.getElementById('reportSummary').classList.add('d-none');

    // Clear pending requests
    pendingRequests.clear();
    
    // Reset visitor cache
    visitorDetailsCache = {};
    
    // اگر DataTable پہلے سے موجود ہے تو صرف reload کریں
    if (dataTable) {
        dataTable.ajax.reload(function(json) {
            handleDataTableResponse(json, fromDateTime, toDateTime, selectedLocations);
        });
    } else {
        // نئی DataTable بنائیں
        initializeDataTable();
        
        // DataTable کے ajax response کو handle کریں
        dataTable.on('xhr.dt', function (e, settings, json, xhr) {
            handleDataTableResponse(json, fromDateTime, toDateTime, selectedLocations);
        });
        
        document.getElementById('staffTableContainer').classList.remove('d-none');
    }
}

function handleDataTableResponse(json, fromDateTime, toDateTime, selectedLocations) {
    document.getElementById('loadingSpinner').classList.add('d-none');
    
    if (json && json.recordsTotal > 0) {
        document.getElementById('staffTableContainer').classList.remove('d-none');
        document.getElementById('totalStaffCount').textContent = json.recordsTotal;
        
        const locationText = selectedLocations.length > 2 
            ? `${selectedLocations.length} locations` 
            : selectedLocations.join(', ');
        
        const fromDisplay = formatDateTimeDisplay(fromDateTime);
        const toDisplay = formatDateTimeDisplay(toDateTime);
        
        const reportInfo = document.getElementById('reportInfo');
        reportInfo.innerHTML = `
            <strong>Report Period:</strong> ${fromDisplay} to ${toDisplay}<br>
            <strong>Locations:</strong> ${locationText}
        `;
        
        document.getElementById('reportSummary').classList.remove('d-none');
    } else {
        showNoData();
    }
}

function showNoData() {
    document.getElementById('noDataMessage').classList.remove('d-none');
    document.getElementById('staffTableContainer').classList.add('d-none');
    document.getElementById('reportSummary').classList.add('d-none');
    
    if (dataTable) {
        dataTable.destroy();
        dataTable = null;
    }
}

function viewStaffMovement(staffNo) {
    if (!staffNo) {
        alert('Staff number is required');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('staffMovementModal'));
    document.getElementById('movementModalTitle').textContent = `Movement History - ${staffNo}`;
    document.getElementById('modalStaffNo').textContent = staffNo;
    
    currentModalStaffNo = staffNo;
    document.getElementById('visitorInfoSection').style.display = 'none';

    modal.show();

    const viewChronologyBtn = document.getElementById('modalViewChronologyBtn');
    viewChronologyBtn.disabled = true;
    viewChronologyBtn.textContent = 'View Chronology';
    viewChronologyBtn.onclick = function() {
        viewVisitorChronology(currentModalStaffNo, currentModalVisitorDetails?.icNo, currentModalVisitorDetails?.fullName);
    };

    const visitorDetails = visitorDetailsCache[staffNo];
    
    if (visitorDetails) {
        displayVisitorInfoInModal(visitorDetails);
        currentModalVisitorDetails = visitorDetails;
        viewChronologyBtn.disabled = false;
    } else {
        fetchVisitorDetailsForModal(staffNo);
    }

    document.getElementById('movementLoading').classList.remove('d-none');
    document.getElementById('movementContent').classList.add('d-none');
    document.getElementById('noMovementMessage').classList.add('d-none');
    
    fetch(`/reports/staff-movement/${staffNo}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('movementLoading').classList.add('d-none');

            if (data.success && data.movement_history && data.movement_history.length > 0) {
                displayMovementHistory(data.movement_history, visitorDetails);
                document.getElementById('movementContent').classList.remove('d-none');
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

function displayVisitorInfoInModal(visitorDetails) {
    if (visitorDetails && visitorDetails.fullName && visitorDetails.fullName !== 'N/A') {
        document.getElementById('modalVisitorName').textContent = visitorDetails.fullName || 'N/A';
        document.getElementById('modalVisitorIC').textContent = visitorDetails.icNo || 'N/A';
        document.getElementById('visitorInfoSection').style.display = 'block';
        currentModalVisitorDetails = visitorDetails;
        
        const viewChronologyBtn = document.getElementById('modalViewChronologyBtn');
        viewChronologyBtn.disabled = false;
    }
}

function fetchVisitorDetailsForModal(staffNo) {
    if (!staffNo) return;
    
    fetch(`${API_BASE}/api/vendorpass/get-visitor-details?icNo=${staffNo}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const visitorDetails = data.data;
                displayVisitorInfoInModal(visitorDetails);
                visitorDetailsCache[staffNo] = visitorDetails;
            }
        })
        .catch(error => {
            console.error(`Error fetching details for ${staffNo}:`, error);
        });
}

function displayMovementHistory(movementHistory, visitorDetails) {
    const tableBody = document.getElementById('movementTableBody');
    tableBody.innerHTML = '';

    if (!movementHistory || !Array.isArray(movementHistory)) {
        return;
    }

    movementHistory.forEach(movement => {
        if (!movement) return;
        
        const row = document.createElement('tr');
        
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
            <td>
                <span class="badge bg-info">Allowed</span>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function viewVisitorChronology(staffNo, icNo, fullName) {
    if (!staffNo) {
        alert('Staff number is required');
        return;
    }
    
    // Step 1: Pehle modal close karo
    const modal = bootstrap.Modal.getInstance(document.getElementById('staffMovementModal'));
    if (modal) {
        modal.hide();
    }
    
    // یقینی بنائیں کہ icNo موجود ہے
    let actualIcNo = icNo;
    if (!actualIcNo || actualIcNo === 'N/A' || actualIcNo === 'undefined') {
        // visitorDetailsCache سے icNo لے لیں
        const visitorDetails = visitorDetailsCache[staffNo];
        if (visitorDetails && visitorDetails.icNo) {
            actualIcNo = visitorDetails.icNo;
        } else {
            actualIcNo = staffNo; // fallback
        }
    }
    
    // FORCE IC Number as default
    const url = `/visitor-details?autoSearch=true&staffNo=${encodeURIComponent(staffNo)}&icNo=${encodeURIComponent(actualIcNo)}&searchBy=icNo&forceIcNo=true`;
    console.log('Redirecting to:', url);
    
    window.location.href = url;
}
</script>
<script>
flatpickr(".datetime-picker", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    time_24hr: true
});
</script>
@endsection
