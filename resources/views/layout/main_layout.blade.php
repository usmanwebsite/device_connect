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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
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

        .sidebar-header {
            position: relative;   /* needed for absolute positioning of close button */
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .bx-chevron-down:before{
            margin-right: 14px;
        }

        #menuToggle {
    display: inline-flex !important;     /* block ki jagah inline-flex */
    align-items: center;
    justify-content: center;
    width: 44px !important;              /* Fixed width – icon size */
    height: 44px !important;             /* Proper touch target */
    padding: 0 !important;               /* Extra padding hatao */
    margin: 0 !important;
    background: transparent;
    border: none;
    cursor: pointer;
}

.menu-icon {
    display: inline-block;   /* important */
    width: auto;
    cursor: pointer;
    padding: 8px;            /* optional for better UX */
}
.left-section {
    width: auto;            /* full width na le */
    display: flex;
    align-items: center;
}

.header-left,
.navbar-left,
.d-flex.align-items-center {
    display: inline-flex !important;     /* ya flex with auto width */
    width: auto !important;
    gap: 0.5rem;
}
        

         body.sidebar-open {
    overflow: hidden;
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
    // ========== GLOBAL FUNCTIONS ==========
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');

    if (!sidebar) return;

    const isCollapsed = sidebar.classList.contains('collapsed');

    if (isCollapsed) {
        sidebar.classList.remove('collapsed');
        if (window.innerWidth < 768) {
            document.body.classList.add('sidebar-open');
        }
    } else {
        sidebar.classList.add('collapsed');
        document.body.classList.remove('sidebar-open');
    }
}

    function handleResponsiveSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        if (!sidebar || !mainContent) return;

        if (window.innerWidth < 768) {
            if (!sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                mainContent.style.marginLeft = '0';
            }
        } else {
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                mainContent.style.marginLeft = '';
            }
        }
    }

    // ========== DOM CONTENT LOADED ==========
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Responsive sidebar initial state
        handleResponsiveSidebar();

        // 2. Main menu dropdowns (click + touch for mobile)
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            const menuMain = item.querySelector('.menu-main');
            if (!menuMain) return;

            const toggleActive = (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Close other open dropdowns
                menuItems.forEach(other => {
                    if (other !== item && other.classList.contains('active')) {
                        other.classList.remove('active');
                    }
                });
                item.classList.toggle('active');
            };

            menuMain.addEventListener('click', toggleActive);
            menuMain.addEventListener('touchstart', toggleActive, { passive: false });
        });

        // 3. Hamburger menu (calls global toggleSidebar)
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');

if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function(e) {
        if (!e.target.closest('#menuToggle')) return;
        e.stopPropagation();
        toggleSidebar();
    });

    menuToggle.addEventListener('touchstart', function(e) {
        if (!e.target.closest('#menuToggle')) return;
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    }, { passive: false });
}

        // 4. Nested submenu dropdowns (level 2 & 3)
        const submenus = document.querySelectorAll('.submenu');
        submenus.forEach(submenu => {
            const submenuHeader = submenu.querySelector('.submenu-header');
            if (!submenuHeader) return;

            const toggleSubmenu = (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Close siblings at same level
                const parentList = submenu.closest('.nested-dropdown');
                if (parentList) {
                    parentList.querySelectorAll('.submenu').forEach(sibling => {
                        if (sibling !== submenu) sibling.classList.remove('active');
                    });
                }
                submenu.classList.toggle('active');
            };

            submenuHeader.addEventListener('click', toggleSubmenu);
            submenuHeader.addEventListener('touchstart', toggleSubmenu, { passive: false });
        });

document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('menuToggle');

    if (
        !e.target.closest('.sidebar') &&
        !e.target.closest('#menuToggle')
    ) {
        menuItems.forEach(item => item.classList.remove('active'));
        submenus.forEach(sub => sub.classList.remove('active'));

        if (window.innerWidth < 768) {
            sidebar.classList.add('collapsed');
            document.body.classList.remove('sidebar-open');
        }
    }
});

        // 6. User dropdown (if present)
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.querySelector('.user-dropdown');
        if (userMenuBtn && userDropdown) {
            const toggleUserDropdown = (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            };
            userMenuBtn.addEventListener('click', toggleUserDropdown);
            userMenuBtn.addEventListener('touchstart', toggleUserDropdown, { passive: false });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (userDropdown && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
            // Close when a dropdown link is clicked
            document.querySelectorAll('.dropdown-link').forEach(link => {
                link.addEventListener('click', () => userDropdown.classList.remove('show'));
            });
        }
    });

    // 7. Re-run responsive check on window resize
    window.addEventListener('resize', handleResponsiveSidebar);

</script>

</body>
</html>
