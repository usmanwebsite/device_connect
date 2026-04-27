@extends('layout.main_layout')

@section('content')
<div class="container mt-4 mb-3">

    <h2>Add New Path</h2>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('paths.store') }}" method="POST" id="pathForm">
        @csrf

        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="name" class="form-label">Path Name</label>
                    <input type="text" name="name" id="name" class="form-control form-control-sm" required style="width: 61%">
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Select and Order Doors</label>
            
            <div class="row g-2">
                <div class="col-md-5">
                    <h6 class="fw-bold">Available Doors</h6>
                    <div id="availableDoors" class="list-group" style="max-height: 200px; overflow-y: auto;">
                        @foreach($vendorLocations as $location)
                            <div class="list-group-item list-group-item-action draggable-door py-2" data-value="{{ $location }}">
                                {{ $location }}
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <div class="col-md-2 text-center d-flex flex-column justify-content-center">
                    <button type="button" id="addDoor" class="btn btn-primary btn-sm mb-2">→ Add</button>
                    <button type="button" id="removeDoor" class="btn btn-secondary btn-sm">← Remove</button>
                </div>
                
                <div class="col-md-5">
                    <h6 class="fw-bold">Selected Doors</h6>
                    <div id="selectedDoors" class="list-group sortable-list" style="max-height: 200px; overflow-y: auto;">
                        <!-- Initially empty -->
                    </div>
                </div>
            </div>
            
            <small class="text-muted mt-2 d-block">Select doors from left, use buttons to move, and drag to reorder on right</small>
        </div>

        <div class="d-flex justify-content-between gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Add Path</button>
            <button type="button" id="clearForm" class="btn btn-outline-secondary btn-sm">Clear Form</button>
        </div>
    </form>

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <h3 class="mb-2 mb-sm-0">Existing Paths</h3>
        <div class="d-flex gap-2">
            <span class="badge bg-info align-self-center">{{ $paths->count() }} paths</span>
            <button type="button" id="refreshHierarchy" class="btn btn-sm btn-outline-info">
                <i class="fas fa-sitemap"></i> Refresh Locations
            </button>
            <button type="button" id="reloadTable" class="btn btn-sm btn-outline-warning">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    
    @if($paths->isEmpty())
        <div class="alert alert-warning py-2">
            <i class="fas fa-info-circle"></i> No paths found. Please add a new path.
        </div>
    @else
        <div class="table-responsive">
            <table id="pathsTable" class="table table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th width="10%">ID</th>
                        <th width="25%">Path Name</th>
                        <th width="50%">Doors</th>
                        <th width="15%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paths as $path)
                        <tr>
                            <td>{{ $path->id }}</td>
                            <td>{{ $path->name }}</td>
                            <td>
                                <div class="doors-list">
                                    @php
                                        $doorsArray = explode(',', $path->doors);
                                    @endphp
                                    @foreach($doorsArray as $door)
                                        <span class="badge bg-primary me-1 mb-1">{{ $door }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('paths.edit', $path->id) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@endsection

@section('styles')
<style>
    /* Consistent button sizing */
    .btn-sm, .btn-group-sm > .btn {
        min-width: 100px;
        min-height: 34px;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    /* Special case for transfer buttons to keep them compact */
    #addDoor, #removeDoor {
        min-width: 85px;
        width: auto !important;
    }
    /* DataTables length menu styling */
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
    /* Door selection visual feedback */
    .draggable-door {
        cursor: pointer;
        transition: all 0.2s;
    }
    .draggable-door.selected {
        background-color: #0d6efd !important;
        color: white !important;
        border-color: #0d6efd;
    }
    #selectedDoors .draggable-door {
        cursor: grab;
    }
    #selectedDoors .draggable-door:active {
        cursor: grabbing;
    }
    /* Sortable placeholder style */
    .ui-state-highlight {
        background-color: #fff3cd;
        border: 2px dashed #ffc107;
        height: 38px;
        margin: 4px 0;
    }
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .btn-sm {
            min-width: 80px;
        }
        #addDoor, #removeDoor {
            min-width: 70px;
        }
        .dataTables_length select {
            min-width: 60px;
        }
    }
</style>
@endsection

@section('scripts')
<!-- Include jQuery UI for drag and drop -->
<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>

<!-- DataTables -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with improved length menu visibility
    let dataTable = $('#pathsTable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        pageLength: 10,
        autoWidth: false,
        responsive: true,
        columnDefs: [
            { width: "10%", targets: 0, className: "dt-center" },
            { width: "25%", targets: 1 },
            { width: "50%", targets: 2 },
            { width: "15%", targets: 3, orderable: false, className: "dt-center" }
        ],
        order: [[0, 'desc']],
        language: {
            lengthMenu: "Show _MENU_ entries",
            zeroRecords: "No records found",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            infoFiltered: "(filtered from _MAX_ total records)",
            search: "Search:",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });

    // Refresh button reloads page
    $('#reloadTable').click(() => location.reload());

    // Clear form button
    $('#clearForm').click(function() {
        $('#pathForm')[0].reset();
        $('#selectedDoors').empty();
        // Restore all doors to available list (avoid duplicates)
        const selectedItems = $('#selectedDoors .draggable-door');
        selectedItems.each(function() {
            const val = $(this).data('value');
            const txt = $(this).text().trim();
            if (!$('#availableDoors').find(`[data-value="${val}"]`).length) {
                $('#availableDoors').append(`<div class="list-group-item list-group-item-action draggable-door py-2" data-value="${val}">${txt}</div>`);
            }
            $(this).remove();
        });
        updateHiddenInputs();
    });

    // Make selected doors sortable
    $("#selectedDoors").sortable({
        placeholder: "ui-state-highlight",
        update: function() { updateHiddenInputs(); }
    });

    // Add door(s) – move selected from left to right
    $("#addDoor").click(function() {
        $("#availableDoors .draggable-door.selected").each(function() {
            const val = $(this).data('value');
            const txt = $(this).text().trim();
            $(this).remove();
            const newItem = $(`<div class="list-group-item draggable-door py-2" data-value="${val}">${txt}</div>`);
            $("#selectedDoors").append(newItem);
        });
        $("#selectedDoors").sortable("refresh");
        updateHiddenInputs();
    });

    // Remove door(s) – move selected from right to left
    $("#removeDoor").click(function() {
        $("#selectedDoors .draggable-door.selected").each(function() {
            const val = $(this).data('value');
            const txt = $(this).text().trim();
            $(this).remove();
            if (!$('#availableDoors').find(`[data-value="${val}"]`).length) {
                $('#availableDoors').append(`<div class="list-group-item list-group-item-action draggable-door py-2" data-value="${val}">${txt}</div>`);
            }
        });
        updateHiddenInputs();
    });

    // Toggle 'selected' class when clicking a door
    $(document).on('click', '.draggable-door', function(e) {
        // Don't toggle when dragging
        if (!$(this).hasClass('ui-sortable-helper')) {
            $(this).toggleClass('selected');
        }
    });

    // Update hidden inputs after order change
    function updateHiddenInputs() {
        $("#selectedDoors input[name='doors[]']").remove();
        $("#selectedDoors .draggable-door").each(function() {
            const val = $(this).data('value');
            $(this).append(`<input type="hidden" name="doors[]" value="${val}">`);
        });
    }

    // Form validation: at least one door selected
    $("#pathForm").submit(function(e) {
        if ($("#selectedDoors .draggable-door").length === 0) {
            e.preventDefault();
            alert("Please select at least one door.");
            return false;
        }
    });

    // Refresh hierarchy from Java API
    $('#refreshHierarchy').on('click', function () {
        if (!confirm('Fetch location hierarchy from Java system?\nMain Locations → locations table\nSub-Locations → vendor_locations table\n\nContinue?')) return;
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing...');
        $.ajax({
            url: "{{ route('vendor.locations.refresh.hierarchy') }}",
            type: "POST",
            data: { _token: "{{ csrf_token() }}" },
            success: function (resp) {
                if (resp.success) {
                    const main = resp.summary.locations || { total: 0, inserted: 0, skipped: 0 };
                    const sub = resp.summary.vendor_locations || { total: 0, inserted: 0, skipped: 0 };
                    alert(`✅ Hierarchy refreshed!\n📊 Main Locations: ${main.inserted} new, ${main.skipped} existing\n📊 Sub-Locations: ${sub.inserted} new, ${sub.skipped} existing\nReloading page...`);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert('❌ Error: ' + resp.message);
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr) {
                alert('❌ Failed: ' + (xhr.responseJSON?.message || 'Network error'));
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
});
</script>
@endsection

