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
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="mb-1">ACCESS LOGS REPORT</h2>
                        <p class="text-muted mb-0">Staff Access History</p>
                    </div>
                </div>

                <div class="row mb-4 filter-form-mobile">
                    <div class="col-12 col-sm-6 col-md-3 mb-3 mb-md-0">
                        <label for="fromDate" class="form-label">From Date & Time</label>
                        <input type="datetime-local" class="form-control datetime-picker" id="fromDate" 
                            value="{{ now()->format('Y-m-d\T00:00') }}">
                        <small class="text-muted">Start date and time</small>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3 mb-3 mb-md-0">
                        <label for="toDate" class="form-label">To Date & Time</label>
                        <input type="datetime-local" class="form-control datetime-picker" id="toDate" 
                            value="{{ now()->format('Y-m-d\T23:59') }}">
                        <small class="text-muted">End date and time</small>
                    </div>
                    
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
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAllLocations">
                                        <label class="form-check-label fw-bold" for="selectAllLocations">
                                            Select All Locations
                                        </label>
                                    </div>
                                    <hr class="my-2">
                                    
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
                        <div id="selectedLocations" class="mt-2 small text-muted" style="min-height: 20px;"></div>
                    </div>
                    
                    <div class="col-12 col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="loadReport()" style="margin-bottom: 30px">
                            <i class="fas fa-search"></i> <span class="d-none d-sm-inline">Generate</span>
                        </button>
                    </div>
                </div>
                <div id="loadingSpinner" class="text-center d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading report data...</p>
                </div>
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
                <div class="table-container-wrapper d-none" id="staffTableContainer">
                    <div class="table-responsive" style="height: 1500px !important">
                        <table class="table table-hover table-striped" id="staffTable">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Visitor Name</th>
                                    <th>Contact No</th>
                                    <th>IC No</th>
                                    <th>Person Visited</th>
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
    <div class="modal-dialog modal-xl"> {{-- ✅ modal-lg se modal-xl kar diya --}}
        <div class="modal-content" style="max-width: 1200px; margin: 0 auto;"> {{-- ✅ max-width add kiya --}}
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
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <p><strong>Staff No:</strong> <span id="modalStaffNo"></span></p>
                        </div>
                        <div class="col-md-8">
                            <div class="visitor-info-section" id="visitorInfoSection" style="display: none;">
                                <p><strong>Full Name:</strong> <span id="modalVisitorName"></span></p>
                                <p><strong>IC No:</strong> <span id="modalVisitorIC"></span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-striped">
                            <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th style="min-width: 150px;">Date & Time</th>
                                    <th style="min-width: 200px;">Location</th>
                                    <th style="min-width: 100px;">Access</th>
                                    <th style="min-width: 200px;">Reason</th>
                                    <th style="min-width: 100px;">Type</th>
                                    <th style="min-width: 120px;">Action</th>
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
let dataTable = null;
let allStaffData = [];
let visitorDetailsCache = {};
let currentModalStaffNo = null;
let currentModalVisitorDetails = null;

// Request tracking variables
let pendingRequests = new Map();
let lastDrawTime = 0;
const DRAW_COOLDOWN = 1000; 
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
        "order": [[6, 'desc']],  
        "ajax": {
            "url": '{{ route("reports.access-logs.data") }}',
            "type": "POST",
            "data": function (d) {
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
                "searchable": false,
                "className": "text-center",
                "width": "50px"
            },
            { 
                "data": "full_name",
                "name": "full_name",
                "orderable": false,
                "searchable": false,
                "className": "text-left",
                "width": "200px"
            },
            { 
                "data": "contact_no",
                "name": "contact_no",
                "orderable": false,
                "searchable": false,
                "className": "text-center",
                "width": "120px"
            },
            { 
                "data": "ic_no",
                "name": "ic_no",
                "orderable": true,
                "searchable": true,
                "className": "text-center",
                "width": "150px"
            },
            { 
                "data": "person_visited",
                "name": "person_visited",
                "orderable": false,
                "searchable": false,
                "className": "text-left",
                "width": "180px"
            },
            { 
                "data": "total_access",
                "name": "total_access",
                "orderable": true,
                "searchable": false,
                "className": "text-center",
                "width": "100px"
            },
            { 
                "data": "first_access",
                "name": "first_access",
                "orderable": true,
                "searchable": false,
                "className": "text-center",
                "width": "150px"
            },
            { 
                "data": "last_access",
                "name": "last_access",
                "orderable": true,
                "searchable": false,
                "className": "text-center",
                "width": "150px"
            },
            { 
                "data": null,
                "orderable": false,
                "searchable": false,
                "className": "text-center",
                "width": "80px",
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
        "order": [[3, 'asc']], // IC No (column index 3) سے sort کریں
        "scrollX": true, // Horizontal scroll enable کریں
        "scrollCollapse": true,
        "fixedColumns": {
            "leftColumns": 1, // No column fixed رکھیں
            "rightColumns": 1 // Actions column fixed رکھیں
        },
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
            const now = Date.now();
            if (now - lastDrawTime < DRAW_COOLDOWN) {
                return;
            }
            lastDrawTime = now;
            
            updateVisitorDetailsForCurrentPage();
            
            $('.staff-movement-btn').off('click').on('click', function() {
                const staffNo = $(this).data('staff-no');
                viewStaffMovement(staffNo);
            });
        },
        "initComplete": function() {
            // Table کو responsive بنانے کے لیے
            this.api().columns.adjust();
        }
    });
}

async function updateVisitorDetailsForCurrentPage() {
    try {
        const rows = dataTable.rows({ page: 'current' }).nodes();
        
        if (!rows || rows.length === 0) {
            return;
        }

        const icNosToFetch = [];
        const rowIcMap = new Map();
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            
            if (!row || !$(row).is('tr')) {
                continue;
            }

            // IC No والا سیل تلاش کریں (column index 3)
            const icNoCell = $(row).find('td:nth-child(4)'); // چوتھا کالم IC No ہے
            if (!icNoCell.length) {
                continue;
            }
            
            const icNo = icNoCell.text().trim();
            
            if (!icNo || icNo === 'N/A' || icNo === 'Loading...') {
                continue;
            }
            
            if (!visitorDetailsCache[icNo]) {
                if (!pendingRequests.has(icNo)) {
                    icNosToFetch.push(icNo);
                    pendingRequests.set(icNo, true);
                }
                rowIcMap.set(row, icNo);
            } else {
                updateRowDetails(row, visitorDetailsCache[icNo]);
            }
        }

        if (icNosToFetch.length > 0) {
            await fetchVisitorDetailsIndividually(icNosToFetch, rowIcMap);
        }
        
    } catch (error) {
        console.error('Error in updateVisitorDetailsForCurrentPage:', error);
    }
}

async function fetchVisitorDetailsBatch(staffNos, rowStaffMap) {
    try {
        // Create a batch request URL
        const batchUrl = `${API_BASE}/api/vendorpass/get-visitor-details?icNo=${staffNo}`;

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
            await fetchVisitorDetailsIndividually(staffNos);
        }
        
        rowStaffMap.forEach((staffNo, row) => {
            if (visitorDetailsCache[staffNo]) {
                updateRowDetails(row, visitorDetailsCache[staffNo]);
            }
        });
        
    } catch (error) {
        console.error('Error in fetchVisitorDetailsBatch:', error);
        await fetchVisitorDetailsIndividually(staffNos, rowStaffMap);
    } finally {
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
        const cells = $(row).find('td');
        
        if (cells.length >= 9) { // اب 9 کالم ہیں
            // Column 2: Visitor Name (index 1)
            $(cells[1]).text(visitorDetails.fullName || 'N/A');
            // Column 3: Contact No (index 2)
            $(cells[2]).text(visitorDetails.contactNo || 'N/A');
            // Column 4: IC No (index 3) - یہ پہلے ہی icNo ہونا چاہیے
            $(cells[3]).text(visitorDetails.icNo || 'N/A');
            // Column 5: Person Visited (index 4)
            $(cells[4]).text(visitorDetails.personVisited || 'N/A');
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

    pendingRequests.clear();

    visitorDetailsCache = {};

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
    
    console.log('API Response:', json);
    console.log('recordsTotal:', json.recordsTotal);
    console.log('Number of rows in data:', json.data ? json.data.length : 0);
    
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

function displayMovementHistory(movementHistory) {
    const tableBody = document.getElementById('movementTableBody');
    tableBody.innerHTML = '';

    movementHistory.forEach(movement => {
        if (!movement) return;

        const isAllowed = Number(movement.access_granted) === 1;
        
        let typeBadge = 'badge bg-secondary';
        let displayType = movement.type || 'N/A';
        
        if (displayType === 'check_in') displayType = 'Checkin';
        if (displayType === 'check_out') displayType = 'Checkout';

                // ✅ Original date_time ko parse karke seconds add karein
        const originalDateTime = movement.date_time || 'N/A';
        let formattedDateTime = originalDateTime;
        
        // Agar format mein seconds nahi hain to add karein
        if (originalDateTime !== 'N/A' && !originalDateTime.includes(':')) {
            const date = new Date(originalDateTime);
            formattedDateTime = date.toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-nowrap">${movement.date_time || 'N/A'}</td>
            <td>${movement.location || 'N/A'}</td>
            <td class="text-center">
                <span class="badge ${isAllowed ? 'bg-success' : 'bg-danger'}">
                    ${movement.access_display || (isAllowed ? 'Yes' : 'No')}
                </span>
            </td>
            <td>${movement.reason || 'N/A'}</td>
            <td class="text-center">
                <span class="${typeBadge}">
                    ${displayType}
                </span>
            </td>
            <td class="text-center">
                <span class="badge ${isAllowed ? 'bg-success' : 'bg-danger'}">
                    ${movement.action_display || (isAllowed ? 'Allowed' : 'Not Allowed')}
                </span>
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
