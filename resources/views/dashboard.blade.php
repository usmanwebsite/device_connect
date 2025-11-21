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
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <h2>{{ count($visitorsOnSite) }}</h2>
                <p>Visitors On-Site</p>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <h2>{{ $todayAppointmentCount }}</h2>
                <p>Expected Today</p>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <h2>89</h2>
                <p>Check-outs Today</p>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
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
                <table class="table table-dark table-hover mt-3">
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

            {{-- Dummy Graph --}}
            <div class="content-card text-center pb-4">
                <h5 class="mb-3">Visitor Traffic by Hour (Today)</h5>
                <canvas id="trafficChart" height="120"></canvas>
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
                    
                    {{-- ✅ Agar koi today appointment nahi hai --}}
                    @if(empty($todayAppointments))
                    <li class="list-group-item text-center">No appointments today</li>
                    @endif
                </ul>
            </div>

            <div class="content-card mt-4">
                <h5 class="mb-3">Recent Alerts</h5>

                @foreach($deniedAccessLogs as $alert)
                    <div class="recent-alert">
                        <span class="badge bg-danger">Access Denied</span>

                        <p>
                            <strong>Staff No:</strong> {{ $alert->staff_no }} <br>
                            <strong>Location:</strong> {{ $alert->location_name ?? 'Unknown Location' }}
                        </p>

                        <small>
                            <strong>Reason:</strong> 
                            {{ $alert->reason ? $alert->reason : 'Other Reason' }}
                        </small>
                        <br>

                        <small>
                            <strong>Time:</strong> 
                            {{ \Carbon\Carbon::parse($alert->created_at)->format('d M Y h:i A') }}
                        </small>
                    </div>
                @endforeach

                {{-- ✅ Visitor Overstay Alerts - Java API se dateOfVisitTo check karke --}}
                @foreach($visitorOverstayAlerts as $alert)
                <div class="recent-alert">
                    <span class="badge bg-warning">Visitor Overstay</span>
                    <p><strong>{{ $alert['visitor_name'] }}</strong> - Staff No: {{ $alert['staff_no'] }}</p>
                    <small>
                        Expected End: {{ $alert['expected_end_time'] }} | 
                        Current: {{ $alert['current_time'] }} | 
                        Overstay: {{ $alert['overstay_duration'] }}
                    </small><br>
                    <small>Host: {{ $alert['host'] }} | Location: {{ $alert['location'] }}</small>
                </div>
                @endforeach

                {{-- ✅ Access Denied Alerts --}}
                @php
                    use App\Models\DeviceAccessLog;
                    $deniedAccessLogs = DeviceAccessLog::where('access_granted', 0)
                        ->whereDate('created_at', now()->format('Y-m-d'))
                        ->orderBy('created_at', 'desc')
                        ->limit(3)
                        ->get();
                @endphp

                @foreach($deniedAccessLogs as $alert)
                <div class="recent-alert">
                    <span class="badge bg-danger">Access Denied</span>
                    <p>Staff No: {{ $alert->staff_no }} - {{ $alert->location_name ?? 'Unknown Location' }}</p>
                    <small>Reason: {{$alert->reason ? $alert->reason : 'Other Reason'}}</small><br>
                    <small>Time: {{ \Carbon\Carbon::parse($alert->created_at)->format('h:i A') }}</small>
                </div>
                @endforeach

                {{-- ✅ Agar koi alerts nahi hai toh default alerts show karein --}}
                @if(empty($visitorOverstayAlerts) && count($deniedAccessLogs) == 0)
                <div class="recent-alert">
                    <span class="badge bg-danger">Denied Entry</span>
                    <p>Visitor on restricted list attempted check-in.</p>
                </div>

                <div class="recent-alert">
                    <span class="badge bg-warning">Visitor Overstay</span>
                    <p>Check-in time exceeded expected duration.</p>
                </div>

                <div class="recent-alert">
                    <span class="badge bg-info">Emergency Alert</span>
                    <p>Incident detected at East Entrance.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- Chart.js Dynamic Graph --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var ctx = document.getElementById('trafficChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: @json($hourlyTrafficData['labels']),
        datasets: [{
            label: 'Visitors',
            data: @json($hourlyTrafficData['data']),
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true,
                labels: {
                    color: 'white'
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: 'white',
                    maxRotation: 45
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    color: 'white',
                    stepSize: 1
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            }
        }
    }
});
</script>
@endsection