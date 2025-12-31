<header class="header">
    <div class="left-section">
        <div class="menu-icon" id="menuToggle">
            &#9776; <!-- Hamburger icon -->
        </div>
    </div>

    <div class="right-section">
        <div class="user-dropdown">
            <div class="user-info" id="userMenuBtn">
                <img src="{{ asset('logo_image/blank-head-icon.gif') }}" class="user-icon" alt="User">
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <ul class="dropdown-menu" id="dropdownMenu">
                <li>
                    <a href="http://localhost:8080/#/pages/edit-profile"
                        class="dropdown-link"
                        style="margin-left: 8px">
                            <i class="fa-regular fa-user"></i>
                            <span style="font-size: 12px">Profile</span>
                    </a>
                </li>
                <li class="dropdown-divider"></li>
                <li>
                    <a href=""
                        class="dropdown-link logout-link"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        style="margin-left: 8px">
                            <i class="fa-solid fa-power-off"></i>
                            <span style="font-size: 12px">Logout</span>
                    </a> 
                </li>
            </ul>
        </div>
    </div>
    
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>

</header>
