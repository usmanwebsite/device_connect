@extends('layout.main_layout')

@section('content')
<div class="container mt-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        <h4>Visitor Types</h4>
        <a href="{{ route('visitor-types.create') }}" class="btn btn-primary btn-sm">
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
            <table class="table table-bordered table-striped" id="visitorTypesTable" style="width:100%">
                <thead>
                    <tr>
                        <th width="10%">#</th>
                        <th width="40%">Visitor Type</th>
                        <th width="35%">Path</th>
                        <th width="15%">Actions</th>
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

@section('styles')
<style>
    /* Improved DataTable dropdown styling */
    .dataTables_length select {
        width: auto;
        min-width: 70px;
        display: inline-block;
        margin: 0 5px;
        padding: 0.25rem 1.5rem 0.25rem 0.5rem;
        background-position: right 0.5rem center;
        line-height: 1.5;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
    }
    .dataTables_wrapper .row:first-child {
        margin-bottom: 1rem;
    }
    .dataTables_filter input {
        margin-left: 0.5rem;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 0.25rem 0.5rem;
    }
    /* Consistent button sizing */
    .btn-sm {
        min-width: 80px;
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dataTables_length select {
            min-width: 60px;
        }
        .btn-sm {
            min-width: 70px;
        }
        .btn-group .btn-sm {
            min-width: 65px;
        }
    }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable with improved dom and styling
    $('#visitorTypesTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries",
            zeroRecords: "No records found",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            infoFiltered: "(filtered from _MAX_ total records)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        columnDefs: [
            { width: "10%", targets: 0, className: "dt-center" },
            { width: "40%", targets: 1 },
            { width: "35%", targets: 2 },
            { width: "15%", targets: 3, orderable: false, className: "dt-center" }
        ],
        autoWidth: false,
        scrollX: true,          // Horizontal scroll on mobile
        scrollCollapse: true
    });

    // Delete button handler
    var deleteUrl = '';
    var deleteId = '';

    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        deleteUrl = $(this).data('url');
        deleteId = $(this).data('id');
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').click(function() {
        if (!deleteUrl) return;
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...').prop('disabled', true);

        $.ajax({
            url: deleteUrl,
            type: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#deleteModal').modal('hide');
                if (response.success) {
                    // Remove row and redraw DataTable
                    var table = $('#visitorTypesTable').DataTable();
                    var row = table.row('#row-' + deleteId);
                    row.remove().draw();
                    // Show success message
                    showAlert('success', response.message);
                } else {
                    showAlert('error', response.message || 'Delete failed');
                }
            },
            error: function(xhr) {
                $('#deleteModal').modal('hide');
                var errorMsg = xhr.responseJSON?.message || 'An error occurred';
                showAlert('error', errorMsg);
            },
            complete: function() {
                $btn.html(originalHtml).prop('disabled', false);
            }
        });
    });

    $('#deleteModal').on('hidden.bs.modal', function () {
        deleteUrl = '';
        deleteId = '';
        $('#confirmDeleteBtn').html('<i class="fas fa-trash"></i> Delete').prop('disabled', false);
    });

    function showAlert(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        var alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" id="dynamicAlert">
                <i class="fas ${icon}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#dynamicAlert').remove();
        $('.container.mt-4.mb-3').prepend(alertHtml);
        setTimeout(() => $('#dynamicAlert').alert('close'), 5000);
    }
});
</script>
@endsection
