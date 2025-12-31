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
                    <input type="text" name="name" id="name" class="form-control form-control-sm" required>
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

        <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary btn-sm">Add Path</button>
            <button type="button" id="clearForm" class="btn btn-outline-secondary btn-sm">Clear Form</button>
        </div>
    </form>

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Existing Paths</h3>
        <div>
            <span class="badge bg-info">{{ $paths->count() }} paths</span>

            <button type="button" id="refreshHierarchy" class="btn btn-sm btn-outline-info ms-2">
                <i class="fas fa-sitemap"></i> Refresh Locations
            </button>

            <button type="button" id="reloadTable" class="btn btn-sm btn-outline-warning ms-2">
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

@section('scripts')
<!-- Include jQuery UI for drag and drop -->
<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>

<!-- DataTables -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with proper configuration
    let dataTable = null;
    
    function initializeDataTable() {
        if ($.fn.DataTable.isDataTable('#pathsTable')) {
            dataTable.destroy();
            $('#pathsTable').removeClass('dataTable');
            $('#pathsTable_wrapper').remove();
        }
        
        dataTable = $('#pathsTable').DataTable({
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            pageLength: 10,
            autoWidth: false,
            responsive: true,
            scrollX: false,
            scrollCollapse: true,
            columnDefs: [
                { 
                    width: "10%", 
                    targets: 0,
                    className: "dt-center"
                },
                { 
                    width: "25%", 
                    targets: 1,
                    className: "dt-left"
                },
                { 
                    width: "50%", 
                    targets: 2,
                    className: "dt-left"
                },
                { 
                    width: "15%", 
                    targets: 3,
                    className: "dt-center",
                    orderable: false
                }
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
    }
    
    // Initialize on page load
    initializeDataTable();
    
    // Reload table button
    $('#reloadTable').click(function() {
        location.reload(); // Simple refresh
    });
    
    // Clear form button
    $('#clearForm').click(function() {
        $('#pathForm')[0].reset();
        $('#selectedDoors').empty();
        
        // Move all doors back to available
        const selectedDoors = $('#selectedDoors .draggable-door');
        selectedDoors.each(function() {
            const doorValue = $(this).data('value');
            const doorText = $(this).text().trim();
            
            // Add back to available if not already there
            if (!$('#availableDoors').find(`[data-value="${doorValue}"]`).length) {
                $('#availableDoors').append(
                    `<div class="list-group-item list-group-item-action draggable-door py-2" data-value="${doorValue}">
                        ${doorText}
                    </div>`
                );
            }
            $(this).remove();
        });
    });
    
    // Make selected doors sortable
    $("#selectedDoors").sortable({
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            updateHiddenInputs();
        }
    });
    
    // Add door button click
    $("#addDoor").click(function() {
        $("#availableDoors .list-group-item.selected").each(function() {
            const doorValue = $(this).data('value');
            const doorText = $(this).text().trim();
            
            // Move to selected
            $(this).remove();
            
            // Add to selected list
            const newItem = $(
                `<div class="list-group-item draggable-door py-2" data-value="${doorValue}">
                    ${doorText}
                    <input type="hidden" name="doors[]" value="${doorValue}">
                </div>`
            );
            $("#selectedDoors").append(newItem);
        });
        
        // Update sortable
        $("#selectedDoors").sortable("refresh");
        updateHiddenInputs();
    });
    
    // Remove door button click
    $("#removeDoor").click(function() {
        $("#selectedDoors .list-group-item.selected").each(function() {
            const doorValue = $(this).data('value');
            const doorText = $(this).text().trim();
            
            // Remove from selected
            $(this).remove();
            
            // Add back to available
            const newItem = $(
                `<div class="list-group-item list-group-item-action draggable-door py-2" data-value="${doorValue}">
                    ${doorText}
                </div>`
            );
            $("#availableDoors").append(newItem);
        });
        
        updateHiddenInputs();
    });
    
    // Select doors on click
    $(document).on('click', '.draggable-door', function() {
        $(this).toggleClass('selected');
    });
    
    // Update hidden inputs when order changes
    function updateHiddenInputs() {
        $("#selectedDoors input[name='doors[]']").remove();
        
        $("#selectedDoors .draggable-door").each(function() {
            const doorValue = $(this).data('value');
            $(this).append(`<input type="hidden" name="doors[]" value="${doorValue}">`);
        });
    }
    
    // Prevent form submit if no doors selected
    $("#pathForm").submit(function(e) {
        if ($("#selectedDoors .draggable-door").length === 0) {
            e.preventDefault();
            alert("Please select at least one door");
            return false;
        }
    });


$('#refreshHierarchy').on('click', function () {
    if (!confirm('This will fetch location hierarchy from Java system:\n• Main Locations → locations table\n• Sub-Locations → vendor_locations table\n\nDo you want to continue?')) {
        return;
    }

    const $btn = $(this);
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing Hierarchy...');

    $.ajax({
        url: "{{ route('vendor.locations.refresh.hierarchy') }}",
        type: "POST",
        data: {
            _token: "{{ csrf_token() }}"
        },
        success: function (response) {
            if (response.success) {
                const summary = response.summary;
                // CORRECTED: PHP में 'locations' और 'vendor_locations' हैं
                const mainLocations = summary.locations || { total: 0, inserted: 0, skipped: 0 };
                const subLocations = summary.vendor_locations || { total: 0, inserted: 0, skipped: 0 };
                
                const message = `✅ ${response.message}\n\n📊 Main Locations:\n• Received: ${mainLocations.total}\n• New Added: ${mainLocations.inserted}\n• Already Existed: ${mainLocations.skipped}\n\n📊 Sub-Locations:\n• Received: ${subLocations.total}\n• New Added: ${subLocations.inserted}\n• Already Existed: ${subLocations.skipped}\n\nPage will reload in 3 seconds...`;
                
                alert(message);
                
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                alert('❌ Error: ' + response.message);
                $btn.prop('disabled', false).html(originalHtml);
            }
        },
        error: function (xhr) {
            let errorMessage = 'Failed to refresh location hierarchy. ';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage += xhr.responseJSON.message;
            } else if (xhr.status === 0) {
                errorMessage += 'Network error. Please check your connection.';
            } else {
                errorMessage += 'Status: ' + xhr.status;
            }
            alert('❌ ' + errorMessage);
            console.error(xhr);
            
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
});

});
</script>
@endsection
