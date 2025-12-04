@extends('layout.main_layout')

{{-- @section('title', 'Visitor Types') --}}

@section('content')
<div class="container mt-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Visitor Types</h4>
        <a href="{{ route('visitor-types.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Visitor Type
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="visitorTypesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visitor Type</th>
                        <th>Path</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visitorTypes as $type)
                    <tr id="row-{{ $type->id }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $type->visitor_type }}</td>
                        <td>{{ $type->path->name ?? '-' }}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="{{ route('visitor-types.edit', $type->id) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                
                                <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                        data-id="{{ $type->id }}"
                                        data-url="{{ route('visitor-types.destroy', $type->id) }}">
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

<!-- ✅ Bootstrap Modal for Delete Confirmation -->
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
                <p>Are you sure you want to delete this visitor type?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    console.log("✅ Document ready");
    
    // Variables to store delete data
    var deleteUrl = '';
    var deleteId = '';
    
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#visitorTypesTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            info: true,
            pageLength: 10,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            },
            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>'
        });
        console.log("✅ DataTable initialized");
    }

    // ✅ Handle delete button click - USING EVENT DELEGATION
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        deleteUrl = $(this).data('url');
        deleteId = $(this).data('id');
        
        console.log("🗑️ Delete button clicked - URL:", deleteUrl, "ID:", deleteId);
        
        if (!deleteUrl) {
            console.error("❌ No delete URL found");
            return;
        }
        
        // Show Bootstrap Modal
        $('#deleteModal').modal('show');
    });

    // ✅ Handle confirm delete button click
    $('#confirmDeleteBtn').click(function() {
        if (!deleteUrl || !deleteId) {
            console.error("❌ No delete URL or ID found");
            return;
        }
        
        console.log("✅ Confirming delete for URL:", deleteUrl, "ID:", deleteId);
        
        // Show loading state
        var $confirmBtn = $('#confirmDeleteBtn');
        var originalHtml = $confirmBtn.html();
        $confirmBtn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
        $confirmBtn.prop('disabled', true);
        
        // Get CSRF token
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        console.log("📤 Sending DELETE request to:", deleteUrl);
        
        // Send AJAX request
        $.ajax({
            url: deleteUrl,
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            data: {
                _token: csrfToken,
                _method: 'DELETE'
            },
            success: function(response) {
                console.log("✅ Delete successful:", response);
                
                // Hide modal
                $('#deleteModal').modal('hide');
                
                // Reset button
                $confirmBtn.html(originalHtml);
                $confirmBtn.prop('disabled', false);
                
                if (response.success) {
                    // Show success message
                    showAlert('success', response.message);
                    
                    // Remove row from table
                    $('#row-' + deleteId).fadeOut(500, function() {
                        $(this).remove();
                        
                        // If using DataTable, redraw
                        if ($.fn.DataTable && $('#visitorTypesTable').DataTable()) {
                            $('#visitorTypesTable').DataTable().row('#row-' + deleteId).remove().draw();
                        }
                    });
                } else {
                    showAlert('error', response.message || 'Delete failed');
                }
            },
            error: function(xhr, status, error) {
                console.error("❌ Delete error:");
                console.error("Status:", status);
                console.error("Error:", error);
                console.error("Response:", xhr.responseText);
                
                // Hide modal
                $('#deleteModal').modal('hide');
                
                // Reset button
                $confirmBtn.html(originalHtml);
                $confirmBtn.prop('disabled', false);
                
                var errorMsg = 'An error occurred while deleting.';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    } else if (response.error) {
                        errorMsg = response.error;
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
        $('#confirmDeleteBtn').html('<i class="fas fa-trash"></i> Delete');
        $('#confirmDeleteBtn').prop('disabled', false);
    });
    
    // Log for debugging
    console.log("🔍 Testing delete buttons:", $('.delete-btn').length, "buttons found");
    
    // Test button click
    $('.delete-btn').first().on('click', function() {
        console.log("🟢 First delete button click test successful");
    });
});
</script>
@endsection
