@extends('layout.main_layout')

@section('content')
<div class="container-fluid dashboard-wrapper">

{{-- Critical Alert Section --}}
<div class="row mb-4" id="criticalAlertSection">
    @if($criticalAlert)
    <div class="col-12" >
        <div class="critical-alert-box" 
            id="currentCriticalAlert" 
            data-alert-id="{{ $criticalAlert['log_id'] }}"
            data-alert-type="{{ $criticalAlert['alert_type'] }}"
            data-staff-no="{{ $criticalAlert['staff_no'] ?? '' }}"
            data-card-no="{{ $criticalAlert['card_no'] ?? '' }}"
            data-location="{{ $criticalAlert['location'] ?? '' }}"
            data-original-location="{{ $criticalAlert['original_location'] ?? $criticalAlert['location'] ?? '' }}"
            @if($criticalAlert['alert_type'] == 'visitor_overstay')
                data-date-of-visit-from="{{ $criticalAlert['overstay_details']['date_of_visit_from'] ?? '' }}"
            @endif>
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="critical-title mb-0">
                    @if($criticalAlert['alert_type'] == 'access_denied')
                        <i class="fas fa-shield-alt me-2"></i>Critical Security Alert
                    @else
                        <i class="fas fa-clock me-2"></i>Visitor Overstay Alert
                    @endif
                    <span class="badge bg-{{ $criticalAlert['priority'] == 'high' ? 'danger' : ($criticalAlert['priority'] == 'medium' ? 'warning' : 'secondary') }} ms-2">
                        {{ ucfirst($criticalAlert['priority'] ?? 'low') }} Priority
                    </span>
                </h5>
                <button class="btn btn-sm btn-outline-light" onclick="acknowledgeAlert()">
                    <i class="fas fa-check me-1"></i> Acknowledge
                </button>
            </div>

            <div class="row mt-3">
                <div class="col-md-4 col-12 mb-3">
                    <p class="label mb-1">Incident Type</p>
                    <p class="value mb-0">
                        @if($criticalAlert['alert_type'] == 'access_denied')
                            Unauthorized Access Attempt
                        @else
                            Visitor Overstay Alert
                        @endif
                    </p>
                </div>

                <div class="col-md-4 col-12 mb-3">
                    <p class="label mb-1">Location</p>
                    <p class="value mb-0">{{ $criticalAlert['location'] }}</p>
                </div>

                <div class="col-md-4 col-12 mb-3">
                    <p class="label mb-1">Time</p>
                    <p class="value mb-0">
                        @if($criticalAlert['alert_type'] == 'access_denied')
                            {{ $criticalAlert['created_at'] ?? '' }} 
                            {{-- ({{ $criticalAlert['time_ago'] ?? '' }}) --}}
                        @else
                            {{ $criticalAlert['created_at'] ?? '' }}
                        @endif
                    </p>
                </div>
            </div>

            <p class="description mt-2 mb-3">
                @if($criticalAlert['alert_type'] == 'access_denied')
                    {{ $criticalAlert['visitor_name'] }} on the restricted watchlist attempted to gain entry.
                @else
                    {{ $criticalAlert['visitor_name'] }} has exceeded their scheduled visit time by {{ $criticalAlert['overstay_details']['overstay_duration'] ?? 'unknown time' }}.
                    Expected end: {{ $criticalAlert['overstay_details']['expected_end_time'] ?? 'N/A' }}
                @endif
            </p>

            <div>
                @if($criticalAlert['alert_type'] == 'access_denied')
                    <button class="btn btn-danger btn-sm" onclick="showSecurityAlertsModal()">
                        <i class="fas fa-eye me-1"></i> View Incident
                    </button>
                @else
                    <button class="btn btn-warning btn-sm" onclick="showVisitorOverstayModal()">
                        <i class="fas fa-clock me-1"></i> View Overstay Details
                    </button>
                @endif
            </div>
        </div>
    </div>
    @else
    <div class="col-12">
        <div class="alert alert-success">
            <h5 class="alert-heading mb-2"><i class="fas fa-shield-check me-2"></i>All Clear</h5>
            <p class="mb-0">No critical security alerts at this moment.</p>
        </div>
    </div>
    @endif
</div>

{{-- Recent Alerts Section - Fixed Width --}}
<div class="row mb-4">
    <div class="col-12 px-0"> {{-- ✅ px-0 add karein --}}
        <div class="content-card" style="background-color: #F8f9fa; padding: 1.5rem;">
            <h5 class="mb-3"><i class="fas fa-bell me-2"></i>Recent Alerts</h5>
            
            <div class="row g-3 mx-0"> {{-- ✅ mx-0 add karein --}}
                <div class="col-12 col-md-6 px-2">
                    <div class="stat-card clickable-card alert-card" onclick="showAccessDeniedModal()">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="text-danger" id="deniedAccessCount24h">{{ $deniedAccessCount24h ?? 0 }}</h2>
                            <span class="badge bg-danger">Access Denied</span>
                        </div>
                        <p class="mb-1 fw-medium">Access Denied Incidents</p>
                        <small class="text-muted">Last 24 hours only</small>
                    </div>
                </div>

                {{-- Visitor Overstay Card --}}
                <div class="col-12 col-md-6 px-2"> {{-- ✅ px-2 for proper spacing --}}
                    <div class="stat-card clickable-card alert-card" onclick="showVisitorOverstayModal()">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="text-warning">{{ $visitorOverstayCount ?? 0 }}</h2>
                            <span class="badge bg-warning">Overstay</span>
                        </div>
                        <p class="mb-1 fw-medium">Visitor Overstay Alerts</p>
                        <small class="text-muted">Active alerts</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="row g-3 mb-4" style="width: 99%; margin-left: 8px !important;">
        <div class="col-md-3 col-6" >
            <div class="stat-card clickable-card" onclick="showVisitorsOnSiteModal()">
                <h2>{{ count($visitorsOnSite) }}</h2>
                <p>Visitors On-Site</p>
            </div>
        </div>

        {{-- Card 2: Expected Today --}}
        <div class="col-md-3 col-6">
            <div class="stat-card clickable-card" onclick="showExpectedTodayModal()">
                <h2>{{ $todayAppointmentCount }}</h2>
                <p>Expected Today</p>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card clickable-card" onclick="showCheckoutsTodayModal()">
                <h2>{{ $checkOutsTodayCount ?? 0 }}</h2>
                <p>Check Out Today</p>
            </div>
        </div>

        {{-- Card 4: Active Security Alerts --}}
        <div class="col-md-3 col-6">
            <div class="stat-card clickable-card" onclick="showSecurityAlertsModal()">
                <h2>{{ $activeSecurityAlertsCount }}</h2>
                <p>Active Security Alerts</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="content-card mb-4">
                <h5>Currently On-Site</h5>
                <div class="table-responsive">
                    <table class="table table-hover mt-3" id="onSiteTable">
                        <thead>
                            <tr>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Check-in Time</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visitorsOnSite as $visitor)
                            <tr>
                                <td>{{ $visitor['full_name'] ?? 'N/A' }}</td>
                                <td>{{ $visitor['person_visited'] ?? 'N/A' }}</td>
                                <td>
                                    {{ \Carbon\Carbon::parse($visitor['created_at'])->format('h:i A') }}
                                </td>
                                <td>
                                    {{ $visitor['location_name'] ?? 'N/A' }}
                                </td>
                            </tr>
                            @endforeach
                            
                            @if(empty($visitorsOnSite))
                            <tr>
                                <td colspan="4" class="text-center">No visitors currently on-site</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3">Visitor Traffic Analytics</h5>
                        
                        {{-- Date Filter Form --}}
                        <div class="row filter-form-mobile">
                            <div class="col-12 col-sm-5 col-md-4 mb-2 mb-md-0">
                                <label for="graphFromDate" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="graphFromDate" value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-12 col-sm-5 col-md-4 mb-2 mb-md-0">
                                <label for="graphToDate" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="graphToDate" value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-12 col-sm-2 col-md-4 d-flex align-items-end">
                                <button class="btn btn-primary w-100" onclick="loadGraphData()">
                                    <i class="fas fa-chart-line"></i> <span class="d-none d-sm-inline">Update Graph</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Graph Loading Spinner --}}
                <div id="graphLoadingSpinner" class="text-center d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading graph data...</p>
                </div>

                <div id="graphContainer">
                    <canvas id="trafficChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="content-card clickable-card" onclick="showUpcomingAppointmentsModal()">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 text-dark">Upcoming Appointments</h5>
                </div>

                <div class="text-center py-4">
                    <h1 class="display-4 text-dark mb-2">{{ count($upcomingAppointments) }}</h1>
                    <p class="text-muted mb-0">
                        @if(count($upcomingAppointments) > 0)
                        @else
                            No upcoming appointments
                        @endif
                    </p>
                </div>
            </div>

            {{-- Today's Appointments (Same as before) --}}
            <div class="content-card">
                <h5 class="mb-3">Today's Appointments</h5>

                <ul class="list-group list-group-flush dark-list">
                    @foreach(collect($todayAppointments)->unique('staff_no') as $appointment)
                    <li class="list-group-item">
                        <strong>{{ $appointment['full_name'] }}</strong> – 
                        {{ \Carbon\Carbon::parse($appointment['date_from'])->format('h:i A') }}                        
                        <br>
                        <small>Host: {{ $appointment['name_of_person_visited'] ?? 'N/A' }}</small>
                    </li>
                    @endforeach
                    
                    @if(empty($todayAppointments))
                    <li class="list-group-item text-center">No appointments today</li>
                    @endif
                </ul>
            </div>

            {{-- Removed Recent Alerts Section from here --}}
        </div>
    </div>

</div>
{{-- Modal 1: Visitors On-Site --}}

<div class="modal fade" id="visitorsOnSiteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Visitors Currently On-Site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="visitorsOnSiteTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Check-in Time</th>
                                <th>Location</th>
                                <th>Staff No</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visitorsOnSite as $index => $visitor)
                            <tr>
                                <td>{{ $loop->index + 1 }}</td>
                                <td>{{ $visitor['full_name'] ?? 'N/A' }}</td>
                                <td>{{ $visitor['person_visited'] ?? 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($visitor['created_at'])->format('h:i A') }}</td>
                                <td>{{ $visitor['location_name'] ?? 'N/A' }}</td>
                                <td>{{ $visitor['staff_no'] ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                            
                            @if(empty($visitorsOnSite))
                            <tr>
                                <td colspan="6" class="text-center">No visitors currently on-site</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal 2: Expected Today --}}
<div class="modal fade" id="expectedTodayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Today's Appointments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="expectedTodayTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Appointment Time</th>
                                <th>Contact No</th>
                                <th>IC No</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($todayAppointments as $index => $appointment)
                            <tr>
                                <td>{{ $loop->index + 1 }}</td>
                                <td>{{ $appointment['full_name'] ?? 'N/A' }}</td>
                                <td>{{ $appointment['name_of_person_visited'] ?? 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($appointment['date_from'])->format('h:i A') }}</td>
                                <td>{{ $appointment['contact_no'] ?? 'N/A' }}</td>
                                <td>{{ $appointment['ic_no'] ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                            
                            @if(empty($todayAppointments))
                            <tr>
                                <td colspan="6" class="text-center">No appointments today</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal 3: Check-outs Today (Dynamic Data) --}}
<div class="modal fade" id="checkoutsTodayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Today's Check-outs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded via AJAX -->
                <div id="checkoutsModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading check-out data...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Security Alerts Modal में AJAX based content --}}
<div class="modal fade" id="securityAlertsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Active Security Alerts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="securityAlertsModalBody">
                {{-- Content will be loaded via AJAX --}}
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading security alerts...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- NEW: Access Denied Modal --}}
<div class="modal fade" id="accessDeniedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Access Denied Incidents ({{ $deniedAccessCount ?? 0 }})</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="accessDeniedTable">
                        <thead>
                            <tr>
                                <th>Visitor Name</th>
                                <th>Contact No</th>
                                <th>IC No</th>
                                <th>Host</th>
                                <th>Location</th>
                                <th>Reason</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($enrichedDeniedAccessLogs as $enrichedLog)
                            <tr>
                                <td>{{ $enrichedLog['visitor_details']['fullName'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['contactNo'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['icNo'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['personVisited'] }}</td>
                                <td>{{ $enrichedLog['log']->location_name ?? 'Unknown Location' }}</td>
                                <td>{{ $enrichedLog['log']->reason ? $enrichedLog['log']->reason : 'Other Reason' }}</td>
                                <td>{{ \Carbon\Carbon::parse($enrichedLog['log']->created_at)->format('d M Y h:i A') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- NEW: Visitor Overstay Modal --}}
<div class="modal fade" id="visitorOverstayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Visitor Overstay Alerts ({{ $visitorOverstayCount ?? 0 }})</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="visitorOverstayTable">
                        <thead>
                            <tr>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Contact No</th>
                                <th>IC No</th>
                                <th>Location</th>
                                <th>Check-in Time</th>
                                <th>Expected End</th>
                                <th>Current Time</th>
                                <th>Overstay Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($enrichedOverstayAlerts as $alert)
                            <tr>
                                <td>{{ $alert['visitor_name'] }}</td>
                                <td>{{ $alert['host'] }}</td>
                                <td>{{ $alert['contact_no'] ?? 'N/A' }}</td>
                                <td>{{ $alert['ic_no'] ?? 'N/A' }}</td>
                                <td>{{ $alert['location'] }}</td>
                                <td>{{ $alert['check_in_time'] }}</td>
                                <td>{{ $alert['expected_end_time'] }}</td>
                                <td>{{ $alert['current_time'] }}</td>
                                <td class="text-danger fw-bold">{{ $alert['overstay_duration'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Updated: Upcoming Appointments Modal with AJAX --}}
<div class="modal fade" id="upcomingAppointmentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upcoming Appointments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Loading Spinner --}}
                <div id="upcomingAppointmentsLoading" class="text-center d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading appointments...</p>
                </div>
                
                {{-- Content Container --}}
                <div id="upcomingAppointmentsContent">
                    <div class="table-responsive">
                        <table class="table table-hover" id="upcomingAppointmentsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Visitor Name</th>
                                    <th>Contact No</th>
                                    <th>IC No</th>
                                    <th>Host</th>
                                    <th>Appointment Date</th>
                                    <th>Appointment Time</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody id="upcomingAppointmentsBody">
                                {{-- Data will be loaded via AJAX --}}
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Pagination Container --}}
                    <div id="upcomingAppointmentsPagination" class="d-flex justify-content-center mt-3">
                        {{-- Pagination will be loaded via AJAX --}}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Chart.js Dynamic Graph --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let trafficChart = null;
// NEW: Modal Show Functions for Alert Cards
function showAccessDeniedModal() {
    const modal = new bootstrap.Modal(document.getElementById('accessDeniedModal'));
    modal.show();
}
function showVisitorOverstayModal() {
    const modal = new bootstrap.Modal(document.getElementById('visitorOverstayModal'));
    modal.show();
}



document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const onSiteTable = $('#onSiteTable').DataTable({
        pageLength: 5, // 5 records per page
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]], // ✅ FIX: Updated lengthMenu
        responsive: true,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search visitors...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ visitors",
            infoEmpty: "Showing 0 to 0 of 0 visitors",
            infoFiltered: "(filtered from _MAX_ total visitors)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        order: [[2, 'desc']], // Sort by check-in time descending
        drawCallback: function(settings) {
            // Remove pagination if only one page
            const api = this.api();
            const pageInfo = api.page.info();
            
            if (pageInfo.pages <= 1) {
                $(api.table().container()).find('.dataTables_paginate').hide();
            } else {
                $(api.table().container()).find('.dataTables_paginate').show();
            }
        }
    });
    
    // Auto-refresh table every 30 seconds
    setInterval(function() {
        refreshOnSiteTable();
    }, 30000);
});

    // Function to refresh table data
    function refreshOnSiteTable() {
        fetch('/dashboard/refresh-on-site', {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // ✅ Get current page length before updating
                const table = $('#onSiteTable').DataTable();
                const currentPageLength = table.page.len();

                updateTableData(data.visitors);

                table.page.len(currentPageLength).draw();
                
                // Update count in card
                const visitorsCard = document.querySelector('.stat-card.clickable-card:nth-child(1) h2');
                if (visitorsCard) {
                    visitorsCard.textContent = data.count || 0;
                }
                
                // ✅ ADD: Also refresh denied access count separately
                refreshDeniedAccessCount();
            }
        })
        .catch(error => {
            console.error('Error refreshing on-site data:', error);
        });
    }

    // ✅ NEW: Separate function to refresh denied access count
    function refreshDeniedAccessCount() {
        fetch('/dashboard/refresh-denied-access-count', {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const deniedAccessCard = document.getElementById('deniedAccessCount24h');
                if (deniedAccessCard) {
                    deniedAccessCard.textContent = data.deniedAccessCount24h || 0;
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing denied access count:', error);
        });
    }

// Function to update table data
function updateTableData(visitors) {
    const table = $('#onSiteTable').DataTable();
    table.clear();
    
    if (visitors.length > 0) {
        visitors.forEach(function(visitor) {
            table.row.add([
                visitor.full_name || 'N/A',
                visitor.person_visited || 'N/A',
                visitor.created_at ? new Date(visitor.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'N/A',
                visitor.location_name || 'N/A'
            ]);
        });
    } else {
        table.row.add([
            'No visitors currently on-site',
            'N/A',
            'N/A',
            'N/A'
        ]);
    }
    
    table.draw(false);
}

function showUpcomingAppointmentsModal() {
    const modal = new bootstrap.Modal(document.getElementById('upcomingAppointmentsModal'));
    
    // Load data when modal opens
    $('#upcomingAppointmentsModal').on('shown.bs.modal', function() {
        loadUpcomingAppointments(1);
    });
    
    modal.show();
}

function loadUpcomingAppointments(page = 1) {
    console.log('Loading upcoming appointments, page:', page);
    
    // Show loading
    $('#upcomingAppointmentsLoading').removeClass('d-none');
    $('#upcomingAppointmentsContent').addClass('d-none');
    
    // Fetch data via AJAX
    fetch(`/vms/dashboard/upcoming-appointments-ajax?page=${page}`)
        .then(response => response.json())
        .then(data => {
            console.log('Upcoming appointments data received:', data);
            
            $('#upcomingAppointmentsLoading').addClass('d-none');
            $('#upcomingAppointmentsContent').removeClass('d-none');
            
            if (data.success) {
                // Update table body
                $('#upcomingAppointmentsBody').html(data.html);
                
                // ✅ CUSTOM PAGINATION SET KAREIN
                if (data.pagination) {
                    $('#upcomingAppointmentsPagination').html(data.pagination);
                } else {
                    $('#upcomingAppointmentsPagination').html('');
                }
                
                // DataTable initialize
                if ($.fn.DataTable.isDataTable('#upcomingAppointmentsTable')) {
                    $('#upcomingAppointmentsTable').DataTable().destroy();
                }
                
                // Pagination events attach karein
                attachPaginationEvents();
            } else {
                // Error handling
            }
        })
        .catch(error => {
            // Error handling
        });
}

function initUpcomingAppointmentsDataTable() {
    // Only initialize if not already initialized
    if (!$.fn.DataTable.isDataTable('#upcomingAppointmentsTable')) {
        $('#upcomingAppointmentsTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[5, 'asc'], [6, 'asc']], // Sort by date then time
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search appointments...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ appointments",
                infoEmpty: "Showing 0 to 0 of 0 appointments",
                infoFiltered: "(filtered from _MAX_ total appointments)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            drawCallback: function(settings) {
                // Hide DataTable's own pagination since we have custom AJAX pagination
                $(this.api().table().container()).find('.dataTables_paginate').hide();
            }
        });
    }
}


function attachPaginationEvents() {
    // Remove existing event listeners
    $('.pagination-link').off('click');
    
    // Attach new event listeners
    $('.pagination-link').on('click', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadUpcomingAppointments(page);
        
        // Update active state
        $('.page-item').removeClass('active');
        $(this).closest('.page-item').addClass('active');
    });
}

// Also make the whole card clickable if there are appointments
document.addEventListener('DOMContentLoaded', function() {
    const upcomingCard = document.querySelector('.content-card h5:contains("Upcoming Appointments")')?.closest('.content-card');
    const appointmentCount = {{ count($upcomingAppointments) }};
    
    if (upcomingCard && appointmentCount > 0) {
        upcomingCard.style.cursor = 'pointer';
        upcomingCard.addEventListener('click', function(e) {
            // Only trigger if not clicking on buttons/links
            if (!e.target.closest('button') && !e.target.closest('a')) {
                showUpcomingAppointmentsModal();
            }
        });
    }
    
    // Clean up modal events when closed
    $('#upcomingAppointmentsModal').on('hidden.bs.modal', function() {
        // Destroy DataTable if exists
        if ($.fn.DataTable.isDataTable('#upcomingAppointmentsTable')) {
            $('#upcomingAppointmentsTable').DataTable().destroy();
        }
        
        // Clear content
        $('#upcomingAppointmentsBody').html('');
        $('#upcomingAppointmentsPagination').html('');
    });
    
    // Clean up security alerts modal events
    $('#securityAlertsModal').on('hidden.bs.modal', function() {
        if ($.fn.DataTable.isDataTable('#securityAlertsDataTable')) {
            $('#securityAlertsDataTable').DataTable().destroy();
        }
    });
});

// Initialize chart with default data
function initializeChart(labels, data) {
    const ctx = document.getElementById('trafficChart').getContext('2d');   
    if (trafficChart) {
        trafficChart.destroy();
    }    
    trafficChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Scans',
                data: data,
                backgroundColor: 'rgba(85, 110, 230, 0.5)',
                borderColor: 'rgba(85, 110, 230, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#495057'
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#495057',
                        maxRotation: 45
                    },
                    grid: {
                        color: 'rgba(73, 80, 87, 0.1)'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#495057',
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(73, 80, 87, 0.1)'
                    }
                }
            }
        }
    });
}



let currentCriticalAlertId = null;

// ✅ UPDATED: Critical Alert Functions
function acknowledgeAlert() {
    const alertBox = document.getElementById('currentCriticalAlert');
    if (!alertBox) return;
    
    const alertId = alertBox.dataset.alertId;
    const alertType = alertBox.dataset.alertType || 'access_denied';
    
    console.log('Sending acknowledgment for:', { alertId, alertType });
    
    // Prepare request data
    const requestData = {
        alert_id: alertId,
        alert_type: alertType,
        staff_no: alertBox.dataset.staffNo || '',
        card_no: alertBox.dataset.cardNo || '',
        location: alertBox.dataset.location || '',
        original_location: alertBox.dataset.originalLocation || alertBox.dataset.location || ''
    };
    
    if (alertType === 'visitor_overstay') {
        requestData.date_of_visit_from = alertBox.dataset.dateOfVisitFrom || '';
        requestData.location = alertBox.dataset.originalLocation || alertBox.dataset.location || '';
    }
    
    // Show loading state
    const acknowledgeBtn = alertBox.querySelector('.btn-outline-light');
    const originalText = acknowledgeBtn.innerHTML;
    acknowledgeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Acknowledging...';
    acknowledgeBtn.disabled = true;
    
    // AJAX call
    fetch('/vms/dashboard/acknowledge-alert', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // ✅ UPDATE BOTH COUNTS IMMEDIATELY
            if (alertType === 'access_denied') {
                // Update Active Security Alerts (OVERALL)
                const activeAlertsCard = document.getElementById('activeSecurityAlertsCount');
                if (activeAlertsCard) {
                    let currentCount = parseInt(activeAlertsCard.textContent);
                    if (currentCount > 0) {
                        activeAlertsCard.textContent = currentCount - 1;
                    }
                }
                
                // Update Denied Access (24 HOURS)
                const deniedAccessCard = document.getElementById('deniedAccessCount24h');
                if (deniedAccessCard) {
                    let currentCount = parseInt(deniedAccessCard.textContent);
                    if (currentCount > 0) {
                        deniedAccessCard.textContent = currentCount - 1;
                    }
                }
            } else if (alertType === 'visitor_overstay') {
                // Update Visitor Overstay
                const overstayCard = document.getElementById('visitorOverstayCount');
                if (overstayCard) {
                    let currentCount = parseInt(overstayCard.textContent);
                    if (currentCount > 0) {
                        overstayCard.textContent = currentCount - 1;
                    }
                }
            }
            
            // Handle next alert or show success
            if (data.has_next && data.next_alert) {
                updateCriticalAlert(data.next_alert);
                // Also refresh counts via AJAX for consistency
                refreshDashboardCounts();
            } else {
                document.getElementById('criticalAlertSection').innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-success">
                            <h5 class="alert-heading">All Alerts Acknowledged</h5>
                            <p class="mb-0">All critical security alerts have been acknowledged.</p>
                        </div>
                    </div>
                `;
            }
        } else {
            alert('Error: ' + data.message);
            acknowledgeBtn.innerHTML = originalText;
            acknowledgeBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error acknowledging alert. Please try again.');
        acknowledgeBtn.innerHTML = originalText;
        acknowledgeBtn.disabled = false;
    });
}

function updateDashboardCountsImmediately(alertType) {
    if (alertType === 'access_denied') {
        // Update Active Security Alerts card
        const activeAlertsCard = document.querySelectorAll('.stat-card')[3]?.querySelector('h2');
        if (activeAlertsCard) {
            let currentCount = parseInt(activeAlertsCard.textContent);
            if (currentCount > 0) {
                activeAlertsCard.textContent = currentCount - 1;
            }
        }
        
        // Update Denied Access card
        const deniedAccessCard = document.querySelector('.alert-card:nth-child(1) h2');
        if (deniedAccessCard) {
            let currentCount = parseInt(deniedAccessCard.textContent);
            if (currentCount > 0) {
                deniedAccessCard.textContent = currentCount - 1;
            }
        }
    } else if (alertType === 'visitor_overstay') {
        // Update Visitor Overstay card
        const overstayCard = document.querySelector('.alert-card:nth-child(2) h2');
        if (overstayCard) {
            let currentCount = parseInt(overstayCard.textContent);
            if (currentCount > 0) {
                overstayCard.textContent = currentCount - 1;
            }
        }
    }
    
    // Also update via AJAX for consistency
    refreshDashboardCounts();
}


function updateAllDashboardCountsImmediately() {
    // Update Active Security Alerts card (top row, 4th card)
    const activeAlertsCard = document.querySelector('.stat-card:nth-child(4) h2');
    if (activeAlertsCard) {
        let currentCount = parseInt(activeAlertsCard.textContent);
        if (currentCount > 0) {
            activeAlertsCard.textContent = currentCount - 1;
        }
    }
    
    // Update Denied Access card (Recent Alerts section, 1st card)
    const deniedAccessCard = document.querySelector('.alert-card:nth-child(1) h2');
    if (deniedAccessCard) {
        let currentCount = parseInt(deniedAccessCard.textContent);
        if (currentCount > 0) {
            deniedAccessCard.textContent = currentCount - 1;
        }
    }
    
    // ✅ ALSO update the counts in backend via AJAX (for consistency)
    refreshDashboardCounts();
}

function closeCriticalAlert() {
    const alertBox = document.getElementById('currentCriticalAlert');
    if (!alertBox) return;
    
    const alertId = alertBox.dataset.alertId;
    
    // Hide the alert section
    document.getElementById('criticalAlertSection').style.display = 'none';
    
    // ✅ IMMEDIATELY UPDATE COUNTS
    updateAllDashboardCountsImmediately();
    
    // AJAX call to mark alert as acknowledged
    fetch('/vms/dashboard/hide-critical-alert', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            alert_id: alertId
        })
    })
    .catch(error => {
        console.error('Error hiding alert:', error);
    });
}


// Function for Active Security Alerts pagination
function updateActiveAlertsPage(page) {
    // Modal close करें
    const modal = bootstrap.Modal.getInstance(document.getElementById('securityAlertsModal'));
    if (modal) {
        modal.hide();
    }
    
    // URL बनाएं और redirect करें
    const url = new URL(window.location.href);
    url.searchParams.set('active_alerts_page', page);
    
    // Page reload करें
    window.location.href = url.toString();
}

function showSecurityAlertsModal() {
    const modal = new bootstrap.Modal(document.getElementById('securityAlertsModal'));
    
    // Modal खुलने पर data load करें
    $('#securityAlertsModal').on('shown.bs.modal', function() {
        loadActiveSecurityAlerts(1);
    });
    
    // Modal बंद होने पर event clean करें
    $('#securityAlertsModal').on('hidden.bs.modal', function() {
        // Destroy DataTable if exists
        if ($.fn.DataTable.isDataTable('#securityAlertsDataTable')) {
            $('#securityAlertsDataTable').DataTable().destroy();
        }
        
        // ✅ IMPORTANT: Clean up all pagination events
        $(document).off('click', '.pagination-link');
        $('.pagination-link').off('click');
    });
    
    modal.show();
}

function loadActiveSecurityAlerts(page = 1) {
    const modalBody = $('#securityAlertsModalBody');
    
    modalBody.html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading security alerts...</p>
        </div>
    `);
    
    // ✅ ADD: Clean up previous events before loading new content
    $(document).off('click', '.pagination-link');
    $('.pagination-link').off('click');
    
    // Fetch data via AJAX
    fetch(`/vms/dashboard/active-security-alerts-ajax?page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const html = `
                    <div class="table-responsive">
                        <table class="table table-hover" id="securityAlertsDataTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Visitor Name</th>
                                    <th>Contact No</th>
                                    <th>IC No</th>
                                    <th>Host</th>
                                    <th>Location</th>
                                    <th>Reason</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.html}
                            </tbody>
                        </table>
                    </div>
                    
                    ${data.pagination ? `
                    <div class="d-flex justify-content-center mt-3">
                        ${data.pagination}
                    </div>
                    <div class="text-center text-muted mt-2">
                        Showing ${(data.current_page - 1) * data.per_page + 1} 
                        to ${Math.min(data.current_page * data.per_page, data.total)} 
                        of ${data.total} entries
                    </div>
                    ` : ''}
                `;
                
                modalBody.html(html);
                
                // ✅ UPDATE: Initialize DataTable with proper destroy first
                if ($.fn.DataTable.isDataTable('#securityAlertsDataTable')) {
                    $('#securityAlertsDataTable').DataTable().destroy();
                }
                
                $('#securityAlertsDataTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                    responsive: true,
                    order: [[7, 'desc']], // Sort by date column
                    searching: true,
                    paging: false, // We have custom pagination
                    info: false,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search alerts..."
                    }
                });
                
                // ✅ UPDATE: Attach pagination events AFTER content is loaded
                attachSecurityAlertsPaginationEvents();
            } else {
                modalBody.html(`
                    <div class="alert alert-danger">
                        <p class="mb-0">Error loading security alerts: ${data.message || 'Unknown error'}</p>
                    </div>
                `);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.html(`
                <div class="alert alert-danger">
                    <p class="mb-0">Error loading security alerts. Please try again.</p>
                </div>
            `);
        });
}

function attachSecurityAlertsPaginationEvents() {
    // First, remove ALL existing pagination events
    $(document).off('click', '.pagination-link');
    
    // Use event delegation for dynamic content
    $(document).on('click', '.pagination-link', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Check if link is disabled
        if ($(this).parent().hasClass('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        
        const page = $(this).data('page');
        if (page) {
            console.log('Loading page:', page);
            loadActiveSecurityAlerts(page);
        }
        
        return false;
    });
}

function attachAllPaginationEvents() {
    // Remove all existing pagination events
    $(document).off('click', '.pagination-link');
    
    // Attach to upcoming appointments
    $(document).on('click', '#upcomingAppointmentsPagination .pagination-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadUpcomingAppointments(page);
    });
    
    // Attach to security alerts
    $(document).on('click', '#securityAlertsModalBody .pagination-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadActiveSecurityAlerts(page);
    });
}

// ✅ Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    attachAllPaginationEvents();
});

function loadSecurityAlertsViaAjax() {
    const modal = new bootstrap.Modal(document.getElementById('securityAlertsModal'));
    
    modal.show();
    
    // Show loading
    $('#securityAlertsDataTable tbody').html(`
        <tr>
            <td colspan="9" class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading security alerts...</p>
            </td>
        </tr>
    `);
    
    // Fetch data via AJAX
    $.ajax({
        url: '/dashboard/security-alerts-data',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.length > 0) {
                let html = '';
                response.data.forEach(function(alert, index) {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${alert.visitor_name}</td>
                            <td>${alert.contact_no}</td>
                            <td>${alert.ic_no}</td>
                            <td>${alert.host}</td>
                            <td>${alert.location}</td>
                            <td>${alert.reason}</td>
                            <td>${alert.date_time}</td>
                            <td>
                                <button class="btn btn-sm btn-info view-details" data-id="${alert.id}">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $('#securityAlertsDataTable tbody').html(html);
                
                // Initialize client-side DataTable
                if ($.fn.DataTable.isDataTable('#securityAlertsDataTable')) {
                    $('#securityAlertsDataTable').DataTable().destroy();
                }
                
                $('#securityAlertsDataTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                    responsive: true,
                    order: [[7, 'desc']],
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search alerts...",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ alerts",
                        infoEmpty: "Showing 0 to 0 of 0 alerts",
                        infoFiltered: "(filtered from _MAX_ total alerts)",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            } else {
                $('#securityAlertsDataTable tbody').html(`
                    <tr>
                        <td colspan="9" class="text-center">No active security alerts found</td>
                    </tr>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading security alerts:', error);
            $('#securityAlertsDataTable tbody').html(`
                <tr>
                    <td colspan="9" class="text-center text-danger">
                        Error loading data. Please try again.
                    </td>
                </tr>
            `);
        }
    });
}


function updateCriticalAlert(alertData) {
    let html = '';
    
    if (alertData.alert_type === 'access_denied') {
        html = `
            <div class="col-12">
                <div class="critical-alert-box" 
                     id="currentCriticalAlert" 
                     data-alert-id="${alertData.log_id}"
                     data-alert-type="access_denied"
                     data-staff-no="${alertData.staff_no || ''}"
                     data-card-no="${alertData.card_no || ''}"
                     data-location="${alertData.location || ''}"
                     data-original-location="${alertData.original_location || alertData.location || ''}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="critical-title mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Critical Security Alert
                            <span class="badge bg-${alertData.priority == 'high' ? 'danger' : (alertData.priority == 'medium' ? 'warning' : 'secondary')} ms-2">
                                ${alertData.priority ? alertData.priority.charAt(0).toUpperCase() + alertData.priority.slice(1) : 'Medium'} Priority
                            </span>
                        </h5>
                        <button class="btn btn-sm btn-outline-light" onclick="acknowledgeAlert()">
                            <i class="fas fa-check me-1"></i> Acknowledge
                        </button>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4 col-12 mb-3">
                            <p class="label mb-1">Incident Type</p>
                            <p class="value mb-0">Unauthorized Access Attempt</p>
                        </div>

                        <div class="col-md-4 col-12 mb-3">
                            <p class="label mb-1">Location</p>
                            <p class="value mb-0">${alertData.location || 'N/A'}</p>
                        </div>

                        <div class="col-md-4 col-12 mb-3">
                            <p class="label mb-1">Time</p>
                            <p class="value mb-0">
                                ${alertData.created_at || 'N/A'} 
                                ${alertData.time_ago ? '(' + alertData.time_ago + ')' : ''}
                                <br>
                            </p>
                        </div>
                    </div>

                    <p class="description mt-2 mb-3">
                        ${alertData.visitor_name || 'Unknown'} on the restricted watchlist attempted to gain entry.
                    </p>

                    <div>
                        <button class="btn btn-danger btn-sm" onclick="showSecurityAlertsModal()">
                            <i class="fas fa-eye me-1"></i> View Incident
                        </button>
                    </div>
                </div>
            </div>
        `;
    } else {
        html = `
            <div class="col-12">
                <div class="critical-alert-box" 
                     id="currentCriticalAlert" 
                     data-alert-id="${alertData.log_id}"
                     data-alert-type="visitor_overstay"
                     data-staff-no="${alertData.staff_no || ''}"
                     data-card-no="${alertData.card_no || ''}"
                     data-location="${alertData.location || ''}"
                     data-original-location="${alertData.original_location || alertData.location || ''}"
                     data-date-of-visit-from="${alertData.overstay_details?.date_of_visit_from || ''}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="critical-title mb-0">
                            <i class="fas fa-clock me-2"></i>Visitor Overstay Alert
                            <span class="badge bg-${alertData.priority == 'high' ? 'danger' : (alertData.priority == 'medium' ? 'warning' : 'secondary')} ms-2">
                                ${alertData.priority ? alertData.priority.charAt(0).toUpperCase() + alertData.priority.slice(1) : 'Medium'} Priority
                            </span>
                        </h5>
                        <button class="btn btn-sm btn-outline-light" onclick="acknowledgeAlert()">
                            <i class="fas fa-check me-1"></i> Acknowledge
                        </button>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4 col-12 mb-3">
                            <p class="label mb-1">Incident Type</p>
                            <p class="value mb-0">Visitor Overstay Alert</p>
                        </div>

                        <div class="col-md-4 col-12 mb-3">
                            <p class="label mb-1">Location</p>
                            <p class="value mb-0">${alertData.location || 'N/A'}</p>
                        </div>

                        <div class="col-md-4 col-12 mb-3">
                            <p class="label mb-1">Time</p>
                            <p class="value mb-0">
                                ${alertData.created_at || 'N/A'} 
                                ${alertData.time_ago ? '(' + alertData.time_ago + ')' : ''}
                                <br>
                            </p>
                        </div>
                    </div>

                    <p class="description mt-2 mb-3">
                        ${alertData.visitor_name || 'Unknown'} has exceeded their scheduled visit time by ${alertData.overstay_details?.overstay_duration || 'unknown time'}.
                        Expected end: ${alertData.overstay_details?.expected_end_time || 'N/A'}
                    </p>

                    <div>
                        <button class="btn btn-warning btn-sm" onclick="showVisitorOverstayModal()">
                            <i class="fas fa-clock me-1"></i> View Overstay Details
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    document.getElementById('criticalAlertSection').innerHTML = html;
}

function refreshDashboardCounts() {
    console.log('Refreshing dashboard counts...');
    
    fetch('/vms/dashboard/refresh-counts', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Dashboard counts refreshed:', data);
        
        if (data.success) {
            // Update Active Security Alerts card (OVERALL COUNT - NO TIME LIMIT)
            if (data.activeSecurityAlertsCount !== undefined) {
                const activeAlertsCard = document.getElementById('activeSecurityAlertsCount');
                if (activeAlertsCard) {
                    activeAlertsCard.textContent = data.activeSecurityAlertsCount;
                }
            }
            
            // Update Denied Access card (24 HOURS ONLY)
            if (data.deniedAccessCount24h !== undefined) {
                const deniedAccessCard = document.getElementById('deniedAccessCount24h');
                if (deniedAccessCard) {
                    deniedAccessCard.textContent = data.deniedAccessCount24h;
                }
            }
            
            // Update Visitor Overstay card
            if (data.visitorOverstayCount !== undefined) {
                const overstayCard = document.getElementById('visitorOverstayCount');
                if (overstayCard) {
                    overstayCard.textContent = data.visitorOverstayCount;
                }
            }
        } else {
            console.error('Error refreshing counts:', data.message);
        }
    })
    .catch(error => {
        console.error('Error refreshing counts:', error);
    });
}

function loadGraphData() {
    const fromDate = document.getElementById('graphFromDate').value;
    const toDate = document.getElementById('graphToDate').value;

    if (!fromDate || !toDate) {
        alert('Please select both from date and to date');
        return;
    }

    if (new Date(fromDate) > new Date(toDate)) {
        alert('From date cannot be greater than To date');
        return;
    }

    // Show loading spinner
    document.getElementById('graphLoadingSpinner').classList.remove('d-none');
    document.getElementById('graphContainer').classList.add('d-none');

    // Make AJAX call to get graph data
    fetch('{{ route("dashboard.graph.data") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            from_date: fromDate,
            to_date: toDate
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('graphLoadingSpinner').classList.add('d-none');
        document.getElementById('graphContainer').classList.remove('d-none');

        if (data.success) {
            initializeChart(data.labels, data.data);
        } else {
            alert('Error loading graph data: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('graphLoadingSpinner').classList.add('d-none');
        document.getElementById('graphContainer').classList.remove('d-none');
        alert('Error loading graph data');
    });
}
document.addEventListener('DOMContentLoaded', function() {
    initializeChart(
        @json($hourlyTrafficData['labels']),
        @json($hourlyTrafficData['data'])
    );
});

// Screen resize par width adjust karein
function adjustCriticalAlertWidth() {
    const criticalAlert = document.querySelector('.critical-alert-box');
    if (criticalAlert) {
        // Parent container ki width lein
        const parentWidth = criticalAlert.parentElement.clientWidth;
        
        // Max width set karein (optional)
        const maxWidth = Math.min(parentWidth, 1200); // 1200px max
        
        criticalAlert.style.width = '100%'; // Always 100%
        criticalAlert.style.maxWidth = maxWidth + 'px';
    }
}


// DataTable Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Access Denied Table
    if ($('#accessDeniedTable').length) {
        $('#accessDeniedTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[6, 'desc']], // Sort by date column descending
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search incidents...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ incidents",
                infoEmpty: "Showing 0 to 0 of 0 incidents",
                infoFiltered: "(filtered from _MAX_ total incidents)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }

    // Initialize Visitor Overstay Table
    if ($('#visitorOverstayTable').length) {
        $('#visitorOverstayTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[5, 'desc']], // Sort by check-in time descending
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search overstays...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ overstays",
                infoEmpty: "Showing 0 to 0 of 0 overstays",
                infoFiltered: "(filtered from _MAX_ total overstays)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            columnDefs: [
                {
                    targets: 8, // Overstay Duration column
                    className: 'text-center'
                }
            ]
        });
    }
});



function updateVisitorsPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('visitors_page', page);
    window.location.href = url.toString();
}

function updateTodayPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('today_page', page);
    window.location.href = url.toString();
}

function updateCheckoutsPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('checkouts_page', page);
    window.location.href = url.toString();
}

// Modal Show Functions
function showVisitorsOnSiteModal() {
    const modal = new bootstrap.Modal(document.getElementById('visitorsOnSiteModal'));
    
    // Initialize DataTable when modal opens
    setTimeout(function() {
        if ($.fn.DataTable.isDataTable('#visitorsOnSiteTable')) {
            $('#visitorsOnSiteTable').DataTable().destroy();
        }
        
        $('#visitorsOnSiteTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[3, 'desc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search visitors...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ visitors",
                infoEmpty: "Showing 0 to 0 of 0 visitors",
                infoFiltered: "(filtered from _MAX_ total visitors)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }, 100);
    
    modal.show();
}

function showExpectedTodayModal() {
    const modal = new bootstrap.Modal(document.getElementById('expectedTodayModal'));
    
    // Initialize DataTable when modal opens
    setTimeout(function() {
        if ($.fn.DataTable.isDataTable('#expectedTodayTable')) {
            $('#expectedTodayTable').DataTable().destroy();
        }
        
        $('#expectedTodayTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[3, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search appointments...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ appointments",
                infoEmpty: "Showing 0 to 0 of 0 appointments",
                infoFiltered: "(filtered from _MAX_ total appointments)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }, 100);
    
    modal.show();
}

function showCheckoutsTodayModal() {
    const modal = new bootstrap.Modal(document.getElementById('checkoutsTodayModal'));
    
    // Show loading
    $('#checkoutsModalContent').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading latest check-out data...</p>
        </div>
    `);
    
    modal.show();
    
    // Fetch latest data via AJAX
    fetch('/vms/dashboard/checkouts-today-modal-data', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCheckoutsModalContent(data.data);
        } else {
            $('#checkoutsModalContent').html(`
                <div class="alert alert-danger">
                    <p class="mb-0">Error loading check-outs data: ${data.message || 'Unknown error'}</p>
                </div>
            `);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        $('#checkoutsModalContent').html(`
            <div class="alert alert-danger">
                <p class="mb-0">Error loading check-outs data. Please try again.</p>
            </div>
        `);
    });
}

function updateCheckoutsModalContent(checkoutsData) {
    let html = '';
    
    if (checkoutsData.length > 0) {
        html = `
            <div class="table-responsive">
                <table class="table table-hover" id="checkoutsTodayTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Visitor Name</th>
                            <th>Host</th>
                            <th>Check-in Time</th>
                            <th>Check-out Time</th>
                            <th>Duration</th>
                            <th>Staff No</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        checkoutsData.forEach((checkout, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${checkout.visitor_name || 'N/A'}</td>
                    <td>${checkout.host || 'N/A'}</td>
                    <td>${checkout.check_in_time || 'N/A'}</td>
                    <td>${checkout.check_out_time || 'N/A'}</td>
                    <td>${checkout.duration || 'N/A'}</td>
                    <td>${checkout.staff_no || 'N/A'}</td>
                    <td>${checkout.location || 'N/A'}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    } else {
        html = `
            <div class="alert alert-info">
                <p class="mb-0">No check-outs found for today that match both conditions:</p>
                <ul class="mb-0">
                    <li>Location: Turnstile</li>
                    <li>Device Type: check_out</li>
                </ul>
            </div>
        `;
    }
    
    $('#checkoutsModalContent').html(html);
    
    // Initialize DataTable if we have data
    if (checkoutsData.length > 0) {
        setTimeout(function() {
            if ($.fn.DataTable.isDataTable('#checkoutsTodayTable')) {
                $('#checkoutsTodayTable').DataTable().destroy();
            }
            
            $('#checkoutsTodayTable').DataTable({
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                responsive: true,
                order: [[4, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search check-outs...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ check-outs",
                    infoEmpty: "Showing 0 to 0 of 0 check-outs",
                    infoFiltered: "(filtered from _MAX_ total check-outs)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        }, 100);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // ✅ FIX: Use client-side DataTables instead of server-side
    // Visitors On-Site Table
    if ($('#visitorsOnSiteTable').length) {
        $('#visitorsOnSiteTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[3, 'desc']], // Sort by check-in time
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search visitors...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ visitors",
                infoEmpty: "Showing 0 to 0 of 0 visitors",
                infoFiltered: "(filtered from _MAX_ total visitors)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
    
    // Today's Appointments Table
    if ($('#expectedTodayTable').length) {
        $('#expectedTodayTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[3, 'asc']], // Sort by appointment time
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search appointments...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ appointments",
                infoEmpty: "Showing 0 to 0 of 0 appointments",
                infoFiltered: "(filtered from _MAX_ total appointments)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
    
    // Check-outs Today Table
    if ($('#checkoutsTodayTable').length) {
        $('#checkoutsTodayTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            responsive: true,
            order: [[4, 'desc']], // Sort by check-out time
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search check-outs...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ check-outs",
                infoEmpty: "Showing 0 to 0 of 0 check-outs",
                infoFiltered: "(filtered from _MAX_ total check-outs)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
});

// DataTable initialization function
function initSecurityAlertsDataTable() {
    if ($.fn.DataTable.isDataTable('#securityAlertsDataTable')) {
        $('#securityAlertsDataTable').DataTable().destroy();
    }
    
    $('#securityAlertsDataTable').DataTable({
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        responsive: true,
        order: [[7, 'desc']], // Sort by date column
        searching: true,
        paging: false, // हमारा custom pagination है
        info: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search alerts..."
        },
        drawCallback: function(settings) {
            // DataTable के अपने pagination buttons hide करें
            $(this.api().table().container()).find('.dataTables_paginate').hide();
        }
    });
}

// Window resize par function call karein
window.addEventListener('resize', adjustCriticalAlertWidth);

// Page load par bhi call karein
document.addEventListener('DOMContentLoaded', adjustCriticalAlertWidth);
</script>
@endsection

