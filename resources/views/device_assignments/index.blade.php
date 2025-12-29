@extends('layout.main_layout')

@section('content')
<div class="container mt-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Device Assignments</h4>
        <a href="{{ route('device-assignments.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Assign New Device
        </a>
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

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="assignmentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Device ID</th>
                        <th>Device Name</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Assigned At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assignments as $assignment)
                    <tr id="row-{{ $assignment->id }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $assignment->device->device_id ?? 'N/A' }}</td>
                        <td>{{ $assignment->device->device_name ?? 'N/A' }}</td>
                        <td>{{ $assignment->location->name ?? 'N/A' }}</td>
                        <td>
                            @if($assignment->is_type == 'check_in')
                                <span class="badge bg-success">Check-In</span>
                            @elseif($assignment->is_type == 'check_out')
                                <span class="badge bg-warning text-dark">Check-Out</span>
                            @else
                                <span class="badge bg-secondary">{{ $assignment->is_type }}</span>
                            @endif
                        </td>
                        <td>{{ $assignment->created_at->format('d-m-Y H:i') }}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('device-assignments.edit', $assignment->id) }}" 
                                   class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                
                                <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                        data-id="{{ $assignment->id }}"
                                        data-url="{{ route('device-assignments.destroy', $assignment->id) }}">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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
                    
                    // Remove row from table
                    $('#row-' + deleteId).fadeOut(500, function() {
                        $(this).remove();
                        
                        // If using DataTable, redraw
                        if ($.fn.DataTable && $('#assignmentsTable').DataTable()) {
                            $('#assignmentsTable').DataTable().row('#row-' + deleteId).remove().draw();
                        }
                    });
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

    // Reset modal on hide
    $('#deleteModal').on('hidden.bs.modal', function () {
        deleteUrl = '';
        deleteId = '';
        $('#confirmDeleteBtn').html('Delete');
        $('#confirmDeleteBtn').prop('disabled', false);
    });
});
</script>
@endsection

