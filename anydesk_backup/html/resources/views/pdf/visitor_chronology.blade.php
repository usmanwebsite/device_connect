<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Visitor Chronology Report</title>
    <style>
        /* Base styles */
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
        }
        
        .header .subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Visitor Info */
        .visitor-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .visitor-info h3 {
            color: #3498db;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 150px;
            color: #2c3e50;
        }
        
        .info-value {
            color: #34495e;
        }
        
        /* Summary Cards */
        .summary-section {
            margin-bottom: 25px;
        }
        
        .summary-section h3 {
            color: #3498db;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
        }
        
        .summary-card .card-title {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-card .card-value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* Dates Section */
        .dates-section {
            margin-bottom: 25px;
        }
        
        .dates-section h3 {
            color: #3498db;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .date-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .date-badge {
            background-color: #e8f4fc;
            border: 1px solid #3498db;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 11px;
            color: #2980b9;
        }
        
        /* Timeline Section */
        .timeline-section {
            margin-bottom: 25px;
        }
        
        .timeline-section h3 {
            color: #3498db;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #3498db;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ddd;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #3498db;
            border: 2px solid white;
        }
        
        .timeline-header {
            margin-bottom: 10px;
        }
        
        .timeline-time {
            font-size: 11px;
            color: #7f8c8d;
            background-color: #f8f9fa;
            padding: 2px 8px;
            border-radius: 3px;
            display: inline-block;
        }

        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 11px;
        }

        .timeline-table td {
            padding: 4px 8px;
            vertical-align: top;
        }

        .timeline-label {
            font-weight: bold;
            color: #2c3e50;
        }

        .timeline-value {
            color: #34495e;
        }
        
        .timeline-body {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }
        
        .timeline-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .timeline-label {
            font-weight: bold;
            color: #2c3e50;
            font-size: 11px;
        }
        
        .timeline-value {
            color: #34495e;
            font-size: 11px;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        /* Utility classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-success { color: #27ae60; }
        .text-warning { color: #f39c12; }
        .text-danger { color: #e74c3c; }
        .text-info { color: #3498db; }
        .mb-3 { margin-bottom: 15px; }
        .mt-3 { margin-top: 15px; }
        
        /* Page break */
        .page-break {
            page-break-before: always;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active { background-color: #d5f4e6; color: #27ae60; }
        .status-inactive { background-color: #f8d7da; color: #e74c3c; }
        .status-pending { background-color: #fff3cd; color: #f39c12; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Visitor Chronology Report</h1>
        <div class="subtitle">
            Generated on: {{ $generatedAt }}
            @if($downloadType === 'date')
                | Showing data for: <strong>{{ $selectedDate }}</strong>
            @endif
        </div>
    </div>
    
    <!-- Visitor Information -->
    <div class="visitor-info">
        <h3>Visitor Information</h3>
        <div class="info-row">
            <div class="info-label">Full Name:</div>
            <div class="info-value">{{ $visitor['fullName'] ?? 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">IC Number:</div>
            <div class="info-value">{{ $visitor['icNo'] ?? 'N/A' }}</div>
        </div>
        {{-- <div class="info-row">
            <div class="info-label">Staff Number:</div>
            <div class="info-value">{{ $visitor['staffNo'] ?? 'N/A' }}</div>
        </div> --}}
    </div>
    
    <!-- Summary Section -->
    <div class="summary-section">
        <h3>Summary</h3>
        <div class="summary-cards">
            <div class="summary-card">
                <div class="card-title">Current Status</div>
                <div class="card-value">
                    @if(isset($chronology['current_status']['status']))
                        @if($chronology['current_status']['status'] === 'in_building')
                            <span class="status-badge status-active">IN BUILDING</span>
                        @elseif($chronology['current_status']['status'] === 'out_of_building')
                            <span class="status-badge status-inactive">OUT OF BUILDING</span>
                        @else
                            <span class="status-badge status-pending">{{ strtoupper($chronology['current_status']['status']) }}</span>
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </div>
            
            <div class="summary-card">
                <div class="card-title">Total Time Spent</div>
                <div class="card-value">
                    {{ $chronology['total_time_spent']['formatted'] ?? '0h 0m 0s' }}
                </div>
            </div>
            
            <div class="summary-card">
                <div class="card-title">Total Visits</div>
                <div class="card-value">
                    {{ count($chronology['dates'] ?? []) }}
                </div>
            </div>
            
            <div class="summary-card">
                <div class="card-title">Total Scans</div>
                <div class="card-value">
                    {{ $chronology['summary']['total_visits'] ?? 0 }}
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dates Section -->
    @if(isset($chronology['dates']) && count($chronology['dates']) > 0)
    <div class="dates-section">
        <h3>Visit Dates</h3>
        <div class="date-list">
            @foreach($chronology['dates'] as $date)
            <div class="date-badge">{{ $date }}</div>
            @endforeach
        </div>
    </div>
    @endif
    
    <!-- Timeline Section -->
    @if($downloadType === 'full' && isset($chronology['all_location_timeline']) && count($chronology['all_location_timeline']) > 0)
    <div class="timeline-section">
        <h3>Complete Movement Timeline</h3>
        <div class="timeline">
            @foreach($chronology['all_location_timeline'] as $index => $item)
            <div class="timeline-item">
                <div class="timeline-header">
                    <span class="timeline-time">
                        Movement {{ $index + 1 }} | 
                        {{ \Carbon\Carbon::parse($item['entry_time'])->format('d-M-Y H:i:s') }}
                    </span>
                </div>
<div class="timeline-body">
    <table class="timeline-table">
        <tr>
            <td class="timeline-label">From Location:</td>
            <td class="timeline-value">{{ $item['from_location'] ?? 'Unknown' }}</td>

            <td class="timeline-label">To Location:</td>
            <td class="timeline-value">{{ $item['to_location'] ?? 'Unknown' }}</td>
        </tr>
        <tr>
            <td class="timeline-label">Time Spent:</td>
            <td class="timeline-value">
                @if(isset($item['time_spent']))
                    {{ $item['time_spent']['hours'] ?? 0 }}h 
                    {{ $item['time_spent']['minutes'] ?? 0 }}m 
                    {{ $item['time_spent']['seconds'] ?? 0 }}s
                @else
                    N/A
                @endif
            </td>

            <td class="timeline-label">Status:</td>
            <td class="timeline-value">
                @if(isset($item['access_granted']) && $item['access_granted'] == 1)
                    <span class="status-badge status-active">GRANTED</span>
                @else
                    <span class="status-badge status-inactive">DENIED</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="timeline-label">Entry Time:</td>
            <td class="timeline-value">{{ \Carbon\Carbon::parse($item['entry_time'])->format('H:i:s') }}</td>

            <td class="timeline-label">Exit Time:</td>
            <td class="timeline-value">{{ \Carbon\Carbon::parse($item['exit_time'])->format('H:i:s') }}</td>
        </tr>
    </table>
</div>

            </div>
            @endforeach
        </div>
    </div>
    @endif
    
    <!-- Date-Specific Timeline -->
    @if($downloadType === 'date' && isset($selectedDate) && isset($chronology['timeline_by_date'][$selectedDate]))
    <div class="timeline-section">
        <h3>Timeline for {{ $selectedDate }}</h3>
        <div class="timeline">
            @foreach($chronology['timeline_by_date'][$selectedDate] as $index => $item)
            <div class="timeline-item">
                <div class="timeline-header">
                    <span class="timeline-time">
                        Movement {{ $index + 1 }} | 
                        {{ \Carbon\Carbon::parse($item['entry_time'])->format('H:i:s') }}
                    </span>
                </div>
                <div class="timeline-body">
                    <div class="timeline-row">
                        <div>
                            <div class="timeline-label">From Location:</div>
                            <div class="timeline-value">{{ $item['from_location'] ?? 'Unknown' }}</div>
                        </div>
                        <div>
                            <div class="timeline-label">To Location:</div>
                            <div class="timeline-value">{{ $item['to_location'] ?? 'Unknown' }}</div>
                        </div>
                        <div>
                            <div class="timeline-label">Time Spent:</div>
                            <div class="timeline-value">
                                @if(isset($item['time_spent']))
                                    {{ $item['time_spent']['hours'] ?? 0 }}h 
                                    {{ $item['time_spent']['minutes'] ?? 0 }}m 
                                    {{ $item['time_spent']['seconds'] ?? 0 }}s
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="timeline-label">Status:</div>
                            <div class="timeline-value">
                                @if(isset($item['access_granted']) && $item['access_granted'] == 1)
                                    <span class="status-badge status-active">GRANTED</span>
                                @else
                                    <span class="status-badge status-inactive">DENIED</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="timeline-row">
                        <div>
                            <div class="timeline-label">Entry Time:</div>
                            <div class="timeline-value">
                                {{ \Carbon\Carbon::parse($item['entry_time'])->format('H:i:s') }}
                            </div>
                        </div>
                        <div>
                            <div class="timeline-label">Exit Time:</div>
                            <div class="timeline-value">
                                {{ \Carbon\Carbon::parse($item['exit_time'])->format('H:i:s') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
    
    <!-- Footer -->
    <div class="footer">
        <p>&copy; {{ date('Y') }} Visitor Management System | Page 1 of 1</p>
    </div>
</body>
</html>

