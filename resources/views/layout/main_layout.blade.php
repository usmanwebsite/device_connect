<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeG | Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
</head>
<body>
    <!-- Include Sidebar -->
    @include('layout.sidebar')

    <div class="main-content">
        <!-- Include Header -->
        @include('layout.header')

        <!-- Main Content Area -->
        <div class="content">
            <!-- Your main content goes here -->
        </div>

        <!-- Include Footer -->
        @include('layout.footer')
    </div>

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

        });
    });
});
</script>


</body>
</html>

