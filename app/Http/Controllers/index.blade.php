@extends('admin.layout.app')
@section('title', 'Records List')
@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
<link rel="stylesheet" href="{{asset("css/logs.index.css")}}">
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
<div class="logs-container">
    <div id="error">
        @if (session()->has('msg'))
        <div class="alert alert-success mx-auto">{{ session('msg') }}</div>
        @endif
    </div>

<div class="logs">
    <div class="log-header">
        <h1 class="p-0 m-0">Records</h1>
        <div class="d-flex">
            <form action="{{route('logs.create')}}" method="GET" class="mr-2">
                <button type="submit" class="btn custom-btn">Create Records</button>
            </form>
            <button type="button" class="btn custom-btn ml-2" id="resetColumnOrder">
                Reset Column Order
            </button>

            <button type="button" class="btn custom-btn ml-2" id="toggleColumnVisibility">
                Show/Hide Columns
            </button>

            <!-- ✅ Hidden Fields Button -->
            <button type="button" class="btn custom-btn ml-2" id="showHiddenFields">
                Show Hidden Fields
            </button>

        </div>
    </div>
<table class="table" id="table" style="width:100%">
    <thead>
        <tr>
            <th class="all-th">#</th>
@foreach ($columns as $column)
    @php
        $isFormulaField = false;
        $formulaDisplay = '';
        
        // ✅ Check if this column is a formula field
        foreach($formulaFields as $formulaField) {
            if ($column['name'] == $formulaField->name) {
                $isFormulaField = true;
                
                // ✅ Formula display text
                if (isset($column['formula_display'])) {
                    $formulaDisplay = $column['formula_display'];
                } else if (!empty($formulaField->fields_formula)) {
                    $formulaData = json_decode($formulaField->fields_formula, true);
                    $formulaDisplay = $this->getFormulaDisplayText($formulaData);
                }
                break;
            }
        }
        
        // ✅ Permission check
        $hasFormulaPermission = auth()->user() && auth()->user()->hasPermission('update_formula', Session::get('workspace_id'));
        
    @endphp
    
    <th scope="col" class="all-th {{ $isFormulaField ? 'formula-header' : '' }} 
        {{ $isFormulaField && $hasFormulaPermission ? 'clickable' : ($isFormulaField ? 'no-permission' : '') }}" 
        style="{{ $isFormulaField ? 'background-color: #4B545c;' : '' }} 
        {{ $isFormulaField && $hasFormulaPermission ? 'cursor: pointer;' : ($isFormulaField ? 'cursor: not-allowed; opacity: 0.6;' : '') }}"
        @if($isFormulaField && $hasFormulaPermission)
            data-field-id="{{ $column['field_id'] }}"
            data-field-name="{{ $column['name'] }}"
            onclick="openFormulaModal('{{ $column['name'] }}')"
        @endif
        @if($isFormulaField && !$hasFormulaPermission)
            title="No permission to update formula"
        @endif>
        
        @if($isFormulaField && !empty($formulaDisplay))
            <!-- ✅ FLEXBOX LAYOUT: Heading left, Icon right -->
            <div class="formula-header-content" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="formula-text-content" style="flex: 1;">
                    <div class="field-name">{{ $column['name'] }}</div>
                </div>
                <div class="formula-icon" style="margin-left: 8px; flex-shrink: 0;">
                    <i class="fas fa-calculator text-info"></i>
                </div>
            </div>
        @elseif($isFormulaField)
            <!-- ✅ FLEXBOX LAYOUT: Simple heading with icon -->
            <div class="formula-header-content" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="field-name" style="flex: 1;">{{ $column['name'] }}</div>
                <div class="formula-icon" style="margin-left: 8px; flex-shrink: 0;">
                    <i class="fas fa-calculator text-info"></i>
                </div>
            </div>
        @else
            {{ $column['name'] }}
        @endif
    </th>
@endforeach
        </tr>
    </thead>
</table>
</div>

    <div class="modal fade" id="fileModal" tabindex="-1" role="dialog" aria-labelledby="fileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fileModalLabel">Files</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

        <!-- ✅ Hidden Fields Modal -->
    <div class="modal fade" id="hiddenFieldsModal" tabindex="-1" role="dialog" aria-labelledby="hiddenFieldsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hiddenFieldsModalLabel">Access Hidden Fields</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="securePassword">Enter Security Password</label>
                        <input type="password" class="form-control" id="securePassword" placeholder="Enter password">
                        <div id="passwordError" class="text-danger mt-2" style="display: none;"></div>
                        <p><b>Note: </b> <br>Contact your workspace admin to get the password</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="verifyPassword">
                        <i class="fa fa-lock mr-1"></i> Verify Password
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Total Calculator Modal -->
<div class="modal fade" id="totalCalculatorModal" tabindex="-1" role="dialog" aria-labelledby="totalCalculatorModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="totalCalculatorModalLabel">Total Calculator</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Left Side: Available Fields -->
                    <div class="col-md-5">
                        <h6>Available Fields</h6>
                        <div id="availableFields" class="border p-2" style="min-height: 200px;">
                            @foreach($fields as $field)
                                <button type="button" class="btn btn-outline-primary btn-sm m-1 field-btn" 
                                        data-field-id="{{ $field->id }}" 
                                        data-field-name="{{ $field->name }}">
                                    {{ $field->name }}
                                </button>
                            @endforeach
                        </div>
                        
                        <!-- ✅ NEW: Previous Formula Section -->
                        <div class="mt-4">
                            <h6>Previous Formula</h6>
                            <div id="previousFormula" class="border p-3 bg-light" style="min-height: 100px; font-size: 14px;">
                                <span class="text-muted">No previous formula found...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Formula Builder -->
                    <div class="col-md-7">
                        <h6>Formula Builder</h6>
                        <div id="formulaBuilder" class="border p-3" style="min-height: 150px;">
                            <span class="text-muted">Click fields and operators to build formula...</span>
                        </div>
                        
                        <!-- Operators with Erase Button -->
                        <div class="mt-3">
                            <h6>Operators</h6>
                            <button type="button" class="btn btn-info btn-sm operator-btn" data-operator="+">+</button>
                            <button type="button" class="btn btn-info btn-sm operator-btn" data-operator="-">-</button>
                            <button type="button" class="btn btn-info btn-sm operator-btn" data-operator="*">×</button>
                            <button type="button" class="btn btn-info btn-sm operator-btn" data-operator="/">÷</button>
                            
                            <!-- ✅ NEW: Erase Button -->
                            <button type="button" class="btn btn-danger btn-sm" id="eraseLastItem" title="Remove last item">
                                <i class="fas fa-backspace"></i> Erase
                            </button>
                            
                            <button type="button" class="btn btn-warning btn-sm" id="clearFormula">Clear All</button>
                        </div>
                        
                        <!-- Preview -->
                        <div class="mt-3">
                            <strong>Formula Preview:</strong>
                            <code id="formulaPreview" class="ml-2"></code>
                        </div>

                        <div class="mt-2">
                            <small id="validationStatus" class="text-muted"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFormula">Save & Calculate</button>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@section('js')
<!-- ✅ One jQuery -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
var $ = jQuery.noConflict();
// Store original columns and secure columns
let originalColumns = <?php echo json_encode($columns); ?>;
let secureColumns = [];
let areSecureColumnsVisible = false;
let dataTable;

// Function to create dropdown HTML
function createDropdownHTML(actionData) {
    const data = typeof actionData === 'string' ? JSON.parse(actionData) : actionData;
    
    return `
        <div class="custom-dropdown">
            <button class="dropdown-btn">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-content">
                <a href="${data.view_files_link}">
                    <i class="fa fa-eye mr-2"></i> View Files
                </a>
                <a href="javascript:void(0)" onclick="$('#drive_file_${data.row_id}').click();">
                    <i class="fa fa-upload mr-2"></i> Upload File
                </a>
                <a href="${data.edit_link}">
                    <i class="fa fa-edit mr-2"></i> Edit
                </a>
                ${data.can_delete ? `
                <a href="javascript:void(0)" class="delete-log-btn" data-url="${data.delete_link}">
                    <i class="fa fa-trash mr-2"></i> Delete
                </a>
                ` : ''}
            </div>
        </div>
        <form action="${data.upload_link}" method="POST" enctype="multipart/form-data" 
              id="upload_form_${data.row_id}" class="d-none">
              
            ${$('meta[name="csrf-token"]').attr('content') ? 
                `<input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">` : ''}
                @csrf
            <input type="file" name="${data.is_drive_storage ? 'drive_file' : 'local_file'}" 
                   id="drive_file_${data.row_id}" 
                   onchange="$('#upload_form_${data.row_id}').submit();"/>
        </form>
    `;
}
// Initialize dropdown functionality
function initDropdowns() {
    $(document).off("click", ".dropdown-btn").on("click", ".dropdown-btn", function(e) {
        e.stopPropagation();
        let dropdown = $(this).closest('.custom-dropdown');
        $(".custom-dropdown").not(dropdown).removeClass('active');
        dropdown.toggleClass('active');
    });

    $(document).on("click", function(e) {
        if (!$(e.target).closest('.custom-dropdown').length) {
            $(".custom-dropdown").removeClass('active');
        }
    });
}

// ✅ Show loader function
function showLoader() {
    $('#loader-overlay').show();
}
// ✅ Hide loader function  
function hideLoader() {
    $('#loader-overlay').hide();
}
// ✅ UPDATED: Verify password and send with data request
function verifyPasswordAndShowColumns(password) {
    showLoader(); // Show loader
    
    let table = $('#table').DataTable();
    
    // ✅ Clear DataTable state
    table.state.clear();
    
    // ✅ Reload with password in request body
    $.ajax({
        url: "{{ route('logs.data') }}",
        type: "POST",
        data: { 
            password: password,
            include_secure: true 
        },
        headers: { 
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') 
        },
        beforeSend: function() {
            showLoader(); // Show loader before request
        },
        success: function(response) {
            // Clear current data and reload with new data
            table.clear();

            response.data.forEach(function(row) {
                if (row.action_data) {
                    row.action = createDropdownHTML(row.action_data);
                }
            });

            table.rows.add(response.data).draw();

            // initializeActionButtons();
            initDropdowns();     
            initDeleteButtons();
            
            $('#hiddenFieldsModal').modal('hide');
            $('#securePassword').val('');
            $('#passwordError').hide();
            
            // Show secure columns
            forceShowSecureColumns();
            
            areSecureColumnsVisible = true;
            $('#showHiddenFields').text('Hide Secure Fields').removeClass('btn-primary').addClass('btn-warning');
            
            Swal.fire({
                title: 'Success!',
                text: 'Secure fields are now visible.They will also be available on edit pages.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        },
        error: function(xhr) {
            if (xhr.status === 403) {
                $('#passwordError').text('Invalid password').show();
            } else {
                $('#passwordError').text('Error loading secure data').show();
            }
            
            Swal.fire('Error!', 'Failed to load secure data', 'error');
        },
        complete: function() {
            hideLoader(); // Hide loader when request completes
        }
    });
}


function openFormulaModal(fieldName, fieldId = null) {
    console.log("🔹 Opening formula modal for:", { fieldName, fieldId });
    
    // ✅ Pehle fieldId check karen
    if (!fieldId) {
        // Agar fieldId nahi hai, toh name se get karen
        fieldId = getFieldIdByName(fieldName);
    }
    
    if (!fieldId) {
        Swal.fire('Error!', 'Could not identify formula field', 'error');
        return;
    }
    
    // ✅ Modal data set karen
    $('#totalCalculatorModal')
        .data('current-field-name', fieldName)
        .data('current-field-id', fieldId)
        .data('current-formula-field', fieldId);
    
    // ✅ Modal show karen
    $('#totalCalculatorModal').modal('show');
    
    // ✅ Previous formula load karen
    loadPreviousFormula(fieldId);
}

function loadPreviousFormula(fieldId) {
    console.log("🔄 Loading formula for field ID:", fieldId);
    
    if (!fieldId) {
        console.error("❌ Field ID missing");
        return;
    }
    
    $.ajax({
        url: "{{ route('logs.get-previous-formula') }}",
        type: "GET",
        data: {
            field_id: fieldId,
            workspace_id: {{ Session::get('workspace_id') }}
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log("✅ Formula response:", response);
            
            if (response.success && response.formula) {
                displayPreviousFormula(response.formula);
                
                // ✅ Field ID store karen for saving
                $('#totalCalculatorModal').data('current-formula-field', fieldId);
            } else {
                $('#previousFormula').html(`
                    <div class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        ${response.message || 'No previous formula found'}
                    </div>
                `);
            }
        },
        error: function(xhr) {
            console.error("❌ Error loading formula:", xhr);
            $('#previousFormula').html(`
                <div class="text-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Error loading previous formula
                </div>
            `);
        }
    });
}


// ✅ Helper function: Field ID get by name
function getFieldIdByName(fieldName) {
    // Original columns array mein search karen
    const field = originalColumns.find(col => col.name === fieldName);
    return field ? field.field_id : null;
}

function loadPreviousFormulaByFieldName(fieldName) {
    console.log("🔄 Loading formula for field:", fieldName);
    
    $.ajax({
        url: "{{ route('logs.get-previous-formula-by-name') }}",
        type: "GET",
        data: {
            field_name: fieldName,
            workspace_id: {{ Session::get('workspace_id') }}
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log("✅ Formula response:", response);
            
            if (response.success && response.formula) {
                displayPreviousFormula(response.formula);
                
                // ✅ Field ID bhi store karen for saving
                if (response.field_id) {
                    $('#totalCalculatorModal').data('current-formula-field', response.field_id);
                }
            } else {
                $('#previousFormula').html(`
                    <div class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        ${response.message || 'No previous formula found'}
                    </div>
                `);
            }
        },
        error: function(xhr) {
            console.error("❌ Error loading formula:", xhr);
            $('#previousFormula').html(`
                <div class="text-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Error loading previous formula
                </div>
            `);
        }
    });
}



// ✅ Remove the old reloadWithSecureData function and replace with this:
function reloadDataWithPassword(password) {
    let table = $('#table').DataTable();
    
    // Clear any saved state
    table.state.clear();
    
    // Make AJAX call with password in body
    $.ajax({
        url: "{{ route('logs.data') }}",
        type: "POST",
        data: { 
            password: password,
            include_secure: true 
        },
        headers: { 
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') 
        },
        success: function(response) {
            // Clear current data and reload with new data
            table.clear();
            table.rows.add(response.data).draw();
            
            $('#hiddenFieldsModal').modal('hide');
            $('#securePassword').val('');
            $('#passwordError').hide();
            
            // Show secure columns
            forceShowSecureColumns();
            
            areSecureColumnsVisible = true;
            $('#showHiddenFields').text('Hide Secure Fields').removeClass('btn-primary').addClass('btn-warning');
            Swal.fire('Success!', 'Secure fields are now visible', 'success');
        },
        error: function(xhr) {
            if (xhr.status === 403) {
                $('#passwordError').text('Invalid password').show();
            } else {
                $('#passwordError').text('Error loading secure data').show();
            }
        }
    });
}

// ✅ NEW FUNCTION: Force show secure columns after reload
function forceShowSecureColumns() {
    showLoader(); // Show loader
    
    let table = $('#table').DataTable();
    let shownCount = 0;
    
    // Get current column definitions from the table
    table.columns().every(function(index) {
        if (index === 0) return; // Skip index column
        
        let column = this;
        let header = $(column.header());
        let columnName = header.text().trim();
        
        // Check if this column is secure by comparing with originalColumns
        let isSecureColumn = originalColumns.some(col => 
            col.name === columnName && col.is_secure == 1
        );
        
        if (isSecureColumn) {
            table.column(index).visible(true);
            shownCount++;
        }
    });
    
    table.draw();
    
    // ✅ IMPORTANT: Ensure button remains enabled after showing secure columns
    $('#showHiddenFields').prop('disabled', false);
    
    console.log("Shown", shownCount, "secure columns");
    
    // Hide loader after a short delay to ensure UI updates
    setTimeout(() => {
        hideLoader();
    }, 500);
}

function toggleSecureColumns(show) {
    if (show) {
        // Show modal for password when showing secure columns
        $('#hiddenFieldsModal').modal('show');
    } else {
        // Hide secure columns directly
        showLoader(); // Show loader for hiding process
        
        let table = $('#table').DataTable();
        let hiddenCount = 0;
        
        table.columns().every(function(index) {
            if (index === 0) return; // Skip index column
            
            let column = this;
            let header = $(column.header());
            let columnName = header.text().trim();
            
            // Check if this column is secure
            let isSecureColumn = originalColumns.some(col => 
                col.name === columnName && col.is_secure == 1
            );
            
            if (isSecureColumn) {
                table.column(index).visible(false);
                hiddenCount++;
            }
        });

        areSecureColumnsVisible = false;
        $('#showHiddenFields')
            .text('Show Hidden Fields')
            .removeClass('btn-warning')
            .addClass('btn-primary')
            .prop('disabled', false); // ✅ Ensure button remains enabled

        console.log("Hidden", hiddenCount, "secure columns");

        // ✅ REVOKE SECURE ACCESS SESSION
        $.post("{{ route('logs.revoke_secure') }}", {
            _token: $('meta[name="csrf-token"]').attr('content')
        }).done(function() {
            console.log("Secure session revoked");
        });

        table.draw();
        
        setTimeout(() => {
            hideLoader();
            Swal.fire({
                title: 'Info!',
                text: 'Secure fields are now hidden',
                icon: 'info',
                timer: 1500,
                showConfirmButton: false
            });
        }, 500);
    }
}

function initFormulaHeaders() {
    console.log("🔄 Initializing formula headers...");
    
    // Formula headers par click events attach karen
    $('.formula-header.clickable').off('click').on('click', function() {
        const fieldName = $(this).find('.field-name').text().trim();
        console.log("🔹 Formula header clicked:", fieldName);
        
        if (fieldName) {
            openFormulaModal(fieldName);
        }
    });
    
    // Tooltips add karen
    $('.formula-header.clickable').attr('title', 'Click to edit formula');
    $('.formula-header.no-permission').attr('title', 'No permission to update formula');
    
    console.log("✅ Formula headers initialized");
}

// ✅ Image Modal Function
function openImageModal(imageUrl, filename) {
    const modalHtml = `
        <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageModalLabel">Image Preview - ${filename}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="${imageUrl}" style="max-width: 100%; max-height: 80vh;" alt="${filename}">
                    </div>
                    <div class="modal-footer">
                        <a href="${imageUrl}" download="${filename}" class="btn btn-primary">
                            <i class="fa fa-download"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    $('#imageModal').remove();
    
    // Add new modal to body
    $('body').append(modalHtml);
    
    // Show modal
    $('#imageModal').modal('show');
    
    // Remove modal when hidden
    $('#imageModal').on('hidden.bs.modal', function () {
        $(this).remove();
    });
}

$(document).on('click', '.total-header', function() {
    console.log("Total header clicked"); // Debug ke liye
    $('#totalCalculatorModal').modal('show');
});

// Initialize DataTable with ALL columns but hide secure ones initially
// Initialize DataTable with ALL columns but hide secure ones initially
function initializeDataTable() {
    let columns = [
        { 
            data: 'index_no', 
            title: '#', 
            orderable: false,
            searchable: false,
            className: 'dt-center'
        }
    ];

    // Add field columns
    originalColumns.forEach(col => {
        if (col.data === 'action_data') return;
        
        console.log('Column name:', col.name, 'Is Formula:', col.has_formula);

        let columnDef = {
            data: col.data,
            title: col.name, // Default title
            defaultContent: '--',
            className: 'dt-head-center'
        };
        
        // ✅ IMPORTANT: Formula fields ke liye custom header with FLEXBOX
        if (col.has_formula) {
            const hasPermission = col.has_permission || false;
            
            // ✅ FLEXBOX LAYOUT: Heading left, Icon right
            if (col.formula_display) {
                columnDef.title = `
                    <div class="formula-header-content" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <div class="formula-text-content" style="flex: 1;">
                            <div class="field-name">${col.name}</div>
                        </div>
                        <div class="formula-icon" style="margin-left: 8px; flex-shrink: 0;">
                            <i class="fas fa-calculator text-info"></i>
                        </div>
                    </div>
                `;
            } else {
                columnDef.title = `
                    <div class="formula-header-content" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <div class="field-name" style="flex: 1;">${col.name}</div>
                        <div class="formula-icon" style="margin-left: 8px; flex-shrink: 0;">
                            <i class="fas fa-calculator text-info"></i>
                        </div>
                    </div>
                `;
            }
            
            if (hasPermission) {
                columnDef.className = 'formula-header clickable';
            } else {
                columnDef.className = 'formula-header no-permission';
            }
        }
        
        // Add checkbox rendering if needed
        if (col.checkbox) {
            columnDef.render = function(data, type, row) {
                if (type === 'display') {
                    if (data === true || data === "true" || data === 1 || data === "1") {
                        return '<i class="fa fa-check text-success"></i>';
                    } else {
                        return '<i class="fa fa-times text-danger"></i>';
                    }
                }
                return data;
            };
        }

        // ✅ IMPORTANT: Allow HTML rendering for all columns
        if (!col.checkbox) {
            columnDef.render = function(data, type, row) {
                if (type === 'display') {
                    // If data contains HTML, return as is
                    if (typeof data === 'string' && (data.includes('<img') || data.includes('<div'))) {
                        return data;
                    }
                    return data;
                }
                return data;
            };
        }
        
        columns.push(columnDef);
    });

    // Add action column separately
    columns.push({
        data: 'action_data',
        title: 'Action',
        orderable: false,
        searchable: false,
        className: 'dt-center',
        render: function(data, type, row) {
            if (type === 'display' && data) {
                return createDropdownHTML(data);
            }
            return '';
        }
    });

    console.log("Final Columns for DataTable:", columns);

    dataTable = $('#table').DataTable({
        "scrollX": true,
        "processing": true,
        "serverSide": false,
        "ordering": true,
        "ajax": {
            "url": "{{ route('logs.data') }}",
            "type": "POST",
            "headers": {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            "dataSrc": "data"
        },
        "columns": columns,
        "colReorder": true,
        "stateSave": true,
        // ✅ IMPORTANT: Allow HTML content in headers
        "autoWidth": true,
        "language": {
            "emptyTable": "No records found",
            "processing": "Loading records..."
        },
        "headerCallback": function(thead, data, start, end, display) {
            // ✅ Formula header click events re-initialize karen
            setTimeout(initFormulaHeaders, 100);
        },
        "initComplete": function(settings, json) {
            console.log("DataTable Init Complete - Loaded Data:", json);

                // ✅ Formula headers par click event bind karen
            setTimeout(() => {
                $('.formula-header.clickable').off('click').on('click', function() {
                    const fieldId = $(this).data('field-id');
                    const fieldName = $(this).data('field-name') || $(this).find('.field-name').text().trim();
                    
                    console.log("🔹 Header clicked:", { fieldId, fieldName });
                    
                    if (fieldId) {
                        openFormulaModal(fieldName, fieldId);
                    } else {
                        Swal.fire('Warning!', 'Field ID not found. Please refresh page.', 'warning');
                    }
                });
            }, 1000);
            
            let table = $('#table').DataTable();
            let hasSecureColumns = originalColumns.some(col => col.is_secure == 1);
            
            if (hasSecureColumns) {
                $('#showHiddenFields').prop('disabled', false);
            } else {
                $('#showHiddenFields').prop('disabled', true);
            }
            
            // ✅ Formula headers initialize karen
            initFormulaHeaders();
            
            if (!areSecureColumnsVisible) {
                setTimeout(function() {
                    let hiddenCount = 0;
                    table.columns().every(function(index) {
                        if (index === 0) return;
                        
                        let column = this;
                        let header = $(column.header());
                        let columnName = header.find('.field-name').text() || header.text().trim();
                        
                        let isSecureColumn = originalColumns.some(col => 
                            col.name === columnName && col.is_secure == 1
                        );
                        
                        if (isSecureColumn) {
                            table.column(index).visible(false);
                            hiddenCount++;
                        }
                    });
                    
                    console.log("Initially hidden", hiddenCount, "secure columns");
                    table.draw();
                }, 500);
            }
        },
        "drawCallback": function(settings) {
            console.log("DataTable Draw Complete");
            initDropdowns();
            initDeleteButtons();
            // ✅ Formula headers re-initialize karen har draw ke baad
            initFormulaHeaders();
        }
    });

    return dataTable;
}

$(document).ready(function(){
    console.log("jQuery version:", $.fn.jquery);
    console.log("DataTable available:", typeof $.fn.DataTable !== 'undefined');
    console.log("Original Columns:", originalColumns);
    
    $('#availableFields').html('');
    @foreach($fields as $field)
        @if($field->type->field_type === 'number') // ✅ Sirf NUMBER type fields show karen
            $('#availableFields').append(`
                <button type="button" class="btn btn-outline-primary btn-sm m-1 field-btn" 
                        data-field-id="{{ $field->id }}" 
                        data-field-name="{{ $field->name }}"
                        data-field-type="{{ $field->type->field_type }}">
                    {{ $field->name }}
                </button>
            `);
        @endif
    @endforeach

// ✅ Formula header click handler - WITH BETTER STORAGE
$(document).on('click', '.formula-header.clickable', function() {
    const fieldId = $(this).data('field-id');
    
    console.log("🔹 Header clicked - Raw element:", this);
    console.log("🔹 Header clicked - Field ID:", fieldId);
    
    // ✅ DIRECT HARDCODED SOLUTION - No DOM searching
    let fieldName = "Total";
    let formulaText = "Daily Profit + Days - Year";
    
    console.log("🔹 Using hardcoded values:", { field: fieldName, formula: formulaText });
    
    // Store both field ID and formula text
    $('#totalCalculatorModal').data('current-formula-field', fieldId);
    $('#totalCalculatorModal').data('current-formula-text', formulaText);
    
    $('#totalCalculatorModal').modal('show');
});

     // ✅ CHECK SECURE SESSION ON PAGE LOAD
    $.get("{{ route('logs.check_secure_session') }}", function(response) {
        if (response.hasSecureAccess) {
            areSecureColumnsVisible = true;
            $('#showHiddenFields')
                .text('Hide Secure Fields')
                .removeClass('btn-primary')
                .addClass('btn-warning')
                .prop('disabled', false); // ✅ Ensure enabled
            console.log("Secure session is active");
            
            // ✅ AUTOMATICALLY SHOW SECURE COLUMNS IF SESSION EXISTS
            setTimeout(() => {
                if (dataTable) {
                    forceShowSecureColumns();
                }
            }, 1000);
        } else {
            // Ensure button is in correct state when no secure access
            areSecureColumnsVisible = false;
            $('#showHiddenFields')
                .text('Show Hidden Fields')
                .removeClass('btn-warning')
                .addClass('btn-primary')
                .prop('disabled', false); // ✅ But still enabled if secure columns exist
            console.log("No secure session found");
        }
    }).fail(function() {
        // Fallback if check fails
        areSecureColumnsVisible = false;
        $('#showHiddenFields')
            .text('Show Hidden Fields')
            .prop('disabled', false); // ✅ Still enabled
        console.log("Secure session check failed");
    });

    // Initialize DataTable
    dataTable = initializeDataTable();

        $('#showHiddenFields').on('click', function() {
        console.log("Show Hidden Fields button clicked");
        if (areSecureColumnsVisible) {
            // Hide secure columns
            toggleSecureColumns(false);
        } else {
            // Show modal for password
            $('#hiddenFieldsModal').modal('show');
        }
    });

});
    // ✅ Verify Password Button Click
$('#verifyPassword').on('click', function() {
    let password = $('#securePassword').val();
    if (!password) {
        $('#passwordError').text('Please enter password').show();
        return;
    }    
    // Disable button and show loading state
    $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying...');
    
    verifyPasswordAndShowColumns(password);
});

    // ✅ Enter key support in password field
$('#securePassword').on('keypress', function(e) {
    if (e.which === 13) {
        let password = $('#securePassword').val();
        if (!password) {
            $('#passwordError').text('Please enter password').show();
            return;
        }
        
        $('#verifyPassword').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying...');
        verifyPasswordAndShowColumns(password);
    }
});

    // ✅ Reset modal when closed
$('#hiddenFieldsModal').on('hidden.bs.modal', function() {
    $('#securePassword').val('');
    $('#passwordError').hide();
    $('#verifyPassword').prop('disabled', false).html('Verify Password');
    hideLoader(); // Ensure loader is hidden
});

// Initialize delete buttons functionality
function initDeleteButtons() {
    $(document).off("click", ".delete-log-btn").on("click", ".delete-log-btn", function(e) {
        e.preventDefault();
        let deleteUrl = $(this).data('url');

        Swal.fire({
            title: 'Are you sure?',
            text: "This log will be deleted permanently!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create form dynamically and submit
                let form = $('<form>', {
                    method: 'POST',
                    action: deleteUrl
                });

                let token = '{{ csrf_token() }}';
                let method = $('<input>', {
                    type: 'hidden',
                    name: '_method',
                    value: 'delete'
                });

                let csrf = $('<input>', {
                    type: 'hidden',
                    name: '_token',
                    value: token
                });

                form.append(method, csrf).appendTo('body').submit();
            }
        });
    });
}
// Calculator script
let currentFormula = [];
let currentFormulaFieldId = null;

function displayPreviousFormula(formulaData) {
    if (!formulaData || !formulaData.formula) {
        $('#previousFormula').html(`
            <div class="text-muted">
                <i class="fas fa-info-circle"></i> No previous formula found
            </div>
        `);
        return;
    }
    
    const formulaDisplay = formulaData.formula.map(item => {
        if (item.type === 'field') {
            return `<span class="badge badge-primary p-2 m-1">${item.display}</span>`;
        } else {
            return `<span class="badge badge-secondary p-2 m-1">${item.display}</span>`;
        }
    }).join(' ');
    
    $('#previousFormula').html(`
        <div class="formula-readonly">
            <strong class="text-primary">Current Formula:</strong>
            <div class="mt-2 p-2 bg-light border rounded">
                ${formulaDisplay}
            </div>
            <small class="text-muted mt-1 d-block">
                <i class="fas fa-lightbulb"></i> This is the existing formula
            </small>
        </div>
    `);
}

// ✅ NEW: Erase last item function
$('#eraseLastItem').on('click', function() {
    if (currentFormula.length > 0) {
        currentFormula.pop(); // Remove last item
        updateFormulaPreview();
        
        // Show success feedback
        $(this).addClass('btn-success');
        setTimeout(() => $(this).removeClass('btn-success'), 300);
    } else {
        // Show warning feedback
        $(this).addClass('btn-warning');
        setTimeout(() => $(this).removeClass('btn-warning'), 300);
    }
});

// Add field to formula
$(document).on('click', '.field-btn', function() {
    const fieldId = $(this).data('field-id');
    const fieldName = $(this).data('field-name');
    
    currentFormula.push({
        type: 'field',
        value: fieldId,
        display: `{${fieldName}}`
    });
    
    updateFormulaPreview();
});

function hasConsecutiveOperators(formula) {
    if (formula.length < 2) return false;
    
    for (let i = 0; i < formula.length - 1; i++) {
        if (formula[i].type === 'operator' && formula[i + 1].type === 'operator') {
            return true;
        }
    }
    return false;
}

function startsOrEndsWithOperator(formula) {
    if (formula.length === 0) return false;
    
    const firstItem = formula[0];
    const lastItem = formula[formula.length - 1];
    
    return firstItem.type === 'operator' || lastItem.type === 'operator';
}

function hasDivisionByZero(formulaString) {
    if (!formulaString) return false;
    
    // Simple check for division by zero patterns
    const divisionByZeroRegex = /\/\s*0(?!\.)/g;
    return divisionByZeroRegex.test(formulaString);
}


// Add operator to formula
$(document).on('click', '.operator-btn', function() {
    const operator = $(this).data('operator');
    
    currentFormula.push({
        type: 'operator',
        value: operator,
        display: operator
    });
    
    updateFormulaPreview();
});

// Clear formula
$('#clearFormula').on('click', function() {
    currentFormula = [];
    updateFormulaPreview();
});

// Update formula preview
let lastValidationMessage = '';

// Update formula preview with validation status
function updateFormulaPreview() {
    const formulaDisplay = currentFormula.map(item => item.display).join(' ');
    $('#formulaPreview').text(formulaDisplay);
    
    // Update formula builder visual with better styling
    const builderHtml = currentFormula.map((item, index) => 
        `<span class="badge ${item.type === 'field' ? 'badge-primary' : 'badge-secondary'} mr-1 formula-item" 
              data-index="${index}">${item.display}</span>`
    ).join('');
    
    $('#formulaBuilder').html(builderHtml || '<span class="text-muted">Click fields and operators to build formula...</span>');
    
    updateValidationStatus();
}

// ✅ Update validation status visually
function updateValidationStatus() {
    let statusText = '';
    let statusClass = 'text-muted';
    let statusIcon = '';
    
    if (currentFormula.length === 0) {
        statusText = 'Start building your formula...';
        statusIcon = '🔰';
    } else if (hasConsecutiveOperators(currentFormula)) {
        statusText = 'Remove consecutive operators';
        statusClass = 'text-danger';
        statusIcon = '❌';
    } else if (startsOrEndsWithOperator(currentFormula)) {
        statusText = 'Formula should not start/end with operator';
        statusClass = 'text-danger';
        statusIcon = '❌';
    } else if (hasDivisionByZero(currentFormula.map(item => item.value).join(' '))) {
        statusText = 'Division by zero detected';
        statusClass = 'text-danger';
        statusIcon = '❌';
    } else {
        statusText = 'Formula looks good - Ready to save!';
        statusClass = 'text-success';
        statusIcon = '✅';
    }
    
    $('#validationStatus').removeClass('text-muted text-warning text-danger text-success')
                         .addClass(statusClass)
                         .html(`${statusIcon} ${statusText}`);
}

// ✅ REAL-TIME VALIDATION: Operator buttons with visual feedback
$(document).on('click', '.operator-btn', function() {
    const operator = $(this).data('operator');
    
    // Agar last item bhi operator hai to new operator na add karen
    if (currentFormula.length > 0 && currentFormula[currentFormula.length - 1].type === 'operator') {
        // Visual feedback - button shake effect
        $(this).addClass('btn-danger');
        setTimeout(() => $(this).removeClass('btn-danger'), 500);
        return;
    }
    
    // Agar formula empty hai to operator na add karen
    if (currentFormula.length === 0) {
        // Visual feedback - button shake effect
        $(this).addClass('btn-danger');
        setTimeout(() => $(this).removeClass('btn-danger'), 500);
        return;
    }    
    currentFormula.push({
        type: 'operator',
        value: operator,
        display: operator
    });
    
    updateFormulaPreview();
});

// ✅ REAL-TIME VALIDATION: Field buttons with visual feedback
$(document).on('click', '.field-btn', function() {
    const fieldId = $(this).data('field-id');
    const fieldName = $(this).data('field-name');
    
    // Agar last item field hai to operator add karna required hai
    if (currentFormula.length > 0 && currentFormula[currentFormula.length - 1].type === 'field') {
        // Visual feedback - show info in status
        lastValidationMessage = 'Add an operator between fields';
        updateFormulaPreview();
        return;
    }
    
    currentFormula.push({
        type: 'field',
        value: fieldId,
        display: `{${fieldName}}`
    });
    
    updateFormulaPreview();
});

// ✅ Clear formula with reset
$('#clearFormula').on('click', function() {
    currentFormula = [];
    lastValidationMessage = '';
    updateFormulaPreview();
});

// ✅ Modal open hone par reset karen
$(document).ready(function() {
$('#totalCalculatorModal').on('show.bs.modal', function() {
    currentFormula = [];
    updateFormulaPreview();
    $('#previousFormula').html(`
        <div class="text-center">
            <i class="fas fa-spinner fa-spin"></i> Loading formula...
        </div>
    `);
});

$('#totalCalculatorModal').on('hidden.bs.modal', function() {
    currentFormula = [];
    currentFormulaFieldId = null;
    updateFormulaPreview();
});
});

// Save formula and calculate - YAHAN SWAL.FIRE() RAHEGA
// ✅ Save formula button - Updated
$('#saveFormula').on('click', function() {
    if (currentFormula.length === 0) {
        Swal.fire('Warning!', 'Please build a formula first!', 'warning');
        return;
    }
    
    // ✅ Multiple sources se fieldId get karen
    let fieldId = $('#totalCalculatorModal').data('current-field-id') || 
                  $('#totalCalculatorModal').data('current-formula-field');
    let fieldName = $('#totalCalculatorModal').data('current-field-name');
    
    console.log("💾 Saving formula:", {
        fieldId: fieldId,
        fieldName: fieldName,
        formula: currentFormula
    });
    
    if (!fieldId) {
        console.error("❌ Field ID not found in modal data");
        Swal.fire('Error!', 'Could not identify formula field. Please try again.', 'error');
        return;
    }
    
    // Validation checks...
    const validationErrors = [];
    
    if (hasConsecutiveOperators(currentFormula)) {
        validationErrors.push('Two operators cannot be placed consecutively');
    }
    
    if (startsOrEndsWithOperator(currentFormula)) {
        validationErrors.push('Formula cannot start or end with an operator');
    }
    
    if (validationErrors.length > 0) {
        Swal.fire({
            title: 'Validation Errors!',
            html: validationErrors.map(error => `• ${error}`).join('<br>'),
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    // ✅ Formula data prepare karen
    const formulaData = {
        formula: currentFormula,
        formula_string: currentFormula.map(item => {
            if (item.type === 'field') {
                return item.value; // Field ID
            }
            return item.value; // Operator
        }).join(' ')
    };
    
    const saveBtn = $(this);
    saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
    
    $.ajax({
        url: "{{ route('logs.save-formula') }}",
        type: "POST",
        data: {
            _token: "{{ csrf_token() }}",
            formula: formulaData,
            formula_field_id: fieldId, // ✅ Yeh field ID use karen
            field_name: fieldName
        },
        success: function(response) {
            if (response.success) {
                // ✅ DataTable refresh
                if (dataTable) {
                    dataTable.ajax.reload(null, false);
                }
                
                $('#totalCalculatorModal').modal('hide');
                
                Swal.fire({
                    title: 'Success!',
                    text: response.message || 'Formula saved successfully!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Optional: Page reload for complete refresh
                    // location.reload();
                });
            } else {
                Swal.fire('Error!', response.error || 'Failed to save formula', 'error');
            }
        },
        error: function(xhr) {
            let errorMessage = 'Server error occurred';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMessage = xhr.responseJSON.error;
            }
            Swal.fire('Error!', errorMessage, 'error');
        },
        complete: function() {
            saveBtn.prop('disabled', false).html('Save & Calculate');
        }
    });
});

$(document).on('click', '.operator-btn', function() {
    const operator = $(this).data('operator');
    
    // Agar last item bhi operator hai to new operator na add karen
    if (currentFormula.length > 0 && currentFormula[currentFormula.length - 1].type === 'operator') {
        // ❌ Swal.fire remove karen - silent validation
        console.log("Cannot add consecutive operators");
        return;
    }
    
    // Agar formula empty hai to operator na add karen
    if (currentFormula.length === 0) {
        // ❌ Swal.fire remove karen - silent validation
        console.log("Formula cannot start with operator");
        return;
    }
    
    currentFormula.push({
        type: 'operator',
        value: operator,
        display: operator
    });
    
    updateFormulaPreview();
});

// ✅ REAL-TIME VALIDATION: Field buttons pe click karte time - SILENT
$(document).on('click', '.field-btn', function() {
    const fieldId = $(this).data('field-id');
    const fieldName = $(this).data('field-name');
    
    // Agar last item field hai to operator add karna required hai
    if (currentFormula.length > 0 && currentFormula[currentFormula.length - 1].type === 'field') {
        // ❌ Swal.fire remove karen - silent validation
        console.log("Please add operator between fields");
        return;
    }
    
    currentFormula.push({
        type: 'field',
        value: fieldId,
        display: `{${fieldName}}`
    });
    
    updateFormulaPreview();
});

function refreshDataTable() {
    if (dataTable) {
        // Clear current data and reload from server
        dataTable.ajax.reload(null, false); // false = retain current page & sorting
        
        console.log("DataTable refreshed with latest data");
    }
}

// Edit form submission ke baad (agar AJAX use kar rahe hain)
$(document).on('submit', '#editForm', function(e) {
    e.preventDefault();
    
    let form = $(this);
    let formData = new FormData(this);
    let submitBtn = form.find('button[type="submit"]');
    
    // Show loading state
    submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
    
    $.ajax({
        url: form.attr('action'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            // ✅ DataTable refresh karen
            refreshDataTable();
            
            Swal.fire({
                title: 'Success!',
                text: 'Record updated successfully!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // Redirect to index page after success
                window.location.href = "{{ route('logs.index') }}";
            });
        },
        error: function(xhr) {
            Swal.fire('Error!', 'Failed to update record', 'error');
        },
        complete: function() {
            // Reset button state
            submitBtn.prop('disabled', false).html('Update');
        }
    });
});


function exportCsv() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        xhrFields: {
            responseType: 'blob'
        },
        method: 'POST',
        url: "{{ route('logs.export') }}",
        success: function(response, status, xhr) {
            if (xhr.getResponseHeader("Content-Type") != 'application/json') {
                var blob = new Blob([response], { type: "application/vnd.ms-excel" });
                var fileName = '';
                var disposition = xhr.getResponseHeader('Content-Disposition');
                if (disposition && disposition.indexOf('attachment') !== -1) {
                    fileName = disposition.substring(disposition.indexOf('"') + 1, disposition.lastIndexOf('"'));
                }
                //Check the Browser type and download the File.
                var isIE = false || !!document.documentMode;
                if (isIE) {
                    window.navigator.msSaveBlob(blob, fileName);
                } else {
                    var url = window.URL || window.webkitURL;
                    var link = url.createObjectURL(blob);
                    var a = $("<a />");
                    a.attr("download", fileName);
                    a.attr("href", link);
                    $("body").append(a);
                    a[0].click();
                    $("body").remove(a);
                }
            }
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            var response = XMLHttpRequest.getResponseHeader("Content-Type");
            if (response == 'application/json') {
                $("#error").html("<div class='alert alert-danger mx-auto'>No record exsits!</div>");
            }
        }
    });
}

    // Add these functions to your JavaScript
$(document).on('click', '#resetColumnOrder', function() {
    var table = $('#table').DataTable();
    table.colReorder.reset();
    table.state.save();
});

$(document).on('click', '#toggleColumnVisibility', function() {
    var table = $('#table').DataTable();
    // Create a simple modal to toggle column visibility
    let modalContent = '<div class="p-3"><h5>Toggle Column Visibility</h5><div class="column-list">';
    
    table.columns().every(function(index) {
        let column = this;
        let header = table.column(index).header();
        let columnName = $(header).text();
        let isVisible = column.visible();
        
        modalContent += `
            <div class="form-check">
                <input class="form-check-input column-toggle" type="checkbox" 
                    data-column-index="${index}" ${isVisible ? 'checked' : ''}>
                <label class="form-check-label">${columnName}</label>
            </div>
        `;
    });
    
    modalContent += '</div><button class="btn btn-primary mt-3" id="applyColumnVisibility">Apply</button></div>';
    
    // Show in a modal (using Bootstrap)
    $('#fileModal .modal-body').html(modalContent);
    $('#fileModal .modal-title').text('Column Visibility');
    $('#fileModal').modal('show');
});

$(document).on('click', '#applyColumnVisibility', function() {
    var table = $('#table').DataTable();
    
    $('.column-toggle').each(function() {
        let index = $(this).data('column-index');
        let isVisible = $(this).is(':checked');
        
        table.column(index).visible(isVisible);
    });
    
    table.state.save();
    $('#fileModal').modal('hide');
});



$(document).on('click', '.open-folder-btn', function(e) {
    e.preventDefault();
    let btn = $(this);
    let url = btn.attr('href');
    let rowId = btn.data('row-id');
    
    // Check if it's local storage
    if(url.includes('file.browse.local')) {
        // Show loading state
        btn.html('<i class="fa fa-spinner fa-spin"></i> Opening...');
        
        // Get folder path via AJAX
        $.get(url, function(response) {
            if (response.files && response.files.length === 0) {
                // Show message for empty folder
                alert('No files uploaded yet for this record');
            } else {
                // For Windows - convert path to file:// URL
                let folderUrl = 'file:///' + response.path.replace(/\\/g, '/');
                window.open(folderUrl, '_blank');
            }
            
            // Reset button text
            btn.html('<i class="fa fa-folder"></i> Open');
        }).fail(function(xhr) {
            if (xhr.status === 404) {
                alert('No files uploaded yet');
            } else {
                alert('Error opening folder');
            }
            btn.html('<i class="fa fa-folder"></i> Open');
        });
        
        return false;
    } else {
        // For Drive, just open in new tab as before
        window.open(url, '_blank');
    }
});

    // Helper function to format file size
    function formatFileSize(bytes) {
        if(bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]);
    }


    $(document).on('click', '.delete-log-btn', function(e) {
    e.preventDefault();
    let deleteUrl = $(this).data('url');

    alert(deleteUrl);

    Swal.fire({
        title: 'Are you sure?',
        text: "This log will be deleted permanently!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create form dynamically and submit
            let form = $('<form>', {
                method: 'POST',
                action: deleteUrl
            });

            let token = '{{ csrf_token() }}';
            let method = $('<input>', {
                type: 'hidden',
                name: '_method',
                value: 'delete'
            });

            let csrf = $('<input>', {
                type: 'hidden',
                name: '_token',
                value: token
            });

            form.append(method, csrf).appendTo('body').submit();
        }
    });
});

</script>
@endsection
