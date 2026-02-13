@extends('layout.main_layout')

@section('title', 'Visitor Info By Door')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0" style="font-size: 22px !important">
                        <i class="fas fa-door-open me-2"></i>Visitor Information By Door/Location
                    </h5>
                </div>
                <div class="card-body">

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="locationSelect"><i class="fas fa-map-marker-alt me-2"></i>Select Location</label>
                                <select class="form-control select2" id="locationSelect" style="width: 100%;">
                                    <option value="">-- Select a Location --</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->name }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="dateFilter"><i class="fas fa-calendar-alt me-2"></i>Select Date</label>
                                <input type="date" class="form-control" id="dateFilter" value="{{ date('Y-m-d') }}" style="height: 52px !important">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" id="fetchVisitorsBtn" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Fetch Visitors
                            </button>
                            <button type="button" id="exportBtn" class="btn btn-success" style="height: 44px !important; width: 160px" disabled>
                                <i class="fas fa-download me-1"></i> Export CSV
                            </button>
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div id="resultsSection" class="d-none">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-info-circle me-2"></i>
                                            Showing visitors for: <strong id="selectedLocationText"></strong>
                                            | Date: <strong id="selectedDateText"></strong>
                                            | Total Visitors: <span id="visitorCount" class="badge bg-primary"></span>
                                        </div>
                                        <div>
                                            Last Updated: <span id="lastUpdated"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loading Spinner -->
                        <div id="loadingSpinner" class="text-center d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Fetching visitor data...</p>
                        </div>

                        <!-- Visitors Table -->
                        <div class="table-responsive" id="visitorsTableContainer">
                            <table class="table table-bordered table-hover table-striped" id="visitorsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Visitor Name</th> <!-- Column 1 -->
                                        <th>Contact No</th> <!-- Column 2 -->
                                        <th>Staff No</th> <!-- Column 3 -->
                                        <th>Person Visited</th> <!-- Column 4 -->
                                        <th>Check-in Time</th> <!-- Column 5 -->
                                        <th>Location</th> <!-- Column 6 -->
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="visitorsTableBody">
                                    <!-- Data will be populated here -->
                                </tbody>
                            </table>
                        </div>

                        <!-- No Data Message -->
                        <div id="noDataMessage" class="text-center d-none">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No visitor data found for the selected location and date.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Visitor Details Modal -->
<div class="modal fade" id="visitorDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-circle me-2"></i>Visitor Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Loading for modal -->
                <div id="modalLoading" class="text-center d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading visitor details...</p>
                </div>

                <!-- Visitor Details Content -->
                <div id="visitorDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')

<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2({
            placeholder: 'Select a location',
            allowClear: true,
            theme: 'bootstrap-5'
        });

        let currentLocation = '';
        let currentDate = '';

        // Fetch visitors when button is clicked
        $('#fetchVisitorsBtn').click(function() {
            const locationName = $('#locationSelect').val();
            const selectedDate = $('#dateFilter').val();
            
            if (!locationName) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Location Required',
                    text: 'Please select a location first.'
                });
                return;
            }

            if (!selectedDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Date Required',
                    text: 'Please select a date.'
                });
                return;
            }

            currentLocation = locationName;
            currentDate = selectedDate;
            fetchVisitorsByLocationAndDate(locationName, selectedDate);
        });

        // Export functionality
        $('#exportBtn').click(function() {
            if (!currentLocation || !currentDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Data',
                    text: 'Please fetch visitor data first.'
                });
                return;
            }

            // Get CSRF token from meta tag
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            
            // Create a form for export
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = "{{ route('visitor-info-door.export') }}";
            
            // Add location parameter
            const locationInput = document.createElement('input');
            locationInput.type = 'hidden';
            locationInput.name = 'location_name';
            locationInput.value = currentLocation;
            form.appendChild(locationInput);
            
            // Add date parameter
            const dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'selected_date';
            dateInput.value = currentDate;
            form.appendChild(dateInput);
            
            // Add CSRF token
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrfToken;
            form.appendChild(tokenInput);
            
            // Submit form
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });

        // Fetch visitors function with date filter
        function fetchVisitorsByLocationAndDate(locationName, selectedDate) {
            // Show loading and results section
            $('#resultsSection').removeClass('d-none');
            $('#loadingSpinner').removeClass('d-none');
            $('#visitorsTableContainer').addClass('d-none');
            $('#noDataMessage').addClass('d-none');
            
            // Update selected location and date text
            $('#selectedLocationText').text(locationName);
            $('#selectedDateText').text(selectedDate);

            // Get CSRF token from meta tag
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            $.ajax({
                url: "{{ route('visitor-info-door.get-visitors') }}",
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                data: {
                    location_name: locationName,
                    selected_date: selectedDate
                },
                success: function(response) {
                    $('#loadingSpinner').addClass('d-none');
                    
                    if (response.success) {
                        if (response.count > 0) {
                            populateVisitorsTable(response.visitors);
                            $('#visitorCount').text(response.count);
                            $('#lastUpdated').text(response.timestamp);
                            $('#visitorsTableContainer').removeClass('d-none');
                            $('#exportBtn').prop('disabled', false);
                        } else {
                            $('#noDataMessage').removeClass('d-none');
                            $('#visitorCount').text('0');
                            $('#exportBtn').prop('disabled', true);
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to fetch visitor data.'
                        });
                    }
                },
                error: function(xhr) {
                    $('#loadingSpinner').addClass('d-none');
                    
                    let errorMessage = 'An error occurred while fetching data. Please try again.';
                    
                    if (xhr.status === 419) {
                        errorMessage = 'Session expired. Please refresh the page and try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: errorMessage
                    });
                }
            });
        }

        // Populate visitors table
// Populate visitors table with NEW COLUMN ORDER
function populateVisitorsTable(visitors) {
    const tbody = $('#visitorsTableBody');
    tbody.empty();

    visitors.forEach((visitor, index) => {
        const row = `
            <tr class="visitor-row" data-staff-no="${visitor.staff_no}">
                <td>${index + 1}</td>
                <td>${visitor.visitor_name}</td> <!-- Visitor Name - Column 1 -->
                <td>${visitor.contact_no}</td> <!-- Contact No - Column 2 -->
                <td><span class="badge bg-info">${visitor.staff_no}</span></td> <!-- Staff No - Column 3 -->
                <td>${visitor.person_visited}</td> <!-- Person Visited - Column 4 -->
                <td>${visitor.check_in_time}</td> <!-- Check-in Time - Column 5 -->
                <td><span class="badge bg-secondary">${visitor.location_name}</span></td> <!-- Location - Column 6 -->
                <td>
                    <button class="btn btn-sm btn-info view-details-btn" 
                            data-staff-no="${visitor.staff_no}"
                            title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });

    // Add click event for view details
    $('.view-details-btn').click(function(e) {
        e.stopPropagation();
        const staffNo = $(this).data('staff-no');
        showVisitorDetails(staffNo);
    });

    // Add click event for entire row
    $('.visitor-row').click(function() {
        const staffNo = $(this).data('staff-no');
        showVisitorDetails(staffNo);
    });
}

        // Show visitor details in modal
        function showVisitorDetails(staffNo) {
            $('#modalLoading').removeClass('d-none');
            $('#visitorDetailsContent').empty();
            
            // Remove any existing modal-backdrop
            $('.modal-backdrop').remove();
            
            // Show modal with backdrop
            $('#visitorDetailsModal').modal('show');

            $.ajax({
                url: "{{ route('visitor-info-door.get-visitor-details') }}",
                type: 'GET',
                data: {
                    staff_no: staffNo
                },
                success: function(response) {
                    $('#modalLoading').addClass('d-none');
                    
                    if (response.success) {
                        renderVisitorDetails(response);
                    } else {
                        $('#visitorDetailsContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${response.message || 'Failed to load visitor details.'}
                            </div>
                        `);
                    }
                },
                error: function(xhr) {
                    $('#modalLoading').addClass('d-none');
                    let errorMessage = 'An error occurred while loading details.';
                    
                    if (xhr.status === 419) {
                        errorMessage = 'Session expired. Please refresh the page.';
                    }
                    
                    $('#visitorDetailsContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            ${errorMessage}
                        </div>
                    `);
                }
            });
        }

// Render visitor details in modal with NEW ORDER
function renderVisitorDetails(response) {
    const visitor = response.visitor;

    function upper(val) {
        return val ? val.toString().toUpperCase() : 'N/A';
    }
    
    let content = `
        <div class="detail-section">
            <h6 class="detail-label mb-3" style="font-size: 20px !important;">
                <i class="fas fa-user me-2"></i>Visitor Information
            </h6>
            <div class="row">

                <div class="col-md-6 detail-item">
                    <div class="detail-label">VISITOR NAME</div>
                    <div class="detail-value">${upper(visitor.full_name || visitor.visitor_name)}</div>
                </div>

                <div class="col-md-6 detail-item">
                    <div class="detail-label">CONTACT NO</div>
                    <div class="detail-value">${visitor.contact_no}</div>
                </div>

                <div class="col-md-6 detail-item">
                    <div class="detail-label">PERSON VISITED</div>
                    <div class="detail-value">${upper(visitor.person_visited)}</div>
                </div>

                <div class="col-md-6 detail-item">
                    <div class="detail-label">IC NO</div>
                    <div class="detail-value">${visitor.ic_no}</div>
                </div>

                <!-- COMPANY moved to SEX position -->
                <div class="col-md-6 detail-item">
                    <div class="detail-label">COMPANY</div>
                    <div class="detail-value">${upper(visitor.company_name)}</div>
                </div>

                <!-- SEX moved to COMPANY position -->
                <div class="col-md-6 detail-item">
                    <div class="detail-label">SEX</div>
                    <div class="detail-value">${upper(visitor.sex)}</div>
                </div>

                <div class="col-md-6 detail-item">
                <div class="detail-label">VISIT FROM</div>
                <div class="detail-value">${visitor.date_of_visit_from}</div>
                </div>

                <div class="col-md-6 detail-item">
                <div class="detail-label">VISIT TO</div>
                <div class="detail-value">${visitor.date_of_visit_to}</div>
                </div>

    `;

    if (visitor.email && visitor.email !== 'N/A') {
        content += `
                <div class="col-md-6 detail-item">
                    <div class="detail-label">EMAIL</div>
                    <div class="detail-value">${visitor.email}</div>
                </div>
        `;
    }

    if (visitor.purpose_of_visit && visitor.purpose_of_visit !== 'N/A') {
        content += `
                <div class="col-md-12 detail-item">
                    <div class="detail-label">PURPOSE OF VISIT</div>
                    <div class="detail-value">${visitor.purpose_of_visit}</div>
                </div>
        `;
    }

    if (visitor.check_in_time && visitor.check_in_time !== 'N/A') {
        content += `
                <div class="col-md-6 detail-item">
                    <div class="detail-label">CHECK-IN TIME</div>
                    <div class="detail-value">${visitor.check_in_time}</div>
                </div>
        `;
    }

    content += `
            </div>
        </div>
    `;

    $('#visitorDetailsContent').html(content);
}


        // Auto-refresh data every 30 seconds if data is loaded
        let refreshInterval;
        
        function startAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
            
            refreshInterval = setInterval(() => {
                if (currentLocation && currentDate) {
                    fetchVisitorsByLocationAndDate(currentLocation, currentDate);
                }
            }, 30000); // 30 seconds
        }

        // Start auto-refresh when data is loaded
        $(document).ajaxSuccess(function(event, xhr, settings) {
            if (settings.url.includes('get-visitors') && xhr.responseJSON && xhr.responseJSON.success) {
                startAutoRefresh();
            }
        });

        // Clear interval when page is hidden
        $(window).on('blur', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });

        // Restart interval when page is visible again
        $(window).on('focus', function() {
            if (currentLocation && currentDate) {
                startAutoRefresh();
            }
        });
    });
</script>
@endsection

