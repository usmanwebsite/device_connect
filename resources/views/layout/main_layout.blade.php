<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeG | Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/colreorder/1.5.5/css/colReorder.bootstrap5.min.css">

    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
        
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css">
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* Fix for main layout structure */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        body {
            display: flex;
            min-height: 100vh;
            font-family: 'Montserrat', sans-serif;
            flex-direction: column;
        }
        
        .main-content {
            flex: 1 0 auto;
            min-height: calc(100vh - 200px); /* Adjust based on header/footer height */
            padding-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        /* Container adjustments */
        .container {
            padding-bottom: 20px;
            margin-bottom: 0;
        }
        
        /* Footer styling */
        .footer {
            background-color: #f8f9fa;
            padding: 15px 0;
            margin-top: auto;
            border-top: 1px solid #dee2e6;
            position: relative;
            z-index: 2;
            width: 100%;
            flex-shrink: 0;
        }
        
        .footer p {
            margin-bottom: 5px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* DataTables adjustments */
        .dataTables_wrapper {
            margin-bottom: 10px !important;
        }
        
        .dataTables_wrapper .dataTables_paginate {
            margin: 5px 0 !important;
            padding: 0 !important;
        }
        
        /* Table container for better spacing */
        .table-container-wrapper {
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
        }
        
        /* Remove DataTables sorting icons */
        table.dataTable thead .sorting,
        table.dataTable thead .sorting_asc,
        table.dataTable thead .sorting_desc,
        table.dataTable thead .sorting_asc_disabled,
        table.dataTable thead .sorting_desc_disabled {
            background-image: none !important;
        }
        
        /* Ensure datatable container handles its own scroll */
        .datatable-scroll-container {
            overflow-x: auto;
            width: 100%;
        }
        
        .modal {
            z-index: 99999 !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }   
        
        .modal-backdrop {
            z-index: 1050 !important;
            opacity: 0.5 !important;
            position: relative !important;
        }
        
        .modal-content {
            z-index: 999999 !important;
            position: relative;
        }
        
        .modal.show .modal-dialog {
            transform: none;
            opacity: 1;
        }
        
        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }
        
        /* Ensure modal is above everything when open */
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }

        body.modal-open .sidebar {
            z-index: 1 !important; /* Modal open hone par sidebar ko piche bhejein */
        }

        /* Modal dialog positioning */
        .modal-dialog {
            z-index: 100000 !important;
        }
        
        /* Compact table styling */
        .compact-table {
            font-size: 14px;
        }
        
        .compact-table th,
        .compact-table td {
            padding: 8px 12px !important;
            vertical-align: middle;
        }
    </style>
    
    @yield('styles')
</head>
<body>
    <!-- Include Sidebar -->
    @include('layout.sidebar')

    <div class="main-content">
        <!-- Include Header -->
        @include('layout.header')

        <!-- Main Content Area -->
        <div class="content-wrapper">
            @yield('content')
        </div>

        <!-- Include Footer -->
        @include('layout.footer')
    </div>

    <!-- Move ALL scripts to the bottom -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/colreorder/1.5.5/js/dataTables.colReorder.min.js"></script>
    
    @yield('scripts')

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Main dropdown functionality
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(item => {
            const menuMain = item.querySelector('.menu-main');
            
            if (menuMain) {
                menuMain.addEventListener('click', function(e) {
                    if (e.target.type === 'checkbox') return;
                    
                    // Close other dropdowns when opening a new one
                    if (!item.classList.contains('active')) {
                        menuItems.forEach(otherItem => {
                            if (otherItem !== item) {
                                otherItem.classList.remove('active');
                            }
                        });
                    }
                    
                    item.classList.toggle('active');
                });
            }
        });

        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');

        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('collapsed');
        });

        // Nested dropdown functionality
        const submenus = document.querySelectorAll('.submenu');
        
        submenus.forEach(submenu => {
            const submenuHeader = submenu.querySelector('.submenu-header');
            
            if (submenuHeader) {
                submenuHeader.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Close other submenus at the same level
                    const parentList = this.closest('.nested-dropdown');
                    if (parentList) {
                        const siblings = parentList.querySelectorAll('.submenu');
                        siblings.forEach(sibling => {
                            if (sibling !== submenu) {
                                sibling.classList.remove('active');
                            }
                        });
                    }
                    
                    submenu.classList.toggle('active');
                });
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.sidebar')) {
                // Close all dropdowns
                menuItems.forEach(item => {
                    item.classList.remove('active');
                });
                
                submenus.forEach(submenu => {
                    submenu.classList.remove('active');
                });
            }
        });

        // Close dropdowns when a link is clicked
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Optional: Add any link click handling here
            });
        });


        
            const userMenuBtn = document.getElementById('userMenuBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');
    const userDropdown = document.querySelector('.user-dropdown');

    if (userMenuBtn && dropdownMenu && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });
        
        // Close dropdown when clicking on a link
        const dropdownLinks = document.querySelectorAll('.dropdown-link');
        dropdownLinks.forEach(link => {
            link.addEventListener('click', function() {
                userDropdown.classList.remove('show');
            });
        });
    }


    });
    </script>

</body>
</html>
