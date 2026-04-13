@extends('layout.main_layout')

@section('content')

<style>
    .filter-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .filter-title {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .filter-group {
        margin-bottom: 15px;
    }
    
    .filter-group label {
        font-size: 13px;
        font-weight: 500;
        color: #666;
        margin-bottom: 5px;
        display: block;
    }
    
    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .filter-group input:focus,
    .filter-group select:focus {
        border-color: #4A90E2;
        outline: none;
        box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn-filter {
        background: #4A90E2;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .btn-filter:hover {
        background: #357ABD;
    }
    
    .btn-reset {
        background: #6c757d;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .btn-reset:hover {
        background: #5a6268;
    }
    
    .stats-card {
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
    }
    
    .stats-card-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .stats-card-completed { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .stats-card-active { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .stats-card-today { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    
    .status-active { background: #28a745; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .status-completed { background: #6c757d; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    .status-scheduled { background: #ffc107; color: #333; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    
    /* Clickable date style */
    .clickable-date {
        cursor: pointer;
        color: #4A90E2;
        text-decoration: underline;
        text-decoration-style: dotted;
    }
    
    .clickable-date:hover {
        color: #357ABD;
    }
    
    /* Modal styles */
    .datetime-modal .modal-content {
        border-radius: 12px;
    }
    
    .datetime-detail {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    
    .datetime-label {
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .datetime-value {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    
    .datetime-icon {
        font-size: 24px;
        margin-right: 10px;
    }

        /* Flatpickr customization */
    .flatpickr-calendar {
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        font-family: inherit;
    }
    
    .flatpickr-day.selected {
        background: #4A90E2;
        border-color: #4A90E2;
    }
    
    .flatpickr-day.today {
        border-color: #4A90E2;
    }
    
    .flatpickr-time input:hover,
    .flatpickr-time input:focus {
        background: #f0f0f0;
    }
    
    .datetime-input-wrapper {
        position: relative;
    }
    
    .datetime-input-wrapper .flatpickr-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s;
        background-color: white;
    }
    
    .datetime-input-wrapper .flatpickr-input:focus {
        border-color: #4A90E2;
        outline: none;
        box-shadow: 0 0 0 2px rgba(74,144,226,0.1);
    }
    
    .datetime-clear-btn {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #999;
        font-size: 14px;
    }
    
    .datetime-clear-btn:hover {
        color: #333;
    }
</style>

<div class="visitor-report-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-12">
                <h1 class="display-5 fw-bold text-dark" style="font-size: 22px !important">Visitor Report</h1>
                <p class="lead mb-0 text-muted" style="font-size: 16px !important">Comprehensive visitor management and tracking system</p>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-white stats-card-total mb-3 stats-card" onclick="filterByStatus('all')">
                        <div class="card-body">
                            <h5 class="card-title">Total Visitors</h5>
                            <h2 class="card-text">{{ count($visitors) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white stats-card-completed mb-3 stats-card" onclick="filterByStatus('Completed')">
                        <div class="card-body">
                            <h5 class="card-title">Completed</h5>
                            <h2 class="card-text">{{ collect($visitors)->where('status', 'Completed')->count() }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white stats-card-active mb-3 stats-card" onclick="filterByStatus('Active')">
                        <div class="card-body">
                            <h5 class="card-title">Active</h5>
                            <h2 class="card-text">{{ collect($visitors)->where('status', 'Active')->count() }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white stats-card-today mb-3 stats-card" onclick="filterByToday()">
                        <div class="card-body">
                            <h5 class="card-title">Today's Visitors</h5>
                            <h2 class="card-text">{{ collect($visitors)->where('date_of_visit', date('Y-m-d'))->count() }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Filter Section - Below Cards -->
<div class="filter-card">
    <div class="filter-title">
        🔍 Filter Visitors
    </div>
    
    <form method="GET" action="{{ route('visitor.report') }}" id="filterForm">
        <div class="row">
            <div class="col-md-3">
                <div class="filter-group">
                    <label>IC Number / Passport</label>
                    <input type="text" name="ic_passport" class="form-control" 
                           placeholder="Search by IC/Passport..." 
                           value="{{ request('ic_passport') }}">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="filter-group">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" 
                           placeholder="Search by name..." 
                           value="{{ request('name') }}">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="filter-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_no" class="form-control" 
                           placeholder="Search by contact..." 
                           value="{{ request('contact_no') }}">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="filter-group">
                    <label>Purpose of Visit</label>
                    <select name="purpose" class="form-control">
                        <option value="">All Purposes</option>
                        @foreach($filterData['purposes'] as $purpose)
                            <option value="{{ $purpose }}" {{ request('purpose') == $purpose ? 'selected' : '' }}>
                                {{ $purpose }}
                            </option>
                        @endforeach
                    </select>
                    {{-- <small class="text-muted">Total {{ count($filterData['purposes']) }} unique purposes</small> --}}
                </div>
            </div>


            
            <!-- Company Name Filter - Commented for client confirmation -->
            {{-- 
            <div class="col-md-3">
                <div class="filter-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" class="form-control" 
                           placeholder="Search by company..." 
                           value="{{ request('company_name') }}">
                </div>
            </div>
            --}}
            
            <!-- Status Filter - Commented for client confirmation -->             
            <div class="col-md-3">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="form-control" id="statusFilter">
                        <option value="">All Status</option>
                        @foreach($filterData['statuses'] as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            
                <div class="col-md-3">
                    <div class="filter-group">
                        <label>Date & Time From</label>
                        <div class="datetime-input-wrapper">
                            <input type="text" name="datetime_from" id="datetime_from" class="flatpickr-input" 
                                placeholder="Select date & time"
                                value="{{ request('datetime_from') ? \Carbon\Carbon::parse(request('datetime_from'))->format('Y-m-d H:i:s') : '' }}">
                        </div>
                        <small class="text-muted">Format: YYYY-MM-DD HH:MM:SS</small>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="filter-group">
                        <label>Date & Time To</label>
                        <div class="datetime-input-wrapper">
                            <input type="text" name="datetime_to" id="datetime_to" class="flatpickr-input" 
                                placeholder="Select date & time"
                                value="{{ request('datetime_to') ? \Carbon\Carbon::parse(request('datetime_to'))->format('Y-m-d H:i:s') : '' }}">
                        </div>
                        <small class="text-muted">Format: YYYY-MM-DD HH:MM:SS</small>
                    </div>
                </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn-filter">
                🔍 Apply Filters
            </button>
            <button type="button" class="btn-reset" onclick="resetFilters()">
                🔄 Reset Filters
            </button>
        </div>
    </form>
</div>

<!-- Results Summary - Updated to show datetime filters -->
@php
    $hasFilters = request()->anyFilled(['ic_passport', 'name', 'contact_no', 'purpose', 'datetime_from', 'datetime_to']);
@endphp

@if($hasFilters)
<div class="alert alert-info">
    <strong>Showing filtered results:</strong> Found {{ count($visitors) }} visitor(s)
    @if(request('datetime_from'))
        <span class="badge bg-info">From: {{ \Carbon\Carbon::parse(request('datetime_from'))->format('d-M-Y h:i A') }}</span>
    @endif
    @if(request('datetime_to'))
        <span class="badge bg-info">To: {{ \Carbon\Carbon::parse(request('datetime_to'))->format('d-M-Y h:i A') }}</span>
    @endif
    <button type="button" class="btn-close float-end" onclick="resetFilters()"></button>
</div>
@endif


    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-header-buttons">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0 text-dark" style="font-weight: 700">Visitor Records</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-show-hide me-2" id="columnToggleBtn">
                            👁️ Show/Hide Columns
                        </button>
                        <a href="{{ route('visitor.report.export', ['type' => 'excel'] + request()->all()) }}" class="btn btn-export">
                            📊 Export
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="datatable-scroll-container">
                <table id="visitorTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th data-column="no">No</th>
                            <th data-column="ic_passport">Ic No / Passport</th>
                            <th data-column="name">Name</th>
                            <th data-column="contact_no">Contact No</th>
                            <th data-column="company_name">Company Name</th>
                            <th data-column="date_of_visit">Date of Visit</th>
                            <th data-column="time_in">Time In</th>
                            <th data-column="time_out">Time Out</th>
                            <th data-column="purpose">Purpose of Visit</th>
                            <th data-column="host_name">Host Name</th>
                            <th data-column="current_location">Current Location</th>
                            <th data-column="location_accessed">Location Accessed</th>
                            <th data-column="duration">Duration</th>
                            <th data-column="status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visitors as $index => $visitor)
                        <tr>
                            <td>{{ $visitor['no'] }}</td>
                            <td>{{ $visitor['ic_passport'] }}</td>
                            <td><strong>{{ $visitor['name'] }}</strong></td>
                            <td>{{ $visitor['contact_no'] }}</td>
                            <td>{{ $visitor['company_name'] }}</td>
                            <td class="clickable-date" onclick="showDateTimeModal({{ $index }})">
                                <i class="fas fa-calendar-alt"></i> {{ $visitor['date_of_visit'] }}
                            </td>
                            <td>{{ $visitor['time_in'] }}</td>
                            <td>{{ $visitor['time_out'] ?? 'N/A' }}</td>
                            <td>{{ $visitor['purpose'] }}</td>
                            <td>{{ $visitor['host_name'] }}</td>
                            <td>{{ $visitor['current_location'] }}</td>
                            <td>{{ $visitor['location_accessed'] }}</td>
                            <td>{{ $visitor['duration'] }}</td>
                            <td>
                                <span class="status-{{ strtolower($visitor['status']) }}">
                                    {{ $visitor['status'] }}
                                </span>
                            </td>
                        </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center">No visitors found matching the filters</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- DateTime Modal -->
<div class="modal fade datetime-modal" id="datetimeModal" tabindex="-1" aria-labelledby="datetimeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="datetimeModalLabel">
                    <i class="fas fa-clock"></i> Visit Date & Time Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="datetimeModalBody">
                <!-- Dynamic content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Column Visibility Modal -->
<div class="modal fade" id="columnVisibilityModal" tabindex="-1" aria-labelledby="columnVisibilityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="columnVisibilityModalLabel">Show/Hide Columns</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllColumns">
                        <label class="form-check-label fw-bold" for="selectAllColumns">
                            Select All Columns
                        </label>
                    </div>
                </div>
                <div class="row" id="columnCheckboxes"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyColumnVisibility">Apply Changes</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Pass visitor data to JavaScript
    const visitorsData = @json($visitors);


        const datetimeFromPicker = flatpickr("#datetime_from", {
        enableTime: true,
        dateFormat: "Y-m-d H:i:S",
        time_24hr: true,
        allowInput: true,
        minuteIncrement: 1,
        placeholder: "Select date & time",
        onChange: function(selectedDates, dateStr, instance) {
            // Optional: Set min date for datetime_to
            if (datetimeToPicker) {
                datetimeToPicker.set('minDate', selectedDates[0]);
            }
        }
    });
    
    // Initialize Flatpickr for datetime_to
    const datetimeToPicker = flatpickr("#datetime_to", {
        enableTime: true,
        dateFormat: "Y-m-d H:i:S",
        time_24hr: true,
        allowInput: true,
        minuteIncrement: 1,
        placeholder: "Select date & time",
        onChange: function(selectedDates, dateStr, instance) {
            // Optional: Set max date for datetime_from
            if (datetimeFromPicker) {
                datetimeFromPicker.set('maxDate', selectedDates[0]);
            }
        }
    });

       function setQuickDate(range) {
        let from = new Date();
        let to = new Date();
        let fromStr = '', toStr = '';
        
        switch(range) {
            case 'today':
                from.setHours(0, 0, 0);
                to.setHours(23, 59, 59);
                fromStr = formatDateTimeForFlatpickr(from);
                toStr = formatDateTimeForFlatpickr(to);
                break;
            case 'yesterday':
                from.setDate(from.getDate() - 1);
                to.setDate(to.getDate() - 1);
                from.setHours(0, 0, 0);
                to.setHours(23, 59, 59);
                fromStr = formatDateTimeForFlatpickr(from);
                toStr = formatDateTimeForFlatpickr(to);
                break;
            case 'this_week':
                const startOfWeek = new Date(from);
                startOfWeek.setDate(from.getDate() - from.getDay());
                startOfWeek.setHours(0, 0, 0);
                fromStr = formatDateTimeForFlatpickr(startOfWeek);
                to.setHours(23, 59, 59);
                toStr = formatDateTimeForFlatpickr(to);
                break;
            case 'this_month':
                const startOfMonth = new Date(from.getFullYear(), from.getMonth(), 1);
                startOfMonth.setHours(0, 0, 0);
                fromStr = formatDateTimeForFlatpickr(startOfMonth);
                to.setHours(23, 59, 59);
                toStr = formatDateTimeForFlatpickr(to);
                break;
        }
        
        if (fromStr) {
            datetimeFromPicker.setDate(fromStr);
            document.querySelector('input[name="datetime_from"]').value = fromStr;
        }
        if (toStr) {
            datetimeToPicker.setDate(toStr);
            document.querySelector('input[name="datetime_to"]').value = toStr;
        }
        
        // Auto submit form after a short delay
        setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 100);
    }


        function formatDateTimeForFlatpickr(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }
    
    function clearDateTimes() {
        datetimeFromPicker.clear();
        datetimeToPicker.clear();
        document.querySelector('input[name="datetime_from"]').value = '';
        document.querySelector('input[name="datetime_to"]').value = '';
        
        // Auto submit form after a short delay
        setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 100);
    }

    
    function showDateTimeModal(index) {
        const visitor = visitorsData[index];
        if (!visitor) return;
        
        const modalBody = document.getElementById('datetimeModalBody');
        
        // Create HTML for modal
        modalBody.innerHTML = `
            <div class="datetime-detail">
                <div class="d-flex align-items-center">
                    <div class="datetime-icon">📅</div>
                    <div>
                        <div class="datetime-label">Visit Date</div>
                        <div class="datetime-value">${visitor.date_of_visit || 'N/A'}</div>
                    </div>
                </div>
            </div>
            
            <div class="datetime-detail">
                <div class="d-flex align-items-center">
                    <div class="datetime-icon">⏰</div>
                    <div>
                        <div class="datetime-label">Time In</div>
                        <div class="datetime-value">${visitor.time_in || 'N/A'}</div>
                    </div>
                </div>
            </div>
            
            <div class="datetime-detail">
                <div class="d-flex align-items-center">
                    <div class="datetime-icon">🚪</div>
                    <div>
                        <div class="datetime-label">Time Out</div>
                        <div class="datetime-value">${visitor.time_out || 'N/A'}</div>
                    </div>
                </div>
            </div>
            
            <div class="datetime-detail">
                <div class="d-flex align-items-center">
                    <div class="datetime-icon">⏱️</div>
                    <div>
                        <div class="datetime-label">Total Duration</div>
                        <div class="datetime-value">${visitor.duration || 'N/A'}</div>
                    </div>
                </div>
            </div>
            
            <div class="datetime-detail">
                <div class="d-flex align-items-center">
                    <div class="datetime-icon">👤</div>
                    <div>
                        <div class="datetime-label">Visitor Name</div>
                        <div class="datetime-value">${visitor.name || 'N/A'}</div>
                    </div>
                </div>
            </div>
        `;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('datetimeModal'));
        modal.show();
    }
    
    $(document).ready(function() {
        var table = $('#visitorTable').DataTable({
            dom: 'Blfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '📊 Export',
                    className: 'btn-export'
                }
            ],
            pageLength: 10,
            responsive: false,
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: true,
            language: {
                search: "Search visitors:",
                lengthMenu: "Show _MENU_ entries per page",
                info: "Showing _START_ to _END_ of _TOTAL_ visitors",
                infoEmpty: "No visitors available",
                infoFiltered: "(filtered from _MAX_ total visitors)"
            }
        });

        $('#columnToggleBtn').on('click', function() {
            generateColumnCheckboxes(table);
            $('#columnVisibilityModal').modal('show');
        });

        function generateColumnCheckboxes(table) {
            var checkboxesHtml = '';
            table.columns().every(function(index) {
                var column = this;
                var header = $(column.header());
                var columnName = (header.data('column') || header.text()).toString().toUpperCase();
                var isVisible = column.visible();
                
                checkboxesHtml += `
                    <div class="col-md-4">
                        <div class="column-checkbox">
                            <div class="form-check">
                                <input class="form-check-input column-checkbox-input" type="checkbox" 
                                       data-column-index="${index}" 
                                       id="column-${index}" 
                                       ${isVisible ? 'checked' : ''}>
                                <label class="form-check-label" for="column-${index}">
                                    ${columnName}
                                </label>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#columnCheckboxes').html(checkboxesHtml);
        }

        $('#applyColumnVisibility').on('click', function() {
            $('.column-checkbox-input').each(function() {
                var columnIndex = $(this).data('column-index');
                var isVisible = $(this).is(':checked');
                table.column(columnIndex).visible(isVisible);
            });
            $('#columnVisibilityModal').modal('hide');
            table.draw();
        });

        $(document).on('change', '#selectAllColumns', function() {
            var isChecked = $(this).is(':checked');
            $('.column-checkbox-input').prop('checked', isChecked);
        });

        $('#columnVisibilityModal').on('show.bs.modal', function () {
            generateColumnCheckboxes(table);
        });
    });

    function resetFilters() {
        window.location.href = "{{ route('visitor.report') }}";
    }
    
    function filterByStatus(status) {
        if (status === 'all') {
            resetFilters();
        } else {
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('status', status);
            window.location.href = currentUrl.toString();
        }
    }
    
    function filterByToday() {
        var today = new Date().toISOString().split('T')[0];
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('date_from', today);
        currentUrl.searchParams.set('date_to', today);
        window.location.href = currentUrl.toString();
    }
</script>
@endsection


