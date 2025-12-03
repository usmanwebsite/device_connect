@extends('layout.main_layout')

@section('content')
<div class="container-fluid dashboard-wrapper">

    {{-- Critical Alert Section --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="critical-alert-box">
                <div class="d-flex justify-content-between">
                    <h5 class="critical-title">Critical Security Alert</h5>
                    <button class="critical-close">&times;</button>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 col-12">
                        <p class="label">Incident Type</p>
                        <p class="value">Unauthorized Access Attempt</p>
                    </div>

                    <div class="col-md-4 col-12">
                        <p class="label">Location</p>
                        <p class="value">West Entrance, Loading Dock</p>
                    </div>

                    <div class="col-md-4 col-12">
                        <p class="label">Time</p>
                        <p class="value">10:52 AM (2 minutes ago)</p>
                    </div>
                </div>

                <p class="description mt-2">
                    An individual on the restricted watchlist attempted to gain entry. 
                    Security personnel have been dispatched. Awaiting status update.
                </p>

                <div class="mt-3">
                    <button class="btn btn-danger btn-sm">View Incident Details</button>
                    <button class="btn btn-outline-light btn-sm">Acknowledge</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Visitors On-Site --}}
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

        {{-- Card 3: Check-outs Today --}}
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

    {{-- Currently On-site + Graph --}}
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

                {{-- Graph Container --}}
                <div id="graphContainer">
                    <canvas id="trafficChart" height="120"></canvas>
                </div>
            </div>
        </div>

        {{-- Right Column --}}
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
                    
                    {{-- ✅ Agar koi upcoming appointment nahi hai --}}
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
                            {{-- <small class="text-muted">Click to view details</small> --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Rest of your modals remain exactly the same --}}
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

{{-- Modal 3: Check-outs Today (Dummy Data) --}}
<div class="modal fade" id="checkoutsTodayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Today's Check-outs</h5>
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
                                <th>Check-out Time</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>John Doe</td>
                                <td>Mr. Smith</td>
                                <td>09:00 AM</td>
                                <td>11:30 AM</td>
                                <td>2 hours 30 minutes</td>
                            </tr>
                            <tr>
                                <td>Jane Smith</td>
                                <td>Ms. Johnson</td>
                                <td>10:15 AM</td>
                                <td>12:45 PM</td>
                                <td>2 hours 30 minutes</td>
                            </tr>
                            <tr>
                                <td>Robert Brown</td>
                                <td>Dr. Wilson</td>
                                <td>01:30 PM</td>
                                <td>03:15 PM</td>
                                <td>1 hour 45 minutes</td>
                            </tr>
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
    modal.show();
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

