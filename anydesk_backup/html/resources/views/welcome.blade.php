@extends('layout.main_layout')

@section('content')
<div class="dashboard-container">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="dashboard-title">Activity Overview</h1>
                <div class="dashboard-actions">
                    <button class="btn btn-primary btn-sm">Refresh</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Critical Security Alert -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger security-alert">
                <div class="d-flex align-items-center">
                    <i class='bx bx-shield-x alert-icon'></i>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="alert-heading">Critical Security Alert</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Incident Type:</strong><br>
                                <span class="incident-detail">Unauthorized Access Attempt</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Details:</strong><br>
                                <span class="text-muted">Multiple failed login attempts detected</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Content & Metrics -->
    <div class="row mb-4">
        <!-- User Content -->
        <div class="col-md-6 mb-3">
            <div class="card metric-card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">User Content (Index)</h6>
                    <h4 class="metric-value">Accessed up: <span class="text-success">+12%</span></h4>
                </div>
            </div>
        </div>
        
        <!-- Metrics Grid -->
        <div class="col-md-6">
            <div class="row">
                <div class="col-6 mb-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <h6 class="card-subtitle mb-2 text-muted">Visitors On-Site</h6>
                            <div class="d-flex justify-content-center align-items-baseline">
                                <h3 class="metric-value me-2">142</h3>
                                <small class="text-success">+67</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 mb-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <h6 class="card-subtitle mb-2 text-muted">Corrected Today</h6>
                            <h3 class="metric-value">89</h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 mb-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <h6 class="card-subtitle mb-2 text-muted">Check-out Today</h6>
                            <h3 class="metric-value">89</h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 mb-3">
                    <div class="card metric-card alert-card">
                        <div class="card-body text-center">
                            <h6 class="card-subtitle mb-2 text-muted">Active Security Alerts</h6>
                            <h3 class="metric-value text-danger">3</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Currently On-Site -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Currently On-Site</h5>
                </div>
                <div class="card-body">
                    <div class="visitors-list">
                        @foreach([
                            ['name' => 'Global Player', 'company' => 'Jamies Center', 'time' => '9:45 AM', 'location' => 'More Lobby'],
                            ['name' => 'Albert Smith', 'company' => 'Financial Baker', 'time' => '9:25 AM', 'location' => 'Water Emmover'],
                            ['name' => 'Fiona Smith', 'company' => 'Hank Whalen', 'time' => '9:28 AM', 'location' => 'Lane Schoner'],
                            ['name' => 'Daniel Lee', 'company' => '', 'time' => '9:28 AM', 'location' => 'More Lobby'],
                            ['name' => 'Markia Carola', 'company' => '10:30 AM with Robert Brown', 'time' => '10:30 AM', 'location' => 'Cardiac WV'],
                            ['name' => 'Emmy Rush', 'company' => 'guest', 'time' => '9:45 AM', 'location' => 'Ease Emmover']
                        ] as $visitor)
                        <div class="visitor-item">
                            <div class="visitor-avatar">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="visitor-details">
                                <div class="visitor-name">{{ $visitor['name'] }}</div>
                                <div class="visitor-company">{{ $visitor['company'] }}</div>
                            </div>
                            <div class="visitor-meta">
                                <div class="visitor-time">{{ $visitor['time'] }}</div>
                                <div class="visitor-location">{{ $visitor['location'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Visitor Traffic by Hour -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Visitor Traffic by Hour</h5>
                </div>
                <div class="card-body">
                    <div class="traffic-chart-placeholder">
                        <div class="chart-bars">
                            @foreach([30, 45, 60, 75, 80, 90, 85, 70, 65, 55, 45, 35] as $height)
                                <div class="chart-bar" style="height: {{ $height }}%"></div>
                            @endforeach
                        </div>
                        <div class="chart-labels">
                            @foreach(['8AM', '9AM', '10AM', '11AM', '12PM', '1PM', '2PM', '3PM', '4PM', '5PM', '6PM', '7PM'] as $label)
                                <span>{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Upcoming Appointments -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Upcoming Appointments</h5>
                </div>
                <div class="card-body">
                    <div class="appointments-list">
                        @foreach([
                            ['name' => 'John Due', 'time' => '10:00 AM', 'description' => 'Anti-Him-Based Audience'],
                            ['name' => 'Alan Smith', 'time' => '10:15 AM', 'description' => 'Anti-Him-based Whalen'],
                            ['name' => 'Markia Carola', 'time' => '10:30 AM', 'description' => 'with Robert Brown']
                        ] as $appointment)
                        <div class="appointment-item">
                            <div class="appointment-time">{{ $appointment['time'] }}</div>
                            <div class="appointment-details">
                                <div class="appointment-name">{{ $appointment['name'] }}</div>
                                <div class="appointment-desc">{{ $appointment['description'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Alerts</h5>
                </div>
                <div class="card-body">
                    <div class="alerts-list">
                        @foreach([
                            ['type' => 'Deleted Entry', 'description' => 'The latest online and online portal release in [time app]', 'icon' => 'bx-trash', 'color' => 'warning'],
                            ['type' => 'Visitor Operating', 'description' => 'Validating the new appended reported client name (date app)', 'icon' => 'bx-user-voice', 'color' => 'info'],
                            ['type' => 'Emergency Alert', 'description' => 'Promoting the new user\'s call', 'icon' => 'bx-alarm', 'color' => 'danger']
                        ] as $alert)
                        <div class="alert-item">
                            <div class="alert-icon {{ $alert['color'] }}">
                                <i class='bx {{ $alert['icon'] }}'></i>
                            </div>
                            <div class="alert-details">
                                <div class="alert-type">{{ $alert['type'] }}</div>
                                <div class="alert-desc">{{ $alert['description'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

