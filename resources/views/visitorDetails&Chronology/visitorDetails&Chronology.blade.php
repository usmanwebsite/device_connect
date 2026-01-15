@extends('layout.main_layout')

@section('title', 'Visitor Details & Chronology')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0" style="font-weight: 600; font-size: 1.8rem; margin-left: 20px !important">Visitor Details</h1>
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
                        <h3 class="card-title" style="font-size: 1.5rem;">
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
                                            <button class="btn btn-primary btn-lg" type="button" id="searchBtn" style="width: 140px !important; height: 55px; margin-left: 5px !important">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                            <button class="btn btn-secondary btn-lg" type="button" id="clearBtn" style="width: 140px !important; height: 55px">
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
                        <h3 class="card-title" style="font-weight: 600; font-size: 1.7rem;">
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
                <!-- Row 1: Full Name | Reason for Visit -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Full Name:</label>
                            <p id="modalFullName" class="border p-2 rounded bg-light">-</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Reason for Visit:</label>
                            <p id="modalReason" class="border p-2 rounded bg-light">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Row 2: IC No | Staff No -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">IC No:</label>
                            <p id="modalIcNo">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Row 3: Person Visited | Contact No -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Person Visited:</label>
                            <p id="modalPersonVisited" class="border p-2 rounded bg-light">-</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Contact No:</label>
                            <p id="modalContactNo">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Row 4: Visit From | Visit To -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Visit From:</label>
                            <p id="modalDateOfVisitFrom">-</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Visit To:</label>
                            <p id="modalDateOfVisitTo">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Row 5: Search Type | Last Updated -->
                <div class="row mb-3">
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
                
                <!-- Visit Duration (Single row) -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="font-weight-bold">Visit Duration:</label>
                            <p id="modalVisitDuration" class="text-primary font-weight-bold">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Company Name (Single row at bottom) -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="font-weight-bold mb-1">Company Name:</h6>
                                <p class="mb-0" id="modalCompanyName">-</p>
                            </div>
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

    const urlParams = new URLSearchParams(window.location.search);
    const autoSearch = urlParams.get('autoSearch');
    const staffNo = urlParams.get('staffNo');
    const icNo = urlParams.get('icNo');
    const searchBy = urlParams.get('searchBy');
    const forceIcNo = urlParams.get('forceIcNo');
    
    console.log('URL Parameters:', {
        autoSearch,
        staffNo,
        icNo,
        searchBy,
        forceIcNo
    });
    
    if (autoSearch === 'true') {
        // Hide all sections initially
        hideAllSections();
        
        // forceIcNo پر مبنی انتخاب کریں
        if (forceIcNo === 'true' && icNo) {
            $('#searchType').val('icNo');
            $('#searchInput').val(icNo);
            console.log('Using IC Number (forced):', icNo);
        } 
        // اگر searchBy پارامیٹر موجود ہے
        else if (searchBy === 'icNo' && icNo) {
            $('#searchType').val('icNo');
            $('#searchInput').val(icNo);
            console.log('Using IC Number:', icNo);
        }
        else if (searchBy === 'staffNo' && staffNo) {
            $('#searchType').val('staffNo');
            $('#searchInput').val(staffNo);
            console.log('Using Staff No:', staffNo);
        }
        // Default: staffNo استعمال کریں
        else if (staffNo) {
            $('#searchType').val('staffNo');
            $('#searchInput').val(staffNo);
            console.log('Using Staff No (default):', staffNo);
        }
        
        // Trigger search after a short delay
        setTimeout(() => {
            searchVisitor();
        }, 500);
    }


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
                { "orderable": false, "targets": [0, 8] },
                { "className": "text-center", "targets": [0, 7, 8] },
                { "width": "5%", "targets": 0 },
                { "width": "15%", "targets": 8 }
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

    $('#closeViewDetailsModalBtn').click(function() {
        $('#viewDetailsModal').modal('hide');
    });

    
    // // Close View Details Modal (Close button)
    // $('#closeViewDetailsModalBtn').click(function() {
    //     $('#viewDetailsModal').modal('hide');
    // });

    $('#closeChronologyModal').click(function() {
        $('#chronologyModal').modal('hide');
    });
    
    // Close Chronology Modal (Close button)
    $('#closeChronologyModalBtn').click(function() {
        $('#chronologyModal').modal('hide');
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
    
    currentSearchTerm = searchTerm;
    currentSearchType = searchType;
    
    showLoading();
    hideAllSections();
    
    console.log('Searching for:', searchTerm, 'Type:', searchType);
    
    // TEMPORARY: Direct Java API call
    const javaBaseUrl = 'http://127.0.0.1:8080';
    let url = `${javaBaseUrl}/api/vendorpass/get-visitor-details?icNo=${searchTerm}`;
    
    if (searchType === 'staffNo') {
        url = `${javaBaseUrl}/api/vendorpass/get-visitor-details?staffNo=${searchTerm}`;
    }
    
    console.log('Direct API URL:', url);
    
    // Direct fetch to Java API
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Direct API Response:', data);
            hideLoading();
            
            if (data && data.status === 'success') {
                currentData = data.data;
                displayVisitorData(data.data);
                showSuccess('Visitor details found successfully');
                $('#exportBtn').show();
            } else {
                showError(data.message || 'Visitor not found');
                showNoDataSection();
                $('#exportBtn').hide();
            }
        })
        .catch(error => {
            console.error('Direct API Error:', error);
            hideLoading();
            showError('Error connecting to API');
            showNoDataSection();
            $('#exportBtn').hide();
        });
}
    
function displayVisitorData(data) {
    console.log('========== DISPLAY VISITOR DATA START ==========');
    console.log('Raw data received:', data);
    
    const visitors = Array.isArray(data) ? data : [data];
    
    console.log('Number of visitors found:', visitors.length);
    
    // Clear existing data
    if (visitorDataTable) {
        visitorDataTable.clear().draw();
    }
    
    // Improved Date Formatting Function
    const formatDate = (dateString) => {
        if (!dateString || 
            dateString === 'N/A' || 
            dateString === 'null' || 
            dateString === null || 
            dateString === undefined) {
            return '-';
        }
        
        try {
            const date = new Date(dateString);
            
            if (isNaN(date.getTime())) {
                return '-';
            }
            
            const formattedDate = date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            
            const formattedTime = date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            
            return `${formattedDate} ${formattedTime}`;
            
        } catch (e) {
            return '-';
        }
    };
    
    // Calculate status badge
    const getStatusBadge = (visitTo) => {
        if (!visitTo || visitTo === 'N/A') {
            return '<span class="badge badge-secondary">Unknown</span>';
        }
        
        try {
            const now = new Date();
            const visitEnd = new Date(visitTo);
            
            if (isNaN(visitEnd.getTime())) {
                return '<span class="badge badge-secondary">Unknown</span>';
            }
            
            if (visitEnd > now) {
                return '<span class="badge badge-success">Active</span>';
            } else {
                return '<span class="badge badge-warning">Expired</span>';
            }
        } catch (e) {
            return '<span class="badge badge-secondary">Error</span>';
        }
    };
    
    // Add each visitor to the table
    let validVisitorsCount = 0;
    
    visitors.forEach((visitor, index) => {
        // Check if visitor has valid information
        const hasValidData = visitor && 
            ((visitor.fullName && visitor.fullName !== 'N/A') || 
             (visitor.icNo && visitor.icNo !== 'N/A'));
        
        if (!hasValidData) {
            console.log('Skipping invalid visitor data:', visitor);
            return;
        }
        
        // Format dates for this visitor
        const formattedFrom = formatDate(visitor.dateOfVisitFrom);
        const formattedTo = formatDate(visitor.dateOfVisitTo);
        
        // Add data to table with both View and Chronology buttons
        // NOTE: Staff No removed from rowData array
        const rowData = [
            index + 1, // Serial number
            visitor.fullName || '-',
            visitor.icNo || '-',
            visitor.companyName || '-',
            visitor.contactNo || '-',
            formattedFrom,
            formattedTo,
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
            validVisitorsCount++;
        }
    });
    
    console.log('Valid visitors added to table:', validVisitorsCount);
    console.log('DataTable row count:', visitorDataTable ? visitorDataTable.data().count() : 0);
    console.log('========== DISPLAY VISITOR DATA END ==========');
    
    // If no valid data was added
    if (visitors.length === 0 || (visitorDataTable && visitorDataTable.data().count() === 0)) {
        showError('No visitor data found');
        showNoDataSection();
        return;
    }
    
    // Show table section
    $('#visitorTableSection').show();
    $('#noDataSection').hide();
    
    // Update success message with count
    showSuccess(`Found ${validVisitorsCount} visitor(s) matching your search`);
    
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
    console.log("Loading chronology for IC No:", icNo);
    
    $.ajax({
        url: '{{ route("visitor-details.chronology") }}',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            staff_no: staffNo || '',
            ic_no: icNo
        },
        success: function(response) {
            $('#chronologyLoading').hide();
            
            console.log("Chronology API Response:", response);
            
            if (response.success) {
                const data = response.data;
                
                // Debug logging
                console.log("Dates available:", data.dates);
                console.log("Logs by date keys:", Object.keys(data.logs_by_date));
                console.log("Timeline by date keys:", Object.keys(data.timeline_by_date));
                
                // Check if we have data
                if (!data.dates || data.dates.length === 0) {
                    console.warn("No dates found in response");
                    $('#chronologyErrorMessage').text('No chronology data available for this visitor');
                    $('#chronologyError').show();
                    return;
                }
                
                displayChronologyData(data);
                $('#chronologyContent').show();
            } else {
                console.error("API error:", response.message);
                $('#chronologyErrorMessage').text(response.message || 'Error loading chronology');
                $('#chronologyError').show();
            }
        },
        error: function(xhr, status, error) {
            $('#chronologyLoading').hide();
            console.error("AJAX Error:", error, xhr.responseText);
            
            let errorMessage = 'An error occurred while loading chronology';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            
            $('#chronologyErrorMessage').text(errorMessage);
            $('#chronologyError').show();
        }
    });
}
    
function displayChronologyData(data) {
    console.log("Displaying chronology data:", data);
    
    // 1. Update summary cards
    if (data.current_status) {
        $('#currentStatus').text(data.current_status.status.toUpperCase());
        $('#currentStatusMsg').text(data.current_status.message);
        
        const statusCard = $('#currentStatus').closest('.card');
        statusCard.removeClass('bg-primary bg-success bg-secondary bg-warning');
        
        if (data.current_status.status === 'in_building') {
            statusCard.addClass('bg-success');
        } else if (data.current_status.status === 'out_of_building') {
            statusCard.addClass('bg-secondary');
        } else {
            statusCard.addClass('bg-warning');
        }
    }
    
    if (data.total_time_spent) {
        $('#totalTimeSpent').text(data.total_time_spent.formatted || data.total_time_spent);
    }
    
    if (data.summary) {
        $('#totalVisits').text(data.dates ? data.dates.length : '0');
        $('#totalAccesses').text(data.summary.total_visits || '0');
        
        if (data.summary.total_visits > 0) {
            const successRate = Math.round((data.summary.successful_accesses / data.summary.total_visits) * 100);
            $('#accessSuccessRate').text(`${successRate}% successful`);
        } else {
            $('#accessSuccessRate').text('No visits');
        }
    }
    
    // 2. Display date buttons
    console.log("Processing dates for buttons:", data.dates);
    if (data.dates && data.dates.length > 0) {
        displayDateButtons(data.dates, data.logs_by_date, data.timeline_by_date);
        $('#dateSelectionSection').show();
    } else {
        $('#dateSelectionSection').hide();
        $('#selectedDateInfo').hide();
        
        // Show all data if no dates
        if (data.all_access_logs) {
            displayAccessLogsForDate(data.all_access_logs);
        }
        if (data.all_location_timeline) {
            displayTimelineForDate(data.all_location_timeline);
        }
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
    console.log("=== DISPLAY DATE BUTTONS DEBUG ===");
    console.log("Dates array:", dates);
    console.log("Logs by date object:", logsByDate);
    console.log("Timeline by date object:", timelineByDate);
    
    let dateButtonsHtml = '';
    
    dates.forEach((date, index) => {
        // Try different key formats
        const possibleKeys = [
            date, // original format
            date.trim(), // trimmed version
            date.replace(/ /g, ''), // without spaces
            formatDateForKey(date) // formatted key
        ];
        
        let logsCount = 0;
        let timelineCount = 0;
        
        // Try each possible key
        for (let key of possibleKeys) {
            if (logsByDate && logsByDate[key]) {
                logsCount = logsByDate[key].length;
                console.log(`Found logs for key: "${key}" with ${logsCount} logs`);
                break;
            }
        }
        
        for (let key of possibleKeys) {
            if (timelineByDate && timelineByDate[key]) {
                timelineCount = timelineByDate[key].length;
                console.log(`Found timeline for key: "${key}" with ${timelineCount} items`);
                break;
            }
        }
        
        const isActive = index === 0 ? 'active' : '';
        dateButtonsHtml += `
            <button type="button" class="btn btn-outline-primary date-btn ${isActive}" 
                    data-date="${date}">
                ${date}
                <span class="badge badge-light ml-1">${logsCount} logs</span>
            </button>
        `;
    });
    
    $('#dateButtons').html(dateButtonsHtml);
    
    // Set first date as selected by default
    if (dates.length > 0) {
        const firstDate = dates[0];
        displayDataForDate(firstDate, logsByDate, timelineByDate);
    }
    
    // Add click event to date buttons
    $('.date-btn').click(function() {
        // Remove active class from all buttons
        $('.date-btn').removeClass('active');
        // Add active class to clicked button
        $(this).addClass('active');
        
        const selectedDate = $(this).data('date');
        displayDataForDate(selectedDate, logsByDate, timelineByDate);
    });
    
    console.log("=== END DATE BUTTONS DEBUG ===");
}

// Helper function to format date for key matching
function formatDateForKey(dateString) {
    try {
        // Try to parse the date and format it
        const parts = dateString.split('-');
        if (parts.length === 3) {
            const day = parts[0];
            const month = parts[1];
            const year = parts[2];
            
            // Create a standard date format
            const date = new Date(`${month} ${day}, ${year}`);
            if (!isNaN(date.getTime())) {
                return date.toLocaleDateString('en-US', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
            }
        }
    } catch (e) {
        console.error("Error formatting date:", e);
    }
    
    // Return original if parsing fails
    return dateString;
}

function displayDataForDate(date, logsByDate, timelineByDate) {
    console.log("=== DISPLAY DATA FOR DATE DEBUG ===");
    console.log("Selected date:", date);
    console.log("All logsByDate keys:", logsByDate ? Object.keys(logsByDate) : 'No logsByDate');
    console.log("All timelineByDate keys:", timelineByDate ? Object.keys(timelineByDate) : 'No timelineByDate');
    
    // Try different key formats
    const possibleKeys = [
        date, // original format
        date.trim(), // trimmed version
        date.replace(/ /g, ''), // without spaces
        formatDateForKey(date) // formatted key
    ];
    
    let accessLogs = [];
    let timeline = [];
    
    // Find logs for the date
    for (let key of possibleKeys) {
        if (logsByDate && logsByDate[key]) {
            accessLogs = logsByDate[key];
            console.log(`Found logs for key: "${key}"`);
            break;
        }
    }
    
    // Find timeline for the date
    for (let key of possibleKeys) {
        if (timelineByDate && timelineByDate[key]) {
            timeline = timelineByDate[key];
            console.log(`Found timeline for key: "${key}"`);
            break;
        }
    }
    
    console.log(`Access logs found: ${accessLogs.length}`);
    console.log(`Timeline items found: ${timeline.length}`);
    
    // Update selected date info
    $('#selectedDateText').text(date);
    $('#selectedDateInfo').show();
    
    // Update indicators
    $('#logsDateIndicator').text(date);
    $('#timelineDateIndicator').text(date);
    
    // Display data
    displayAccessLogsForDate(accessLogs);
    displayTimelineForDate(timeline);
    
    console.log("=== END DISPLAY DATA DEBUG ===");
}

function displayAccessLogsForDate(accessLogs) {
    let logsHtml = '';
    
    if (accessLogs && accessLogs.length > 0) {
        logsHtml = accessLogs.map((log, index) => {
            const accessBadge = log.access_granted == 1 
                ? '<span class="badge badge-success">GRANTED</span>' 
                : '<span class="badge badge-danger">DENIED</span>';
            
            // Format time to next location
            let timeToNext = '-';
            if (log.next_time && log.created_at) {
                const timeDiff = Math.abs(new Date(log.next_time) - new Date(log.created_at));
                const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                
                if (hours > 0) {
                    timeToNext = `${hours}h ${minutes}m`;
                } else if (minutes > 0) {
                    timeToNext = `${minutes}m ${seconds}s`;
                } else {
                    timeToNext = `${seconds}s`;
                }
            }
            
            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${formatDateTime(log.created_at)}</td>
                    <td>${log.location_name || 'Unknown'}</td>
                    <td>${accessBadge}</td>
                    <td>${log.next_location || '-'}</td>
                    <td>${timeToNext}</td>
                </tr>
            `;
        }).join('');
    } else {
        logsHtml = '<tr><td colspan="6" class="text-center">No access logs found for this date</td></tr>';
    }
    
    $('#accessLogsTable').html(logsHtml);
}

function displayTimelineForDate(timeline) {
    console.log("Timeline items received:", timeline);
    console.log("Number of timeline items:", timeline ? timeline.length : 0);
    
    if (timeline && timeline.length > 0) {
        console.log("First timeline item:", timeline[0]);
        console.log("Last timeline item:", timeline[timeline.length - 1]);
    }
    let timelineHtml = '';
    
    if (timeline && timeline.length > 0) {
        console.log("Timeline items to display:", timeline);
        
        timelineHtml = timeline.map((item, index) => {
            // Calculate time spent
            let timeSpentText = '-';
            if (item.time_spent) {
                const hours = item.time_spent.hours || 0;
                const minutes = item.time_spent.minutes || 0;
                const seconds = item.time_spent.seconds || 0;
                
                if (hours > 0) {
                    timeSpentText = `${hours}h ${minutes}m ${seconds}s`;
                } else if (minutes > 0) {
                    timeSpentText = `${minutes}m ${seconds}s`;
                } else {
                    timeSpentText = `${seconds}s`;
                }
            }
            
            const accessBadge = item.access_granted == 1 
                ? '<span class="badge badge-success">GRANTED</span>' 
                : '<span class="badge badge-danger">DENIED</span>';
            
            return `
                <div class="timeline-item mb-3">
                    <div class="timeline-header d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold">
                            <i class="fas fa-route mr-1"></i>
                            Movement ${index + 1}
                        </span>
                        <small class="text-muted">${formatDateTime(item.entry_time)}</small>
                    </div>
                    <div class="timeline-body p-3 border rounded bg-light">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <strong>From:</strong><br>
                                <div class="mt-1 p-2 bg-white rounded border">
                                    ${item.from_location || 'Unknown'}
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <strong>To:</strong><br>
                                <div class="mt-1 p-2 bg-white rounded border">
                                    ${item.to_location || 'Unknown'}
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <strong>Time Spent:</strong><br>
                                <div class="mt-1 p-2 bg-white rounded border">
                                    <i class="fas fa-clock mr-1"></i>${timeSpentText}
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <strong>Access Status:</strong><br>
                                <div class="mt-1 p-2 bg-white rounded border">
                                    ${accessBadge}
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-sign-in-alt mr-1"></i>
                                    <strong>Entry:</strong> ${formatDateTime(item.entry_time)}
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-sign-out-alt mr-1"></i>
                                    <strong>Exit:</strong> ${formatDateTime(item.exit_time)}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } else {
        timelineHtml = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i>
                No movement data available for the selected date.
            </div>
        `;
    }
    
    $('#locationTimeline').html(timelineHtml);
    console.log("Timeline HTML set for", timeline.length, "items");
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
    
    // Set modal values with new layout
    $('#modalFullName').text(data.fullname || '-');
    $('#modalReason').text(data.reason || '-');
    $('#modalIcNo').text(data.icno || '-');
    $('#modalPersonVisited').text(data.personvisited || '-');
    $('#modalContactNo').text(data.contactno || '-');
    $('#modalDateOfVisitFrom').text(formatModalDate(data.visitfrom) || '-');
    $('#modalDateOfVisitTo').text(formatModalDate(data.visitto) || '-');
    $('#modalSearchType').text(getSearchTypeText(data.searchtype));
    $('#modalLastUpdated').text(new Date().toLocaleString());
    $('#modalVisitDuration').text(calculateDuration(data.visitfrom, data.visitto));
    $('#modalCompanyName').text(data.company || '-');
    
    // Show modal
    $('#viewDetailsModal').modal('show');
}
    
// Print details button
$('#printDetailsBtn').click(function() {
    const printContent = `
        <html>
        <head>
            <title>Visitor Details - ${$('#modalFullName').text()}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .details-table th, .details-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .details-table th { background-color: #f2f2f2; width: 30%; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                .two-column-layout { display: flex; gap: 20px; margin-bottom: 15px; }
                .column { flex: 1; }
                .field-row { margin-bottom: 10px; }
                .field-label { font-weight: bold; display: block; margin-bottom: 5px; }
                .field-value { padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9; }
                .highlight-box { background-color: #f0f8ff; padding: 10px; border: 1px solid #cce5ff; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Visitor Details Report</h2>
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>
            
            <div class="two-column-layout">
                <div class="column">
                    <div class="field-row">
                        <span class="field-label">Full Name:</span>
                        <div class="field-value">${$('#modalFullName').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">IC No:</span>
                        <div class="field-value">${$('#modalIcNo').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Person Visited:</span>
                        <div class="field-value highlight-box">${$('#modalPersonVisited').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Visit From:</span>
                        <div class="field-value">${$('#modalDateOfVisitFrom').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Search Type:</span>
                        <div class="field-value">${$('#modalSearchType').text()}</div>
                    </div>
                </div>
                
                <div class="column">
                    <div class="field-row">
                        <span class="field-label">Reason for Visit:</span>
                        <div class="field-value highlight-box">${$('#modalReason').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Staff No:</span>
                        <div class="field-value">${$('#modalStaffNo').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Contact No:</span>
                        <div class="field-value">${$('#modalContactNo').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Visit To:</span>
                        <div class="field-value">${$('#modalDateOfVisitTo').text()}</div>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Last Updated:</span>
                        <div class="field-value">${$('#modalLastUpdated').text()}</div>
                    </div>
                </div>
            </div>
            
            <div class="field-row">
                <span class="field-label">Visit Duration:</span>
                <div class="field-value" style="font-weight: bold; color: #007bff;">${$('#modalVisitDuration').text()}</div>
            </div>
            
            <div class="field-row">
                <span class="field-label">Company Name:</span>
                <div class="field-value highlight-box">${$('#modalCompanyName').text()}</div>
            </div>
            
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
        
        // Updated headers without Staff No
        csvContent += "Sr.No,Full Name,IC No,Company,Contact No,Visit From,Visit To,Status,Reason\n";
        
        // Add data rows without Staff No
        visitors.forEach((visitor, index) => {
            const row = [
                index + 1,
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

