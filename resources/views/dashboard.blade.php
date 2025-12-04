@extends('layout.main_layout')

@section('content')
<div class="container-fluid dashboard-wrapper">

    {{-- Critical Alert Section --}}
    <div class="row mb-4" id="criticalAlertSection">
        @if($criticalAlert)
        <div class="col-12">
            <div class="critical-alert-box" id="currentCriticalAlert" data-alert-id="{{ $criticalAlert['log_id'] }}">
                <div class="d-flex justify-content-between">
                    <h5 class="critical-title">Critical Security Alert</h5>
                    {{-- <button class="critical-close" onclick="closeCriticalAlert()">&times;</button> --}}
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 col-12">
                        <p class="label">Incident Type</p>
                        <p class="value">Unauthorized Access Attempt</p> {{-- ✅ Static --}}
                    </div>

                    <div class="col-md-4 col-12">
                        <p class="label">Location</p>
                        <p class="value">{{ $criticalAlert['location'] }}</p>
                    </div>

                    <div class="col-md-4 col-12">
                        <p class="label">Time</p>
                        <p class="value">{{ $criticalAlert['created_at'] }} ({{ $criticalAlert['time_ago'] }})</p>
                    </div>
                </div>

                <p class="description mt-2">
                    {{ $criticalAlert['visitor_name'] }} on the restricted watchlist attempted to gain entry.                    
                </p>

                <div class="mt-3">
                    <button class="btn btn-danger btn-sm" onclick="viewCriticalIncidentDetails({{ $criticalAlert['log_id'] }})">View Incident</button>
                    <button class="btn btn-outline-light btn-sm" onclick="acknowledgeAlert()">Acknowledge</button>
                </div>
            </div>
        </div>
        @else
        {{-- ✅ Agar koi critical alert nahi hai --}}
        <div class="col-12">
            <div class="alert alert-success">
                <h5 class="alert-heading">All Clear</h5>
                <p class="mb-0">No critical security alerts at this moment.</p>
            </div>
        </div>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
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
                <p>Check-outs Today</p>
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
                <table class="table table-hover mt-3">
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
                        
                        {{-- ✅ Agar koi visitor on-site nahi hai --}}
                        @if(empty($visitorsOnSite))
                        <tr>
                            <td colspan="4" class="text-center">No visitors currently on-site</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            {{-- Graph with Date Filter --}}
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
            <div class="content-card">
                <h5 class="mb-3">Upcoming Appointments</h5>

                <ul class="list-group list-group-flush dark-list">
                    @foreach($upcomingAppointments as $appointment)
                    <li class="list-group-item">
                        <strong>{{ $appointment['full_name'] }}</strong> – 
                        {{ \Carbon\Carbon::parse($appointment['date_from'])->format('M d, h:i A') }}
                    </li>
                    @endforeach

                    @if(empty($upcomingAppointments))
                    <li class="list-group-item text-center">No upcoming appointments</li>
                    @endif
                </ul>
            </div>

            <div class="content-card mt-4">
                <h5 class="mb-3">Today's Appointments</h5>

                <ul class="list-group list-group-flush dark-list">
                    @foreach($todayAppointments as $appointment)
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

            {{-- ✅ CORRECTED: Recent Alerts Section with ONE card per row --}}
            <div class="content-card mt-4" style="background-color: #F8f9fa;">
                <h5 class="mb-3">Recent Alerts</h5>
                
                <div class="row g-3">
                    {{-- Access Denied Card - takes full width --}}
                    <div class="col-12">
                        <div class="stat-card clickable-card alert-card" onclick="showAccessDeniedModal()">
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="text-danger">{{ $deniedAccessCount ?? 0 }}</h2>
                                <span class="badge bg-danger">Access Denied</span>
                            </div>
                            <p>Access Denied Incidents</p>
                            {{-- <small class="text-muted">Click to view details</small> --}}
                        </div>
                    </div>

                    {{-- Visitor Overstay Card - takes full width --}}
                    <div class="col-12">
                        <div class="stat-card clickable-card alert-card" onclick="showVisitorOverstayModal()">
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="text-warning">{{ $visitorOverstayCount ?? 0 }}</h2>
                                <span class="badge bg-warning">Overstay</span>
                            </div>
                            <p>Visitor Overstay Alerts</p>
                        </div>
                    </div>
                </div>
            </div>
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
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Check-in Time</th>
                                <th>Location</th>
                                <th>Staff No</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visitorsOnSite as $visitor)
                            <tr>
                                <td>{{ $visitor['full_name'] ?? 'N/A' }}</td>
                                <td>{{ $visitor['person_visited'] ?? 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($visitor['created_at'])->format('h:i A') }}</td>
                                <td>{{ $visitor['location_name'] ?? 'N/A' }}</td>
                                <td>{{ $visitor['staff_no'] ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                            
                            @if(empty($visitorsOnSite))
                            <tr>
                                <td colspan="5" class="text-center">No visitors currently on-site</td>
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
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Appointment Time</th>
                                <th>Contact No</th>
                                <th>IC No</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($todayAppointments as $appointment)
                            <tr>
                                <td>{{ $appointment['full_name'] ?? 'N/A' }}</td>
                                <td>{{ $appointment['name_of_person_visited'] ?? 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($appointment['date_from'])->format('h:i A') }}</td>
                                <td>{{ $appointment['contact_no'] ?? 'N/A' }}</td>
                                <td>{{ $appointment['ic_no'] ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                            
                            @if(empty($todayAppointments))
                            <tr>
                                <td colspan="5" class="text-center">No appointments today</td>
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
                <h5 class="modal-title">Today's Check-outs ({{ count($checkoutsTodayModalData ?? []) }})</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if(!empty($checkoutsTodayModalData))
                    <div class="table-responsive">
                        <table class="table table-hover">
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
                                @foreach($checkoutsTodayModalData as $index => $checkout)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $checkout['visitor_name'] }}</td>
                                    <td>{{ $checkout['host'] }}</td>
                                    <td>{{ $checkout['check_in_time'] }}</td>
                                    <td>{{ $checkout['check_out_time'] }}</td>
                                    <td>{{ $checkout['duration'] }}</td>
                                    <td>{{ $checkout['staff_no'] }}</td>
                                    <td>{{ $checkout['location'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info">
                        <p class="mb-0">No check-outs found for today that match both conditions (Turnstile location and check_out type).</p>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
{{-- Modal 4: Active Security Alerts --}}
<div class="modal fade" id="securityAlertsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Active Security Alerts - Access Denied Records</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Staff No</th>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Contact No</th>
                                <th>IC No</th>
                                <th>Location</th>
                                <th>Reason</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($enrichedDeniedAccessLogs as $enrichedLog)
                            <tr>
                                <td>{{ $enrichedLog['log']->staff_no }}</td>
                                <td>{{ $enrichedLog['visitor_details']['fullName'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['personVisited'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['contactNo'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['icNo'] }}</td>
                                <td>{{ $enrichedLog['log']->location_name ?? 'Unknown Location' }}</td>
                                <td>{{ $enrichedLog['log']->reason ? $enrichedLog['log']->reason : 'Other Reason' }}</td>
                                <td>{{ \Carbon\Carbon::parse($enrichedLog['log']->created_at)->format('d M Y h:i A') }}</td>
                            </tr>
                            @endforeach
                            
                            @if(empty($enrichedDeniedAccessLogs))
                            <tr>
                                <td colspan="8" class="text-center">No security alerts found</td>
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
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Staff No</th>
                                <th>Visitor Name</th>
                                <th>Host</th>
                                <th>Contact No</th>
                                <th>IC No</th>
                                <th>Location</th>
                                <th>Reason</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($enrichedDeniedAccessLogs as $enrichedLog)
                            <tr>
                                <td>{{ $enrichedLog['log']->staff_no }}</td>
                                <td>{{ $enrichedLog['visitor_details']['fullName'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['personVisited'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['contactNo'] }}</td>
                                <td>{{ $enrichedLog['visitor_details']['icNo'] }}</td>
                                <td>{{ $enrichedLog['log']->location_name ?? 'Unknown Location' }}</td>
                                <td>{{ $enrichedLog['log']->reason ? $enrichedLog['log']->reason : 'Other Reason' }}</td>
                                <td>{{ \Carbon\Carbon::parse($enrichedLog['log']->created_at)->format('d M Y h:i A') }}</td>
                            </tr>
                            @endforeach
                            
                            @if(empty($enrichedDeniedAccessLogs))
                            <tr>
                                <td colspan="8" class="text-center">No access denied incidents found</td>
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
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Visitor Name</th>
                                <th>Staff No</th>
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
                                <td>{{ $alert['staff_no'] }}</td>
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
                            
                            @if(empty($enrichedOverstayAlerts))
                            <tr>
                                <td colspan="10" class="text-center">No visitor overstay alerts found</td>
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
                label: 'Visitors',
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
// Modal Show Functions
function showVisitorsOnSiteModal() {
    const modal = new bootstrap.Modal(document.getElementById('visitorsOnSiteModal'));
    modal.show();
}
function showExpectedTodayModal() {
    const modal = new bootstrap.Modal(document.getElementById('expectedTodayModal'));
    modal.show();
}

function showCheckoutsTodayModal() {
    const modal = new bootstrap.Modal(document.getElementById('checkoutsTodayModal'));
    
    // Show loading while fetching fresh data
    const modalBody = document.querySelector('#checkoutsTodayModal .modal-body');
    const originalContent = modalBody.innerHTML; // Store original content
    
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading latest check-out data...</p>
        </div>
    `;
    
    modal.show();   
    // Fetch latest data via AJAX
    fetch('/dashboard/checkouts-today-modal-data', {
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
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <p class="mb-0">Error loading check-outs data: ${data.message || 'Unknown error'}</p>
                </div>
                ${originalContent}
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <p class="mb-0">Error loading check-outs data. Please try again.</p>
            </div>
            ${originalContent}
        `;
    });
}
function updateCheckoutsModalContent(checkoutsData) {
    const modalBody = document.querySelector('#checkoutsTodayModal .modal-body');
    const modalTitle = document.querySelector('#checkoutsTodayModal .modal-title');
    
    if (checkoutsData.length > 0) {
        let html = `
            <div class="table-responsive">
                <table class="table table-hover">
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
        
        modalBody.innerHTML = html;
        modalTitle.textContent = `Today's Check-outs (${checkoutsData.length})`;
    } else {
        modalBody.innerHTML = `
            <div class="alert alert-info">
                <p class="mb-0">No check-outs found for today that match both conditions:</p>
                <ul class="mb-0">
                    <li>Location: Turnstile</li>
                    <li>Device Type: check_out</li>
                </ul>
            </div>
        `;
        modalTitle.textContent = 'Today\'s Check-outs (0)';
    }
}


let currentCriticalAlertId = null;

// ✅ UPDATED: Critical Alert Functions
function acknowledgeAlert() {
    const alertBox = document.getElementById('currentCriticalAlert');
    if (!alertBox) return;
    
    const alertId = alertBox.dataset.alertId;
    
    // Show loading state
    const acknowledgeBtn = alertBox.querySelector('.btn-outline-light');
    const originalText = acknowledgeBtn.innerHTML;
    acknowledgeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Acknowledging...';
    acknowledgeBtn.disabled = true;
    
    // AJAX call to acknowledge alert
    fetch('/dashboard/acknowledge-alert', {
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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.has_next && data.next_alert) {
                // Update with next alert
                updateCriticalAlert(data.next_alert);
                // Refresh counts
                refreshDashboardCounts();
                window.location.reload();
            } else {
                // No more alerts, show success message
                document.getElementById('criticalAlertSection').innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-success">
                            <h5 class="alert-heading">All Alerts Acknowledged</h5>
                            <p class="mb-0">All critical security alerts have been acknowledged.</p>
                        </div>
                    </div>
                `;
                // Refresh counts
                // refreshDashboardCounts();
            }

            updateAllDashboardCountsImmediately();
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
    fetch('/dashboard/hide-critical-alert', {
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

function viewCriticalIncidentDetails(alertId) {
    currentCriticalAlertId = alertId;
    
    // Show loading in modal
    const modalBody = document.querySelector('#securityAlertsModal .modal-body');
    const originalContent = modalBody.innerHTML;
    
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading incident details...</p>
        </div>
    `;
    
    // Fetch specific alert details
    fetch('/dashboard/get-critical-alert-details', {
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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update modal with specific alert details
            updateSecurityAlertsModalForCriticalAlert(data.alert);
        } else {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <p class="mb-0">Error loading incident details: ${data.message}</p>
                </div>
                ${originalContent}
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <p class="mb-0">Error loading incident details. Please try again.</p>
            </div>
            ${originalContent}
        `;
    });
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('securityAlertsModal'));
    modal.show();
}

function updateSecurityAlertsModalForCriticalAlert(alertData) {
    const modalBody = document.querySelector('#securityAlertsModal .modal-body');
    const modalTitle = document.querySelector('#securityAlertsModal .modal-title');
    
    if (!alertData || !alertData.log) {
        modalBody.innerHTML = `
            <div class="alert alert-warning">
                <p class="mb-0">No details found for this incident.</p>
            </div>
        `;
        return;
    }
    
    const log = alertData.log;
    const visitor = alertData.visitor_details;
    
    modalTitle.textContent = 'Critical Security Alert Details';
    
    const html = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Staff No</th>
                        <th>Visitor Name</th>
                        <th>Host</th>
                        <th>Contact No</th>
                        <th>IC No</th>
                        <th>Location</th>
                        <th>Reason</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>${log.staff_no || 'N/A'}</td>
                        <td>${visitor.fullName || 'N/A'}</td>
                        <td>${visitor.personVisited || 'N/A'}</td>
                        <td>${visitor.contactNo || 'N/A'}</td>
                        <td>${visitor.icNo || 'N/A'}</td>
                        <td>${log.location_name || 'Unknown Location'}</td>
                        <td>${log.reason ? log.reason : 'Other Reason'}</td>
                        <td>${new Date(log.created_at).toLocaleString('en-US', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        })}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    modalBody.innerHTML = html;
}

// ✅ Reset modal when opened from Active Security Alerts card
function showSecurityAlertsModal() {
    const modal = new bootstrap.Modal(document.getElementById('securityAlertsModal'));
    const modalTitle = document.querySelector('#securityAlertsModal .modal-title');
    const modalBody = document.querySelector('#securityAlertsModal .modal-body');
    
    // Reset to original content
    modalTitle.textContent = 'Active Security Alerts - Access Denied Records';
    
    // Show original content (from PHP)
    @if(!empty($enrichedDeniedAccessLogs))
        const originalHtml = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Staff No</th>
                            <th>Visitor Name</th>
                            <th>Host</th>
                            <th>Contact No</th>
                            <th>IC No</th>
                            <th>Location</th>
                            <th>Reason</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrichedDeniedAccessLogs as $enrichedLog)
                        <tr>
                            <td>{{ $enrichedLog['log']->staff_no }}</td>
                            <td>{{ $enrichedLog['visitor_details']['fullName'] }}</td>
                            <td>{{ $enrichedLog['visitor_details']['personVisited'] }}</td>
                            <td>{{ $enrichedLog['visitor_details']['contactNo'] }}</td>
                            <td>{{ $enrichedLog['visitor_details']['icNo'] }}</td>
                            <td>{{ $enrichedLog['log']->location_name ?? 'Unknown Location' }}</td>
                            <td>{{ $enrichedLog['log']->reason ? $enrichedLog['log']->reason : 'Other Reason' }}</td>
                            <td>{{ \Carbon\Carbon::parse($enrichedLog['log']->created_at)->format('d M Y h:i A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        `;
        modalBody.innerHTML = originalHtml;
    @else
        modalBody.innerHTML = `
            <div class="alert alert-info">
                <p class="mb-0">No security alerts found</p>
            </div>
        `;
    @endif
    
    modal.show();
}

function updateCriticalAlert(alertData) {
    const html = `
        <div class="col-12">
            <div class="critical-alert-box" id="currentCriticalAlert" data-alert-id="${alertData.log_id}">
                <div class="d-flex justify-content-between">
                    <h5 class="critical-title">Critical Security Alert</h5>
                    <button class="critical-close" onclick="closeCriticalAlert()">&times;</button>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 col-12">
                        <p class="label">Incident Type</p>
                        <p class="value">Unauthorized Access Attempt</p>
                    </div>

                    <div class="col-md-4 col-12">
                        <p class="label">Location</p>
                        <p class="value">${alertData.location}</p>
                    </div>

                    <div class="col-md-4 col-12">
                        <p class="label">Time</p>
                        <p class="value">${alertData.created_at} (${alertData.time_ago})</p>
                    </div>
                </div>

                <p class="description mt-2">
                    ${alertData.visitor_name} on the restricted watchlist attempted to gain entry.                    
                </p>

                <div class="mt-3">
                    <button class="btn btn-danger btn-sm" onclick="viewCriticalIncidentDetails(${alertData.log_id})">View Incident</button>
                    <button class="btn btn-outline-light btn-sm" onclick="acknowledgeAlert()">Acknowledge</button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('criticalAlertSection').innerHTML = html;
    document.getElementById('criticalAlertSection').style.display = 'block';
}

function refreshDashboardCounts() {
    fetch('/dashboard/refresh-counts', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update Active Security Alerts count
            if (data.activeSecurityAlertsCount !== undefined) {
                const activeAlertsCard = document.querySelector('.stat-card:nth-child(4) h2');
                if (activeAlertsCard) {
                    activeAlertsCard.textContent = data.activeSecurityAlertsCount;
                }
            }
            
            // Update Denied Access count
            if (data.deniedAccessCount !== undefined) {
                const deniedAccessCard = document.querySelector('.alert-card:nth-child(1) h2');
                if (deniedAccessCard) {
                    deniedAccessCard.textContent = data.deniedAccessCount;
                }
            }
        }
    })
    .catch(error => {
        console.error('Error refreshing counts:', error);
    });
}

function showSecurityAlertsModal() {
    const modal = new bootstrap.Modal(document.getElementById('securityAlertsModal'));
    modal.show();
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
</script>
@endsection

