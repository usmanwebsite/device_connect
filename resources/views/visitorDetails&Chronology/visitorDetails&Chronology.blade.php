@extends('layout.main_layout')

@section('title', 'Visitor Details & Chronology')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Visitor Details</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Visitor Details</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-search mr-1"></i> 
                            Search Visitor
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="searchType">Search By</label>
                                    <select id="searchType" class="form-control form-control-lg">
                                        <option value="">SELECT TYPE</option>
                                        <option value="staffNo">Staff Number</option>
                                        <option value="icNo">IC Number</option>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Select how to search
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label for="searchInput">Search Term</label>
                                    <div class="input-group">
                                        <input type="text" id="searchInput" class="form-control form-control-lg" 
                                               placeholder="Enter Staff No or IC Number" 
                                               aria-label="Search Visitor">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary btn-lg" type="button" id="searchBtn">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                            <button class="btn btn-secondary btn-lg" type="button" id="clearBtn">
                                                <i class="fas fa-times"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        <span id="searchHint">Enter staff number or IC number</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loading Indicator -->
                        <div class="row mt-3" id="loadingSection" style="display: none;">
                            <div class="col-md-12 text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Searching visitor details...</p>
                            </div>
                        </div>
                        
                        <!-- Error Alert -->
                        <div class="alert alert-danger mt-3" id="errorAlert" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <span id="errorMessage"></span>
                        </div>
                        
                        <!-- Success Alert -->
                        <div class="alert alert-success mt-3" id="successAlert" style="display: none;">
                            <i class="fas fa-check-circle"></i> 
                            <span id="successMessage"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visitor Details DataTable -->
        <div class="row mt-3" id="visitorTableSection" style="display: none;">
            <div class="col-md-12">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users mr-1"></i> 
                            Visitor Details
                        </h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                            <button type="button" class="btn btn-tool" id="refreshTable">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-tool" id="exportBtn" title="Export Data" style="display: none;">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped" id="visitorTable" width="100%">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Staff No</th>
                                        <th>Full Name</th>
                                        <th>IC No</th>
                                        <th>Company</th>
                                        <th>Contact No</th>
                                        <th>Visit From</th>
                                        <th>Visit To</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Data Found Section -->
        <div class="row mt-3" id="noDataSection" style="display: none;">
            <div class="col-md-12">
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-search fa-sm mr-1"></i> 
                            No Data Found
                        </h3>
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="fas fa-search fa-4x mb-3 text-muted"></i>
                        <h4>No visitor details found</h4>
                        <p class="text-muted">Try searching with a different staff number or IC number</p>
                        <button class="btn btn-primary" id="tryDifferentSearch">
                            <i class="fas fa-redo mr-1"></i> Try Different Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" role="dialog" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="viewDetailsModalLabel">
                    <i class="fas fa-user-circle mr-1"></i> 
                    Visitor Details
                </h5>
                <button type="button" class="close" id="closeViewDetailsModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Staff No:</label>
                            <p id="modalStaffNo">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Full Name:</label>
                            <p id="modalFullName">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">IC No:</label>
                            <p id="modalIcNo">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Sex:</label>
                            <p id="modalSex">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Contact No:</label>
                            <p id="modalContactNo">-</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Company Name:</label>
                            <p id="modalCompanyName">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Person Visited:</label>
                            <p id="modalPersonVisited">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Visit From:</label>
                            <p id="modalDateOfVisitFrom">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Visit To:</label>
                            <p id="modalDateOfVisitTo">-</p>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Visit Duration:</label>
                            <p id="modalVisitDuration">-</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="font-weight-bold">Reason for Visit:</label>
                            <p id="modalReason" class="border p-2 rounded">-</p>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Search Type:</label>
                            <p id="modalSearchType">-</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Last Updated:</label>
                            <p id="modalLastUpdated">-</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeViewDetailsModalBtn">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="printDetailsBtn">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chronology Modal -->

<div class="modal fade" id="chronologyModal" tabindex="-1" role="dialog" aria-labelledby="chronologyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="chronologyModalLabel">
                    <i class="fas fa-history mr-1"></i> 
                    Visitor Chronology & Access Logs
                </h5>
                <button type="button" class="close" id="closeChronologyModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Loading Section -->
                <div id="chronologyLoading" class="text-center py-5">
                    <div class="spinner-border text-info" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading visitor chronology...</p>
                </div>
                
                <!-- Content Section -->
                <div id="chronologyContent" style="display: none;">
                    <!-- Visitor Info -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Staff No:</strong> <span id="chronoStaffNo">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Full Name:</strong> <span id="chronoFullName">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>IC No:</strong> <span id="chronoIcNo">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Current Status</h6>
                                    <h4 id="currentStatus" class="mb-0">-</h4>
                                    <small id="currentStatusMsg"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Time Spent</h6>
                                    <h4 id="totalTimeSpent" class="mb-0">-</h4>
                                    <small>in building</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Visits</h6>
                                    <h4 id="totalVisits" class="mb-0">-</h4>
                                    <small>days visited</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Scans</h6>
                                    <h4 id="totalAccesses" class="mb-0">-</h4>
                                    <small id="accessSuccessRate"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Date Selection Section -->
                    <div class="card mb-4" id="dateSelectionSection">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-calendar-alt mr-1"></i> 
                                Select Date
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="btn-group flex-wrap" role="group" id="dateButtons">
                                        <!-- Date buttons will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Selected Date Info -->
                    <div class="row mb-3" id="selectedDateInfo" style="display: none;">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-calendar-day mr-1"></i>
                                Showing data for: <strong id="selectedDateText"></strong>
                            </div>
                        </div>
                    </div>
                    
                    {{-- 
                    <!-- Access Logs for Selected Date - COMMENTED OUT FOR CLIENT REVIEW -->
                    <div class="card mb-4" id="accessLogsSection">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-list-alt mr-1"></i> 
                                Complete Access Logs Timeline
                                <span id="logsDateIndicator" class="badge badge-light ml-2"></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Time</th>
                                            <th>Location</th>
                                            <th>Access Status</th>
                                            <th>Next Location</th>
                                            <th>Time to Next Location</th>
                                        </tr>
                                    </thead>
                                    <tbody id="accessLogsTable">
                                        <!-- Access logs will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    --}}
                    
                    <!-- Location Timeline for Selected Date -->
                    <div class="card" id="locationTimelineSection">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-route mr-1"></i> 
                                Location Movement Timeline
                                <span id="timelineDateIndicator" class="badge badge-light ml-2"></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline" id="locationTimeline">
                                <!-- Timeline items will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Error Section -->
                <div id="chronologyError" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <span id="chronologyErrorMessage"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeChronologyModalBtn">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- <div class="modal fade" id="chronologyModal" tabindex="-1" role="dialog" aria-labelledby="chronologyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="chronologyModalLabel">
                    <i class="fas fa-history mr-1"></i> 
                    Visitor Chronology & Access Logs
                </h5>
                <button type="button" class="close" id="closeChronologyModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="chronologyLoading" class="text-center py-5">
                    <div class="spinner-border text-info" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading visitor chronology...</p>
                </div>

                <div id="chronologyContent" style="display: none;">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Staff No:</strong> <span id="chronoStaffNo">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Full Name:</strong> <span id="chronoFullName">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>IC No:</strong> <span id="chronoIcNo">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Current Status</h6>
                                    <h4 id="currentStatus" class="mb-0">-</h4>
                                    <small id="currentStatusMsg"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Time Spent</h6>
                                    <h4 id="totalTimeSpent" class="mb-0">-</h4>
                                    <small>in building</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Visits</h6>
                                    <h4 id="totalVisits" class="mb-0">-</h4>
                                    <small>days visited</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Scans</h6>
                                    <h4 id="totalAccesses" class="mb-0">-</h4>
                                    <small id="accessSuccessRate"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4" id="dateSelectionSection">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-calendar-alt mr-1"></i> 
                                Select Date
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="btn-group flex-wrap" role="group" id="dateButtons">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3" id="selectedDateInfo" style="display: none;">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-calendar-day mr-1"></i>
                                Showing data for: <strong id="selectedDateText"></strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4" id="accessLogsSection">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-list-alt mr-1"></i> 
                                Complete Access Logs Timeline
                                <span id="logsDateIndicator" class="badge badge-light ml-2"></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Time</th>
                                            <th>Location</th>
                                            <th>Access Status</th>

                                            <th>Next Location</th>
                                            <th>Time to Next Location</th>
                                        </tr>
                                    </thead>
                                    <tbody id="accessLogsTable">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" id="locationTimelineSection">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-route mr-1"></i> 
                                Location Movement Timeline
                                <span id="timelineDateIndicator" class="badge badge-light ml-2"></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline" id="locationTimeline">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="chronologyError" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <span id="chronologyErrorMessage"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeChronologyModalBtn">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div> --}}
@endsection

@section('scripts')
<!-- DataTables Scripts -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.0/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    let visitorDataTable = null;
    let currentSearchTerm = '';
    let currentSearchType = 'auto';
    let currentData = null;
    
    // Initialize DataTable with empty state
    function initializeDataTable() {
        if (visitorDataTable) {
            visitorDataTable.destroy();
        }
        
        visitorDataTable = $('#visitorTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "pageLength": 10,
            "language": {
                "emptyTable": "No visitor data available",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "Showing 0 to 0 of 0 entries",
                "infoFiltered": "(filtered from _MAX_ total entries)",
                "lengthMenu": "Show _MENU_ entries",
                "loadingRecords": "Loading...",
                "processing": "Processing...",
                "search": "Search:",
                "zeroRecords": "No matching records found"
            },
            "columnDefs": [
                { "orderable": false, "targets": [0, 9] },
                { "className": "text-center", "targets": [0, 8, 9] },
                { "width": "5%", "targets": 0 },
                { "width": "15%", "targets": 9 }
            ]
        });
    }
    
    // Initialize empty DataTable
    initializeDataTable();
    
    // Update search hint based on selected type
    $('#searchType').change(function() {
        updateSearchHint();
    });
    
    function updateSearchHint() {
        const type = $('#searchType').val();
        let hint = '';
        
        switch(type) {
            case 'auto':
                hint = 'Enter staff number or IC number (system will auto-detect)';
                break;
            case 'staffNo':
                hint = 'Enter staff number (e.g., TESTUSER123)';
                break;
            case 'icNo':
                hint = 'Enter IC number (e.g., 352021234567)';
                break;
        }
        
        $('#searchHint').text(hint);
        $('#searchInput').focus();
    }
    
    // Initialize hint
    updateSearchHint();
    
    // Search button click event
    $('#searchBtn').click(function() {
        searchVisitor();
    });
    
    // Clear button click event
    $('#clearBtn').click(function() {
        clearSearch();
    });
    
    // Try different search button
    $('#tryDifferentSearch').click(function() {
        $('#searchInput').val('');
        $('#searchInput').focus();
        hideAllSections();
    });
    
    // Enter key event on search input
    $('#searchInput').keypress(function(e) {
        if (e.which === 13) {
            searchVisitor();
        }
    });
    
    // Refresh table button
    $('#refreshTable').click(function() {
        if (currentSearchTerm) {
            searchVisitor();
        }
    });
    
    // Export button
    $('#exportBtn').click(function() {
        exportToCSV();
    });
    
    // Close View Details Modal (X button)
    $('#closeViewDetailsModal').click(function() {
        $('#viewDetailsModal').modal('hide');
    });
    
    // Close View Details Modal (Close button)
    $('#closeViewDetailsModalBtn').click(function() {
        $('#viewDetailsModal').modal('hide');
    });
    
    // Close Chronology Modal (X button)
    $('#closeChronologyModal').click(function() {
        $('#chronologyModal').modal('hide');
    });
    
    // Close Chronology Modal (Close button)
    $('#closeChronologyModalBtn').click(function() {
        $('#chronologyModal').modal('hide');
    });
    
    // ESC key to close modals
    $(document).keydown(function(e) {
        if (e.keyCode === 27) { // ESC key
            if ($('#viewDetailsModal').hasClass('show')) {
                $('#viewDetailsModal').modal('hide');
            }
            if ($('#chronologyModal').hasClass('show')) {
                $('#chronologyModal').modal('hide');
            }
        }
    });
    
    // Close modal when clicking on backdrop
    $(document).on('click', '.modal', function(e) {
        if ($(e.target).hasClass('modal')) {
            $(this).modal('hide');
        }
    });
    
    function searchVisitor() {
        const searchTerm = $('#searchInput').val().trim();
        const searchType = $('#searchType').val();
        
        if (!searchTerm) {
            showError('Please enter a search term');
            return;
        }
        
        if (searchTerm.length < 3) {
            showError('Please enter at least 3 characters');
            return;
        }
        
        currentSearchTerm = searchTerm;
        currentSearchType = searchType;
        
        // Show loading, hide other sections
        showLoading();
        hideAllSections();
        
        console.log('Searching for:', searchTerm, 'Type:', searchType);
        
        $.ajax({
            url: '{{ route("visitor-details.search") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                search_term: searchTerm,
                search_type: searchType
            },
            success: function(response) {
                console.log('API Response:', response);
                hideLoading();
                
                if (response.success) {
                    console.log('Data received:', response.data);
                    currentData = response.data;
                    displayVisitorData(response.data);
                    showSuccess('Visitor details found successfully');
                    $('#exportBtn').show();
                } else {
                    showError(response.message || 'Visitor not found');
                    showNoDataSection();
                    $('#exportBtn').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                hideLoading();
                let errorMessage = 'An error occurred while searching';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.status === 0) {
                    errorMessage = 'Network error. Please check your connection.';
                } else if (xhr.status === 404) {
                    errorMessage = 'API endpoint not found. Please contact administrator.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                }
                
                showError(errorMessage);
                showNoDataSection();
                $('#exportBtn').hide();
            }
        });
    }
    
    function displayVisitorData(data) {
        // Check if data is array or single object
        const visitors = Array.isArray(data) ? data : [data];
        
        console.log('Number of visitors found:', visitors.length);
        
        // Clear existing data
        if (visitorDataTable) {
            visitorDataTable.clear().draw();
        }
        
        // Format date function
        const formatDate = (dateString) => {
            if (!dateString || dateString === 'N/A') return '-';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }) + ' ' + date.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return dateString;
            }
        };
        
        // Calculate status badge
        const getStatusBadge = (visitTo) => {
            if (!visitTo || visitTo === 'N/A') return '<span class="badge badge-secondary">Unknown</span>';
            
            const now = new Date();
            const visitEnd = new Date(visitTo);
            
            if (visitEnd > now) {
                return '<span class="badge badge-success">Active</span>';
            } else {
                return '<span class="badge badge-warning">Expired</span>';
            }
        };
        
        // Add each visitor to the table
        visitors.forEach((visitor, index) => {
            // Check if visitor has valid information
            const hasValidData = visitor && 
                                (visitor.staffNo && visitor.staffNo !== 'N/A') || 
                                (visitor.fullName && visitor.fullName !== 'N/A') || 
                                (visitor.icNo && visitor.icNo !== 'N/A');
            
            if (!hasValidData) {
                console.log('Skipping invalid visitor data:', visitor);
                return;
            }
            
            // Add data to table with both View and Chronology buttons
            const rowData = [
                index + 1,
                visitor.staffNo || '-',
                visitor.fullName || '-',
                visitor.icNo || '-',
                visitor.companyName || '-',
                visitor.contactNo || '-',
                formatDate(visitor.dateOfVisitFrom) || '-',
                formatDate(visitor.dateOfVisitTo) || '-',
                getStatusBadge(visitor.dateOfVisitTo),
                `<div class="btn-group" role="group">
                    <button class="btn btn-sm btn-info view-details" 
                            data-staffno="${visitor.staffNo || ''}"
                            data-fullname="${visitor.fullName || ''}"
                            data-icno="${visitor.icNo || ''}"
                            data-sex="${visitor.sex || ''}"
                            data-contactno="${visitor.contactNo || ''}"
                            data-company="${visitor.companyName || ''}"
                            data-personvisited="${visitor.personVisited || ''}"
                            data-visitfrom="${visitor.dateOfVisitFrom || ''}"
                            data-visitto="${visitor.dateOfVisitTo || ''}"
                            data-reason="${visitor.reason || ''}"
                            data-searchtype="${visitor.searchType || ''}">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn btn-sm btn-warning view-chronology" 
                            data-staffno="${visitor.staffNo || ''}"
                            data-fullname="${visitor.fullName || ''}"
                            data-icno="${visitor.icNo || ''}">
                        <i class="fas fa-history"></i> Chronology
                    </button>
                </div>`
            ];
            
            if (visitorDataTable) {
                visitorDataTable.row.add(rowData).draw();
            }
        });
        
        // If no valid data was added
        if (visitors.length === 0 || visitorDataTable.data().count() === 0) {
            showError('No visitor data found');
            showNoDataSection();
            return;
        }
        
        // Show table section
        $('#visitorTableSection').show();
        $('#noDataSection').hide();
        
        // Update success message with count
        showSuccess(`Found ${visitors.length} visitor(s) matching your search`);
        
        // Add click event to view buttons
        $('#visitorTable').off('click', '.view-details').on('click', '.view-details', function() {
            showVisitorDetailsModal($(this).data());
        });
        
        // Add click event to chronology buttons
        $('#visitorTable').off('click', '.view-chronology').on('click', '.view-chronology', function() {
            const staffNo = $(this).data('staffno');
            const fullName = $(this).data('fullname');
            const icNo = $(this).data('icno');
            showChronologyModal(staffNo, fullName, icNo);
        });
    }
    
    // Chronology Modal Functions
    function showChronologyModal(staffNo, fullName, icNo) {
        // Set visitor info in modal
        $('#chronoStaffNo').text(staffNo || '-');
        $('#chronoFullName').text(fullName || '-');
        $('#chronoIcNo').text(icNo || '-');
        
        // Reset modal content
        $('#chronologyLoading').show();
        $('#chronologyContent').hide();
        $('#chronologyError').hide();
        
        // Clear previous content
        $('#currentStatus').text('-');
        $('#totalTimeSpent').text('-');
        $('#locationsVisited').text('-');
        $('#totalAccesses').text('-');
        $('#turnstileEntries').html('');
        $('#turnstileExits').html('');
        $('#accessLogsTable').html('');
        $('#locationTimeline').html('');
        
        // Show modal
        $('#chronologyModal').modal('show');
        
        // Load chronology data
        loadVisitorChronology(staffNo, icNo);
    }
    
    // Function to load chronology data
    function loadVisitorChronology(staffNo, icNo) {
        $.ajax({
            url: '{{ route("visitor-details.chronology") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                staff_no: staffNo,
                ic_no: icNo
            },
            success: function(response) {
                $('#chronologyLoading').hide();
                
                if (response.success) {
                    displayChronologyData(response.data);
                    $('#chronologyContent').show();
                } else {
                    $('#chronologyErrorMessage').text(response.message || 'Error loading chronology');
                    $('#chronologyError').show();
                }
            },
            error: function(xhr, status, error) {
                $('#chronologyLoading').hide();
                let errorMessage = 'An error occurred while loading chronology';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                $('#chronologyErrorMessage').text(errorMessage);
                $('#chronologyError').show();
            }
        });
    }
    
    // Function to display chronology data
function displayChronologyData(data) {
    // 1. Update summary cards
    if (data.current_status) {
        $('#currentStatus').text(data.current_status.status.toUpperCase());
        $('#currentStatusMsg').text(data.current_status.message);
        
        // Color code based on status
        const statusCard = $('#currentStatus').closest('.card');
        statusCard.removeClass('bg-primary bg-success bg-secondary');
        
        if (data.current_status.status === 'in_building') {
            statusCard.addClass('bg-success');
        } else if (data.current_status.status === 'out_of_building') {
            statusCard.addClass('bg-secondary');
        } else {
            statusCard.addClass('bg-primary');
        }
    }
    
    if (data.total_time_spent) {
        $('#totalTimeSpent').text(data.total_time_spent.formatted || data.total_time_spent);
    }
    
    if (data.summary) {
        $('#totalVisits').text(data.dates ? data.dates.length : '0');
        $('#totalAccesses').text(data.summary.total_visits || '0');
        
        const successRate = data.summary.total_visits > 0 
            ? Math.round((data.summary.successful_accesses / data.summary.total_visits) * 100) 
            : 0;
        $('#accessSuccessRate').text(`${successRate}% successful`);
    }
    
    // 2. Display turnstile entries/exits if available
    if (data.turnstile_info) {
        displayTurnstileEntries(data.turnstile_info);
    }
    
    // 3. Display date buttons if dates available
    if (data.dates && data.dates.length > 0) {
        displayDateButtons(data.dates, data.logs_by_date, data.timeline_by_date);
    } else {
        // If no dates, hide date selection and show all data
        $('#dateSelectionSection').hide();
        $('#selectedDateInfo').hide();
        // displayAllAccessLogs(data.all_access_logs);
        displayAllLocationTimeline(data.all_location_timeline);
    }
}

function displayTurnstileEntries(turnstileInfo) {
    if (turnstileInfo.entries && turnstileInfo.entries.length > 0) {
        let entriesHtml = '';
        turnstileInfo.entries.forEach((entry, index) => {
            entriesHtml += `
                <div class="alert alert-success mb-2">
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-sign-in-alt mr-1"></i> Entry ${index + 1}</span>
                        <span class="badge badge-success">CHECK-IN</span>
                    </div>
                    <div class="mt-1">
                        <small><strong>Date:</strong> ${entry.date || formatDate(entry.time)}</small><br>
                        <small><strong>Time:</strong> ${formatDateTime(entry.time)}</small><br>
                        <small><strong>Location:</strong> ${entry.location || 'Unknown'}</small>
                    </div>
                </div>
            `;
        });
        
    }
}

    function displayDateButtons(dates, logsByDate, timelineByDate) {
        let dateButtonsHtml = '';
        
        dates.forEach((date, index) => {
            // Get date key from formatted date (assuming format: dd-MMM-yyyy)
            const dateKey = getDateKeyFromFormatted(date);
            const logsCount = logsByDate[dateKey] ? logsByDate[dateKey].length : 0;
            const timelineCount = timelineByDate[dateKey] ? timelineByDate[dateKey].length : 0;
            
            const isActive = index === 0 ? 'active' : '';
            dateButtonsHtml += `
                <button type="button" class="btn btn-outline-primary date-btn ${isActive}" 
                        data-date="${date}" data-date-key="${dateKey}">
                    ${date}
                </button>
            `;
        });
        
        $('#dateButtons').html(dateButtonsHtml);
        
        // Set first date as selected by default
        if (dates.length > 0) {
            const firstDate = dates[0];
            const firstDateKey = getDateKeyFromFormatted(firstDate);
            displayDataForDate(firstDate, firstDateKey, logsByDate, timelineByDate);
        }
        
        // Add click event to date buttons
        $('.date-btn').click(function() {
            // Remove active class from all buttons
            $('.date-btn').removeClass('active');
            // Add active class to clicked button
            $(this).addClass('active');
            
            const selectedDate = $(this).data('date');
            const selectedDateKey = $(this).data('date-key');
            displayDataForDate(selectedDate, selectedDateKey, logsByDate, timelineByDate);
        });
    }

    function displayDataForDate(date, dateKey, logsByDate, timelineByDate) {
        // Update selected date info
        $('#selectedDateText').text(date);
        $('#selectedDateInfo').show();
        
        // Update indicators
        $('#logsDateIndicator').text(date);
        $('#timelineDateIndicator').text(date);
        
        console.log("Displaying data for date:", date);
        console.log("Date key:", dateKey);
        console.log("Logs by date:", logsByDate);
        
        // Display access logs for selected date
        // const accessLogs = logsByDate[date] || [];
        // console.log("Access logs for", date, ":", accessLogs.length, "logs");
        // displayAccessLogsForDate(accessLogs);
        
        // Display timeline for selected date
        const timeline = timelineByDate[date] || [];
        console.log("Timeline for", date, ":", timeline.length, "items");
        displayTimelineForDate(timeline);
    }

    function displayAccessLogsForDate(accessLogs) {
    let logsHtml = '<tr><td colspan="7" class="text-center">No access logs found for this date</td></tr>';
    
    if (accessLogs && accessLogs.length > 0) {
        logsHtml = accessLogs.map((log, index) => {
            const accessBadge = log.access_granted == 1 
                ? '<span class="badge badge-success">GRANTED</span>' 
                : '<span class="badge badge-danger">DENIED</span>';
            
            // const ackBadge = log.acknowledge == 1 
            //     ? '<span class="badge badge-info">YES</span>' 
            //     : '<span class="badge badge-warning">NO</span>';
            
            let timeToNext = '-';
            if (log.next_time && log.created_at) {
                const timeDiff = Math.abs(new Date(log.next_time) - new Date(log.created_at));
                const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                timeToNext = `${hours}h ${minutes}m`;
            }
            
            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${formatDateTime(log.created_at)}</td>
                    <td>${log.location_name || log.location || '-'}</td>
                    <td>${accessBadge}</td>
                    
                    <td>${log.next_location || '-'}</td>
                    <td>${timeToNext}</td>
                </tr>
            `;
        }).join('');
    }
    
    $('#accessLogsTable').html(logsHtml);
}

function displayTimelineForDate(timeline) {
    let timelineHtml = '<div class="text-muted">No movement data available for this date</div>';
    
    if (timeline && timeline.length > 0) {
        timelineHtml = timeline.map((item, index) => {
            const timeSpent = item.time_spent ? 
                `${item.time_spent.hours || 0}h ${item.time_spent.minutes || 0}m ${item.time_spent.seconds || 0}s` : '-';
            
            return `
                <div class="timeline-item mb-3">
                    <div class="timeline-header d-flex justify-content-between">
                        <span class="font-weight-bold">Movement ${index + 1}</span>
                        <small class="text-muted">${formatDateTime(item.entry_time)}</small>
                    </div>
                    <div class="timeline-body p-2 border rounded bg-light">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>From:</strong><br>
                                ${item.from_location || 'Unknown'}
                            </div>
                            <div class="col-md-3">
                                <strong>To:</strong><br>
                                ${item.to_location || 'Unknown'}
                            </div>
                            <div class="col-md-3">
                                <strong>Time Spent:</strong><br>
                                ${timeSpent}
                            </div>
                            <div class="col-md-3">
                                <strong>Status:</strong><br>
                                ${item.access_granted == 1 ? '<span class="badge badge-success">GRANTED</span>' : '<span class="badge badge-danger">DENIED</span>'}
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <small><strong>Entry:</strong> ${formatDateTime(item.entry_time)}</small>
                            </div>
                            <div class="col-md-6">
                                <small><strong>Exit:</strong> ${formatDateTime(item.exit_time)}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    $('#locationTimeline').html(timelineHtml);
}
function displayAllAccessLogs(accessLogs) {
    let logsHtml = '<tr><td colspan="7" class="text-center">No access logs found</td></tr>';
    
    if (accessLogs && accessLogs.length > 0) {
        logsHtml = accessLogs.map((log, index) => {
            const accessBadge = log.access_granted == 1 
                ? '<span class="badge badge-success">GRANTED</span>' 
                : '<span class="badge badge-danger">DENIED</span>';
            
            // const ackBadge = log.acknowledge == 1 
            //     ? '<span class="badge badge-info">YES</span>' 
            //     : '<span class="badge badge-warning">NO</span>';
            
            let timeToNext = '-';
            if (log.next_time && log.created_at) {
                const timeDiff = Math.abs(new Date(log.next_time) - new Date(log.created_at));
                const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                timeToNext = `${hours}h ${minutes}m`;
            }
            
            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${formatDateTime(log.created_at)}</td>
                    <td>${log.location_name || log.location || '-'}</td>
                    <td>${accessBadge}</td>
                    <td>${log.next_location || '-'}</td>
                    <td>${timeToNext}</td>
                </tr>
            `;
        }).join('');
    }
    
    $('#accessLogsTable').html(logsHtml);
}

function displayAllLocationTimeline(timeline) {
    let timelineHtml = '<div class="text-muted">No movement data available</div>';
    
    if (timeline && timeline.length > 0) {
        timelineHtml = timeline.map((item, index) => {
            const timeSpent = item.time_spent ? 
                `${item.time_spent.hours || 0}h ${item.time_spent.minutes || 0}m ${item.time_spent.seconds || 0}s` : '-';
            
            return `
                <div class="timeline-item mb-3">
                    <div class="timeline-header d-flex justify-content-between">
                        <span class="font-weight-bold">Movement ${index + 1}</span>
                        <small class="text-muted">${formatDateTime(item.entry_time)}</small>
                    </div>
                    <div class="timeline-body p-2 border rounded bg-light">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>From:</strong><br>
                                ${item.from_location || 'Unknown'}
                            </div>
                            <div class="col-md-3">
                                <strong>To:</strong><br>
                                ${item.to_location || 'Unknown'}
                            </div>
                            <div class="col-md-3">
                                <strong>Time Spent:</strong><br>
                                ${timeSpent}
                            </div>
                            <div class="col-md-3">
                                <strong>Status:</strong><br>
                                ${item.access_granted == 1 ? '<span class="badge badge-success">GRANTED</span>' : '<span class="badge badge-danger">DENIED</span>'}
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <small><strong>Entry:</strong> ${formatDateTime(item.entry_time)}</small>
                            </div>
                            <div class="col-md-6">
                                <small><strong>Exit:</strong> ${formatDateTime(item.exit_time)}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    $('#locationTimeline').html(timelineHtml);
}


function getDateKeyFromFormatted(formattedDate) {
    // Convert dd-MMM-yyyy to yyyy-mm-dd
    const date = new Date(formattedDate);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
    
    // Function to display turnstile information
    function displayTurnstileInfo(turnstileInfo) {
        let entriesHtml = '<div class="text-muted">No entries found</div>';
        let exitsHtml = '<div class="text-muted">No exits found</div>';
        
        if (turnstileInfo.entries && turnstileInfo.entries.length > 0) {
            entriesHtml = turnstileInfo.entries.map((entry, index) => `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <span>Entry ${index + 1}</span>
                        <span class="badge badge-success">IN</span>
                    </div>
                    <small class="text-muted">${formatDateTime(entry.time)}</small><br>
                    <small>${entry.location || 'Unknown'}</small>
                </div>
            `).join('');
        }
        
        if (turnstileInfo.exits && turnstileInfo.exits.length > 0) {
            exitsHtml = turnstileInfo.exits.map((exit, index) => `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <span>Exit ${index + 1}</span>
                        <span class="badge badge-danger">OUT</span>
                    </div>
                    <small class="text-muted">${formatDateTime(exit.time)}</small><br>
                    <small>${exit.location || 'Unknown'}</small>
                </div>
            `).join('');
        }
        
        $('#turnstileEntries').html(entriesHtml);
        $('#turnstileExits').html(exitsHtml);
    }
    
    // Function to display access logs
    function displayAccessLogs(accessLogs) {
        let logsHtml = '<tr><td colspan="7" class="text-center">No access logs found</td></tr>';
        
        if (accessLogs && accessLogs.length > 0) {
            logsHtml = accessLogs.map((log, index) => {
                const accessBadge = log.access_granted == 1 
                    ? '<span class="badge badge-success">GRANTED</span>' 
                    : '<span class="badge badge-danger">DENIED</span>';
                
                // const ackBadge = log.acknowledge == 1 
                //     ? '<span class="badge badge-info">YES</span>' 
                //     : '<span class="badge badge-warning">NO</span>';
                
                let timeAtPrev = '-';
                if (log.previous_time && log.created_at) {
                    const timeDiff = Math.abs(new Date(log.created_at) - new Date(log.previous_time));
                    const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                    const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                    timeAtPrev = `${hours}h ${minutes}m`;
                }
                
                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${formatDateTime(log.created_at)}</td>
                        <td>${log.location_name || log.location || '-'}</td>
                        <td>${accessBadge}</td>
                        <td>${log.previous_location || '-'}</td>
                        <td>${timeAtPrev}</td>
                    </tr>
                `;
            }).join('');
        }
        
        $('#accessLogsTable').html(logsHtml);
    }    
    // Function to display location timeline
    function displayLocationTimeline(timeline) {
        let timelineHtml = '<div class="text-muted">No movement data available</div>';
        
        if (timeline && timeline.length > 0) {
            timelineHtml = timeline.map((item, index) => {
                const timeSpent = item.time_spent ? 
                    `${item.time_spent.hours || 0}h ${item.time_spent.minutes || 0}m ${item.time_spent.seconds || 0}s` : '-';
                
                return `
                    <div class="timeline-item mb-3">
                        <div class="timeline-header d-flex justify-content-between">
                            <span class="font-weight-bold">Step ${index + 1}</span>
                            <small class="text-muted">${formatDateTime(item.entry_time)}</small>
                        </div>
                        <div class="timeline-body p-2 border rounded bg-light">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>From:</strong> ${item.from_location || 'Unknown'}
                                </div>
                                <div class="col-md-4">
                                    <strong>To:</strong> ${item.to_location || 'Unknown'}
                                </div>
                                <div class="col-md-4">
                                    <strong>Time Spent:</strong> ${timeSpent}
                                </div>
                            </div>
                            <div class="row mt-1">
                                <div class="col-md-6">
                                    <small>Entry: ${formatDateTime(item.entry_time)}</small>
                                </div>
                                <div class="col-md-6">
                                    <small>Exit: ${formatDateTime(item.exit_time)}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }        
        $('#locationTimeline').html(timelineHtml);
    }
    

    function formatDate(dateString) {
    if (!dateString || dateString === 'N/A') return '-';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return dateString;
    }
}

function displayFilters(dates, allLogs) {
    let dateFilterHtml = '';
    let locationFilterHtml = '';
    
    // Extract unique locations from all logs
    let locations = [];
    allLogs.forEach(log => {
        if (log.location_name && !locations.includes(log.location_name)) {
            locations.push(log.location_name);
        }
    });
    
    // Date buttons
    dates.forEach((date, index) => {
        const isActive = index === 0 ? 'active' : '';
        dateFilterHtml += `
            <button type="button" class="btn btn-outline-primary date-filter-btn ${isActive}" 
                    data-date="${date}">
                ${date}
            </button>
        `;
    });
    
    // Location buttons
    locations.forEach((location, index) => {
        const isActive = index === 0 ? 'active' : '';
        locationFilterHtml += `
            <button type="button" class="btn btn-outline-info location-filter-btn ${isActive}" 
                    data-location="${location}">
                ${location}
            </button>
        `;
    });
    
    // Add to modal
    $('#dateFilters').html(dateFilterHtml);
    $('#locationFilters').html(locationFilterHtml);
    
    // Add event listeners
    $('.date-filter-btn').click(function() {
        $('.date-filter-btn').removeClass('active');
        $(this).addClass('active');
        const selectedDate = $(this).data('date');
        filterDataByDate(selectedDate);
    });
    
    $('.location-filter-btn').click(function() {
        $('.location-filter-btn').removeClass('active');
        $(this).addClass('active');
        const selectedLocation = $(this).data('location');
        filterDataByLocation(selectedLocation);
    });
}

function filterDataByLocation(location) {
    const filteredLogs = allAccessLogs.filter(log => {
        return log.location_name === location;
    });
    
    displayAccessLogsForDate(filteredLogs);
    
    // Show location-specific timeline
    const locationTimeline = generateLocationSpecificTimeline(filteredLogs);
    displayTimelineForDate(locationTimeline);
}

function generateLocationSpecificTimeline(logs) {
    let timeline = [];
    
    // Sort logs by time
    const sortedLogs = logs.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    
    for (let i = 1; i < sortedLogs.length; i++) {
        const currentLog = sortedLogs[i];
        const previousLog = sortedLogs[i - 1];
        
        const timeSpent = Math.abs(new Date(currentLog.created_at) - new Date(previousLog.created_at));
        const hours = Math.floor(timeSpent / (1000 * 60 * 60));
        const minutes = Math.floor((timeSpent % (1000 * 60 * 60)) / (1000 * 60));
        
        timeline.push({
            'from_location': previousLog.location_name,
            'to_location': currentLog.location_name,
            'entry_time': previousLog.created_at,
            'exit_time': currentLog.created_at,
            'time_spent': {
                'hours': hours,
                'minutes': minutes,
                'total_seconds': timeSpent / 1000
            },
            'access_granted': currentLog.access_granted
        });
    }    
    return timeline;
}

function filterDataByDate(date) {
    // Convert date format from dd-MMM-yyyy to yyyy-mm-dd
    const dateObj = new Date(date);
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    const dateKey = `${year}-${month}-${day}`;
    
    // Filter your data based on dateKey
    const filteredLogs = allAccessLogs.filter(log => {
        const logDate = log.created_at.split(' ')[0];
        return logDate === dateKey;
    });
    
    displayAccessLogsForDate(filteredLogs);
    displayLocationTimelineForDate(filteredLogs);
}

function formatDateTime(dateString) {
    if (!dateString || dateString === 'N/A') return '-';
    try {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
    } catch (e) {
        return dateString;
    }
}
    
    // View Details Modal Functions
    function showVisitorDetailsModal(data) {
        // Format dates for modal
        const formatModalDate = (dateString) => {
            if (!dateString || dateString === 'N/A') return '-';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } catch (e) {
                return dateString;
            }
        };
        
        // Calculate visit duration
        const calculateDuration = (from, to) => {
            if (!from || !to || from === 'N/A' || to === 'N/A') return '-';
            try {
                const fromDate = new Date(from);
                const toDate = new Date(to);
                const diffMs = toDate - fromDate;
                const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
                
                if (diffDays > 0) {
                    return `${diffDays} day(s) ${diffHours} hour(s) ${diffMinutes} minute(s)`;
                } else if (diffHours > 0) {
                    return `${diffHours} hour(s) ${diffMinutes} minute(s)`;
                } else {
                    return `${diffMinutes} minute(s)`;
                }
            } catch (e) {
                return '-';
            }
        };
        
        // Get search type display text
        const getSearchTypeText = (type) => {
            switch(type) {
                case 'STAFFNO': return 'Staff Number';
                case 'ICNO': return 'IC Number';
                default: return type || 'Auto Detect';
            }
        };
        
        // Set modal values
        $('#modalStaffNo').text(data.staffno || '-');
        $('#modalFullName').text(data.fullname || '-');
        $('#modalIcNo').text(data.icno || '-');
        $('#modalSex').text(data.sex || '-');
        $('#modalContactNo').text(data.contactno || '-');
        $('#modalCompanyName').text(data.company || '-');
        $('#modalPersonVisited').text(data.personvisited || '-');
        $('#modalDateOfVisitFrom').text(formatModalDate(data.visitfrom) || '-');
        $('#modalDateOfVisitTo').text(formatModalDate(data.visitto) || '-');
        $('#modalReason').text(data.reason || '-');
        $('#modalVisitDuration').text(calculateDuration(data.visitfrom, data.visitto));
        $('#modalLastUpdated').text(new Date().toLocaleString());
        $('#modalSearchType').text(getSearchTypeText(data.searchtype));
        
        // Show modal
        $('#viewDetailsModal').modal('show');
    }
    
    // Print details button
    $('#printDetailsBtn').click(function() {
        const printContent = `
            <html>
            <head>
                <title>Visitor Details - ${$('#modalStaffNo').text()}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                    .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    .details-table th, .details-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    .details-table th { background-color: #f2f2f2; width: 30%; }
                    .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Visitor Details Report</h2>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                </div>
                <table class="details-table">
                    <tr>
                        <th>Staff No:</th>
                        <td>${$('#modalStaffNo').text()}</td>
                    </tr>
                    <tr>
                        <th>Full Name:</th>
                        <td>${$('#modalFullName').text()}</td>
                    </tr>
                    <tr>
                        <th>IC No:</th>
                        <td>${$('#modalIcNo').text()}</td>
                    </tr>
                    <tr>
                        <th>Sex:</th>
                        <td>${$('#modalSex').text()}</td>
                    </tr>
                    <tr>
                        <th>Contact No:</th>
                        <td>${$('#modalContactNo').text()}</td>
                    </tr>
                    <tr>
                        <th>Company:</th>
                        <td>${$('#modalCompanyName').text()}</td>
                    </tr>
                    <tr>
                        <th>Person Visited:</th>
                        <td>${$('#modalPersonVisited').text()}</td>
                    </tr>
                    <tr>
                        <th>Visit From:</th>
                        <td>${$('#modalDateOfVisitFrom').text()}</td>
                    </tr>
                    <tr>
                        <th>Visit To:</th>
                        <td>${$('#modalDateOfVisitTo').text()}</td>
                    </tr>
                    <tr>
                        <th>Reason:</th>
                        <td>${$('#modalReason').text()}</td>
                    </tr>
                    <tr>
                        <th>Visit Duration:</th>
                        <td>${$('#modalVisitDuration').text()}</td>
                    </tr>
                    <tr>
                        <th>Search Type:</th>
                        <td>${$('#modalSearchType').text()}</td>
                    </tr>
                </table>
                <div class="footer">
                    <p>This is a computer-generated document. No signature required.</p>
                    <p>© ${new Date().getFullYear()} Visitor Management System</p>
                </div>
            </body>
            </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.print();
    });
    
    // Export to CSV function
    function exportToCSV() {
        if (!currentData) {
            showError('No data to export');
            return;
        }
        
        const visitors = Array.isArray(currentData) ? currentData : [currentData];        
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Visitor Details Export\n";
        csvContent += `Search Term: ${currentSearchTerm}\n`;
        csvContent += `Search Type: ${currentSearchType}\n`;
        csvContent += `Export Date: ${new Date().toLocaleString()}\n\n`;        
        // Add headers
        csvContent += "Sr.No,Staff No,Full Name,IC No,Company,Contact No,Visit From,Visit To,Status,Reason\n";
        
        // Add data rows
        visitors.forEach((visitor, index) => {
            const row = [
                index + 1,
                `"${visitor.staffNo || ''}"`,
                `"${visitor.fullName || ''}"`,
                `"${visitor.icNo || ''}"`,
                `"${visitor.companyName || ''}"`,
                `"${visitor.contactNo || ''}"`,
                `"${visitor.dateOfVisitFrom || ''}"`,
                `"${visitor.dateOfVisitTo || ''}"`,
                `"${visitor.dateOfVisitTo && new Date(visitor.dateOfVisitTo) > new Date() ? 'Active' : 'Expired'}"`,
                `"${visitor.reason || ''}"`
            ];
            csvContent += row.join(',') + '\n';
        });
        
        // Download CSV
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `visitor_${currentSearchTerm}_${Date.now()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showSuccess('Data exported successfully');
    }
    
    function clearSearch() {
        $('#searchInput').val('');
        currentSearchTerm = '';
        currentData = null;
        hideAllSections();
        initializeDataTable();
        $('#successAlert').hide();
        $('#errorAlert').hide();
        $('#exportBtn').hide();
    }
    
    function showLoading() {
        $('#loadingSection').show();
        $('#errorAlert').hide();
        $('#successAlert').hide();
    }
    
    function hideLoading() {
        $('#loadingSection').hide();
    }
    
    function hideAllSections() {
        $('#visitorTableSection').hide();
        $('#noDataSection').hide();
    }
    
    function showNoDataSection() {
        $('#visitorTableSection').hide();
        $('#noDataSection').show();
    }
    
    function showError(message) {
        $('#errorMessage').text(message);
        $('#errorAlert').show();
        $('#successAlert').hide();
    }
    
    function showSuccess(message) {
        $('#successMessage').text(message);
        $('#successAlert').show();
        $('#errorAlert').hide();
    }
    
    // Auto-focus on search input
    $('#searchInput').focus();
});
</script>
@endsection

