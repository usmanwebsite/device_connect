@extends('layout.main_layout')

@section('title', 'Sync Configuration')

@section('content')
<div class="container mt-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Cloud Sync Configuration</h4>
        <div>
            <a href="{{ route('sync-settings.edit', $setting->id) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button type="button" class="btn btn-danger delete-btn" 
                    data-id="{{ $setting->id }}"
                    data-url="{{ route('sync-settings.destroy', $setting->id) }}">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered">
                <tr>
                    <th width="200">IP / Host</th>
                    <td>{{ $setting->ip_host }}</td>
                </tr>
                <tr>
                    <th>Database Name</th>
                    <td>{{ $setting->db_name }}</td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td>{{ $setting->db_user }}</td>
                </tr>
                <tr>
                    <th>Password</th>
                    <td>•••••••• (encrypted)</td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the sync configuration?</p>
                <p class="text-danger"><small>You will need to create a new one to use sync.</small></p>
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
    let deleteUrl = '';
    let deleteId = '';

    $(document).on('click', '.delete-btn', function() {
        deleteUrl = $(this).data('url');
        deleteId = $(this).data('id');
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').click(function() {
        if (!deleteUrl) return;
        let btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...').prop('disabled', true);
        
        $.ajax({
            url: deleteUrl,
            type: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#deleteModal').modal('hide');
                if (response.success) {
                    window.location.href = "{{ route('sync-settings.create') }}";
                } else {
                    alert('Delete failed: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred.');
            },
            complete: function() {
                btn.html('Delete').prop('disabled', false);
            }
        });
    });
});
</script>
@endsection
