@extends('layout.main_layout')

@section('content')
<div class="container mt-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Device Assignments</h4>
        <div>
            <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#ipRangeModal">
                <i class="fas fa-network-wired"></i> IP Range Settings
            </button>
            <a href="{{ route('device-assignments.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Assign New Device
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Current IP Range Display -->
    @if($ipRange)
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-info-circle"></i> 
            <strong>Current IP Range:</strong> 
            {{ $ipRange->ip_range_from }} - {{ $ipRange->ip_range_to }}
        </div>
        <button type="button" class="btn btn-sm btn-outline-dark" 
                data-bs-toggle="modal" data-bs-target="#ipRangeModal">
            <i class="fas fa-edit"></i> Change
        </button>
    </div>
    @else
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>IP Range not set!</strong> 
        Please configure the IP range settings.
        <button type="button" class="btn btn-sm btn-warning ms-2" 
                data-bs-toggle="modal" data-bs-target="#ipRangeModal">
            <i class="fas fa-cog"></i> Configure
        </button>
    </div>
    @endif

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="assignmentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Device ID</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Registration</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Last Heartbeat</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assignmentsData as $index => $device)
                    <tr id="row-{{ $device['assignment_id'] ?? $device['device_connection_id'] }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $device['device_id'] }}</td>
                        <td>{{ $device['ip'] ?? 'N/A' }}</td>
                        
                        <!-- Status Column -->
                        <td>
                            @if($device['status'] == 'online')
                                <span class="badge bg-success status-badge">
                                    <i class="fas fa-wifi"></i> Online
                                </span>
                            @else
                                <span class="badge bg-secondary status-badge">
                                    <i class="fas fa-times-circle"></i> Offline
                                </span>
                            @endif
                        </td>
                        
                        <!-- Registration Status Column -->
                        <td>
                            @if($device['is_registered'])
                                <span class="badge bg-primary registration-badge">
                                    <i class="fas fa-check-circle"></i> Registered
                                </span>
                            @else
                                <span class="badge bg-warning text-dark registration-badge">
                                    <i class="fas fa-exclamation-triangle"></i> Unregistered
                                </span>
                            @endif
                        </td>
                        
                        <td>{{ $device['location_name'] ?? 'N/A' }}</td>
                        <td>
                            @if($device['is_type'] == 'check_in')
                                <span class="badge bg-success">Check-In</span>
                            @elseif($device['is_type'] == 'check_out')
                                <span class="badge bg-warning text-dark">Check-Out</span>
                            @else
                                <span class="badge bg-secondary">N/A</span>
                            @endif
                        </td>
                        
                        <td class="last-heartbeat">
                            @if($device['last_heartbeat'])
                                {{ \Carbon\Carbon::parse($device['last_heartbeat'])->format('d-m-Y H:i:s') }}
                            @else
                                Never
                            @endif
                        </td>
                        
                        <td>
                            <div class="btn-group" role="group">
                                @if($device['is_registered'])
                                    <!-- Edit button only for registered devices -->
                                    <a href="{{ route('device-assignments.edit', $device['assignment_id']) }}" 
                                       class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    
                                    <!-- Delete button only for registered devices -->
                                    <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                            data-id="{{ $device['assignment_id'] }}"
                                            data-url="{{ route('device-assignments.destroy', $device['assignment_id']) }}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                @else
                                    <!-- For unregistered devices, show only "Assign" button -->
                                    <a href="{{ route('device-assignments.create') }}?device_id={{ $device['device_connection_id'] }}" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-link"></i> Assign
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- IP Range Modal -->
<div class="modal fade" id="ipRangeModal" tabindex="-1" aria-labelledby="ipRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ipRangeModalLabel">
                    <i class="fas fa-network-wired"></i> IP Range Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('device-assignments.update-ip-range') }}" method="POST" id="ipRangeForm">
                @csrf
                <div class="modal-body">
                    <!-- Validation Errors Display -->
                    <div id="ipRangeErrors" class="alert alert-danger d-none">
                        <ul id="errorList" class="mb-0">
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ip_range_from" class="form-label">IP Range From *</label>
                        <input type="text" class="form-control" id="ip_range_from" 
                               name="ip_range_from" placeholder="e.g., 192.168.1.1"
                               value="{{ $ipRange->ip_range_from ?? '' }}" required>
                        <div class="form-text">Starting IP address of the range</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ip_range_to" class="form-label">IP Range To *</label>
                        <input type="text" class="form-control" id="ip_range_to" 
                               name="ip_range_to" placeholder="e.g., 192.168.1.254"
                               value="{{ $ipRange->ip_range_to ?? '' }}" required>
                        <div class="form-text">Ending IP address of the range</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Devices outside this range will be marked with warning.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveIpRangeBtn">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this device assignment?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#assignmentsTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search..."
        },
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>'
    });

    // IP Range Form Submit using AJAX
    $('#saveIpRangeBtn').click(function(e) {
        e.preventDefault();
        
        var form = $('#ipRangeForm');
        var formData = form.serialize();
        var fromIp = $('#ip_range_from').val();
        var toIp = $('#ip_range_to').val();
        
        // Hide previous errors
        $('#ipRangeErrors').addClass('d-none');
        $('#errorList').empty();
        
        // Basic IP validation
        if (!isValidIp(fromIp) || !isValidIp(toIp)) {
            showModalError('Please enter valid IP addresses.');
            return false;
        }
        
        // Show loading
        var $saveBtn = $('#saveIpRangeBtn');
        var originalHtml = $saveBtn.html();
        $saveBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        $saveBtn.prop('disabled', true);
        
        // Get CSRF token
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        // AJAX request
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            success: function(response) {
                // Reset button
                $saveBtn.html(originalHtml);
                $saveBtn.prop('disabled', false);
                
                // Close modal
                $('#ipRangeModal').modal('hide');
                
                // Show success message
                showAlert('success', 'IP Range updated successfully!');
                
                // Reload page after delay
                setTimeout(function() {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                // Reset button
                $saveBtn.html(originalHtml);
                $saveBtn.prop('disabled', false);
                
                // Show validation errors
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    var errorHtml = '';
                    
                    for (var field in errors) {
                        errorHtml += '<li>' + errors[field][0] + '</li>';
                    }
                    
                    $('#errorList').html(errorHtml);
                    $('#ipRangeErrors').removeClass('d-none');
                } else {
                    showModalError('An error occurred while saving. Please try again.');
                }
            }
        });
    });

    // Function to show error in modal
    function showModalError(message) {
        $('#errorList').html('<li>' + message + '</li>');
        $('#ipRangeErrors').removeClass('d-none');
    }

    // Delete functionality
    var deleteUrl = '';
    var deleteId = '';
    
    $(document).on('click', '.delete-btn', function() {
        deleteUrl = $(this).data('url');
        deleteId = $(this).data('id');
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').click(function() {
        if (!deleteUrl) return;
        
        // Show loading state
        var $confirmBtn = $('#confirmDeleteBtn');
        var originalHtml = $confirmBtn.html();
        $confirmBtn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
        $confirmBtn.prop('disabled', true);
        
        // Get CSRF token
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        $.ajax({
            url: deleteUrl,
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            success: function(response) {
                $('#deleteModal').modal('hide');
                $confirmBtn.html(originalHtml);
                $confirmBtn.prop('disabled', false);
                
                if (response.success) {
                    // Show success message
                    showAlert('success', response.message);
                    
                    // Reload page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert('error', response.message || 'Delete failed');
                }
            },
            error: function(xhr, status, error) {
                $('#deleteModal').modal('hide');
                $confirmBtn.html(originalHtml);
                $confirmBtn.prop('disabled', false);
                
                var errorMsg = 'An error occurred while deleting.';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch (e) {
                    errorMsg = xhr.responseText || errorMsg;
                }
                
                showAlert('error', errorMsg);
            }
        });
    });

    // Function to show alerts
    function showAlert(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        var alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert" id="dynamicAlert">
                <i class="fas ${icon}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Remove existing dynamic alerts
        $('#dynamicAlert').remove();
        
        // Add new alert
        $('.container.mt-4.mb-3').prepend(alertHtml);
        
        // Auto remove alert after 5 seconds
        setTimeout(function() {
            $('#dynamicAlert').alert('close');
        }, 5000);
    }

    // Validate IP address
    function isValidIp(ip) {
        var pattern = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        return pattern.test(ip);
    }

    // Reset modal on hide
    $('#deleteModal').on('hidden.bs.modal', function () {
        deleteUrl = '';
        deleteId = '';
        $('#confirmDeleteBtn').html('Delete');
        $('#confirmDeleteBtn').prop('disabled', false);
    });
    
    // Reset IP range modal on hide
    $('#ipRangeModal').on('hidden.bs.modal', function () {
        $('#saveIpRangeBtn').html('<i class="fas fa-save"></i> Save Settings');
        $('#saveIpRangeBtn').prop('disabled', false);
        $('#ipRangeErrors').addClass('d-none');
        $('#errorList').empty();
    });
    
    // Reset IP range modal on show
    $('#ipRangeModal').on('show.bs.modal', function () {
        $('#ipRangeErrors').addClass('d-none');
        $('#errorList').empty();
    });
    
    // Real-time status update function
    function refreshDeviceStatus() {
        $.ajax({
            url: '{{ route("device-assignments.get-status") }}',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    response.devices.forEach(function(device) {
                        var row = $('#assignmentsTable').find('td:contains("' + device.device_id + '")').closest('tr');
                        
                        if (row.length) {
                            // Update status badge
                            var statusBadge = row.find('.status-badge');
                            if (device.status === 'online') {
                                statusBadge.html('<i class="fas fa-wifi"></i> Online');
                                statusBadge.removeClass('bg-secondary').addClass('bg-success');
                            } else {
                                statusBadge.html('<i class="fas fa-times-circle"></i> Offline');
                                statusBadge.removeClass('bg-success').addClass('bg-secondary');
                            }
                            
                            // Update last heartbeat
                            row.find('.last-heartbeat').text(device.last_heartbeat_formatted);
                        }
                    });
                }
            },
            error: function() {
                console.log('Error refreshing device status');
            }
        });
    }
    
    // Auto refresh every 30 seconds
    setInterval(refreshDeviceStatus, 30000);
});
</script>
@endsection

