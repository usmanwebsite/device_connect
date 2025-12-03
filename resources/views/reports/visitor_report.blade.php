@extends('layout.main_layout')

@section('content')
<div class="visitor-report-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-12">
                <h1 class="display-5 fw-bold text-dark">Visitor Report</h1>
                <p class="lead mb-0 text-muted">Comprehensive visitor management and tracking system</p>
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

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-white stats-card-total mb-3 stats-card">
                        <div class="card-body">
                            <h5 class="card-title">Total Visitors</h5>
                            <h2 class="card-text">{{ count($visitors) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white stats-card-completed mb-3 stats-card">
                        <div class="card-body">
                            <h5 class="card-title">Completed</h5>
                            <h2 class="card-text">{{ collect($visitors)->where('status', 'Completed')->count() }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white stats-card-active mb-3 stats-card">
                        <div class="card-body">
                            <h5 class="card-title">Active</h5>
                            <h2 class="card-text">{{ collect($visitors)->where('status', 'Active')->count() }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white stats-card-today mb-3 stats-card">
                        <div class="card-body">
                            <h5 class="card-title">Today's Visitors</h5>
                            <h2 class="card-text">{{ collect($visitors)->where('date_of_visit', date('Y-m-d'))->count() }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <!-- Table Header with Buttons -->
            <div class="table-header-buttons">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0 text-dark">Visitor Records</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-show-hide me-2" id="columnToggleBtn">
                            👁️ Show/Hide Columns
                        </button>
                        <a href="{{ route('visitor.report.export', ['type' => 'excel']) }}" class="btn btn-export">
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
                        @foreach($visitors as $visitor)
                        <tr>
                            <td>{{ $visitor['no'] }}</td>
                            <td>{{ $visitor['ic_passport'] }}</td>
                            <td><strong>{{ $visitor['name'] }}</strong></td>
                            <td>{{ $visitor['contact_no'] }}</td>
                            <td>{{ $visitor['company_name'] }}</td>
                            <td>{{ $visitor['date_of_visit'] }}</td>
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Custom Column Visibility Modal -->
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
                <div class="row" id="columnCheckboxes">
                    <!-- Column checkboxes will be generated here by JavaScript -->
                </div>
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
<!-- DataTables & Buttons -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/1.5.5/js/dataTables.colReorder.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable with SIMPLE configuration
        var table = $('#visitorTable').DataTable({
            dom: 'Blfrtip', // Removed print button from dom
            buttons: [
                {
                    extend: 'excel',
                    text: '📊 Export',
                    className: 'btn-export'
                }
                // Print button removed
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

        // Simple modal open function
        $('#columnToggleBtn').on('click', function() {
            generateColumnCheckboxes();
            $('#columnVisibilityModal').modal('show');
        });

        // Function to generate column checkboxes
        function generateColumnCheckboxes() {
            var checkboxesHtml = '';
            table.columns().every(function(index) {
                var column = this;
                var header = $(column.header());
                var columnName = header.data('column') || header.text();
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

        // Apply column visibility changes
        $('#applyColumnVisibility').on('click', function() {
            $('.column-checkbox-input').each(function() {
                var columnIndex = $(this).data('column-index');
                var isVisible = $(this).is(':checked');
                table.column(columnIndex).visible(isVisible);
            });
            $('#columnVisibilityModal').modal('hide');
            table.draw();
        });

        // Select all / deselect all functionality
        $(document).on('change', '#selectAllColumns', function() {
            var isChecked = $(this).is(':checked');
            $('.column-checkbox-input').prop('checked', isChecked);
        });

        // Generate checkboxes when modal is shown
        $('#columnVisibilityModal').on('show.bs.modal', function () {
            generateColumnCheckboxes();
        });
    });
</script>
@endsection

