@extends('layout.main_layout')

@section('content')
<div class="container mt-4 mb-3">

    <h2>Edit Path</h2>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('paths.update', $path->id) }}" method="POST" id="pathForm">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="name" class="form-label">Path Name</label>
                    <input type="text" name="name" id="name" class="form-control form-control-sm" 
                           value="{{ $path->name }}" required>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Select and Order Doors</label>
            
            @php
                // Get current selected doors from path
                $selectedDoors = explode(',', $path->doors);
                $selectedDoors = array_map('trim', $selectedDoors);
                $selectedDoors = array_filter($selectedDoors); // Remove empty values
                
                // Get all vendor locations as available doors
                $allDoors = $vendorLocations; // From controller
                $availableDoors = array_diff($allDoors, $selectedDoors);
            @endphp
            
            <div class="row g-2">
                <div class="col-md-5">
                    <h6 class="fw-bold">Available Doors</h6>
                    <div id="availableDoors" class="list-group" style="max-height: 200px; overflow-y: auto;">
                        @foreach($availableDoors as $door)
                            @if(trim($door)) {{-- Check for non-empty doors --}}
                                <div class="list-group-item list-group-item-action draggable-door py-2" 
                                     data-value="{{ trim($door) }}">
                                    {{ trim($door) }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                
                <div class="col-md-2 text-center d-flex flex-column justify-content-center">
                    <button type="button" id="addDoor" class="btn btn-primary btn-sm mb-2">→ Add</button>
                    <button type="button" id="removeDoor" class="btn btn-secondary btn-sm">← Remove</button>
                </div>
                
                <div class="col-md-5">
                    <h6 class="fw-bold">Selected Doors (Drag to reorder)</h6>
                    <div id="selectedDoors" class="list-group sortable-list" style="max-height: 200px; overflow-y: auto;">
                        @foreach($selectedDoors as $door)
                            @if(trim($door)) {{-- Check for non-empty doors --}}
                                <div class="list-group-item draggable-door py-2" data-value="{{ trim($door) }}">
                                    {{ trim($door) }}
                                    <input type="hidden" name="doors[]" value="{{ trim($door) }}">
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            
            <small class="text-muted mt-2 d-block">Select doors from left, use buttons to move, and drag to reorder on right</small>
        </div>

        <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary btn-sm">Update Path</button>
            <a href="{{ route('paths.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>

</div>
@endsection

@section('styles')
<style>
.sortable-list {
    min-height: 100px;
}
.draggable-door {
    cursor: move;
    transition: all 0.2s;
}
.draggable-door.selected {
    background-color: #007bff !important;
    color: white !important;
    border-color: #007bff !important;
}
.ui-sortable-helper {
    background-color: #f8f9fa !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>
@endsection

@section('scripts')
<!-- Include jQuery UI for drag and drop -->
<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
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
    
    // Initialize hidden inputs on page load
    updateHiddenInputs();
});
</script>
@endsection

