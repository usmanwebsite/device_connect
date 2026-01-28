<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="{{ asset('logo_image/mnr_logo.jpeg')}}" alt="SafeG Logo" class="img-style">
        {{--<img src="{{ asset('logo_image/LogoMnRTrams.png')}}" alt="SafeG Logo" class="img-style"> --}}
    </div>
    
    @if(isset($angularMenu) && count($angularMenu) > 0)
    <ul class="sidebar-menu">
        @foreach($angularMenu as $mainItem)
        <li class="menu-item dropdown">
            <div class="menu-main">
                <div class="menu-icon-section">
                    <i class='bx {{ $mainItem['icon'] }} menu-icon'></i>
                </div>
                <div class="menu-content-section">
                    <div class="menu-label-container">
                        <label for="menu-{{ $mainItem['id'] }}" class="menu-label">
                            {{ $mainItem['label'] }}
                        </label>
                        @if(isset($mainItem['subItems']) && count($mainItem['subItems']) > 0)
                        <span class="dropdown-arrow">
                            <i class="bx bx-chevron-down" style="font-size: 1rem !important"></i>
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            
            @if(isset($mainItem['subItems']) && count($mainItem['subItems']) > 0)
            <ul class="dropdown-content">
                @foreach($mainItem['subItems'] as $subItem)
                    @if(isset($subItem['subItems']) && count($subItem['subItems']) > 0)
                        <li class="submenu">
                            <a href="#" class="submenu-header">
                                <div class="menu-icon-section"></div>
                                <div class="menu-content-section">
                                    <div class="submenu-label-container">
                                        <span class="submenu-label">{{ $subItem['label'] }}</span>
                                        <span class="submenu-arrow">▼</span>
                                    </div>
                                </div>
                            </a>
                            <ul class="nested-dropdown">
                                @foreach($subItem['subItems'] as $nestedItem)
                                    @if(isset($nestedItem['subItems']) && count($nestedItem['subItems']) > 0)
                                        <!-- Level 3: Double nested submenu -->
                                        <li class="submenu">
                                            <a href="#" class="submenu-header">
                                                <div class="menu-icon-section"></div>
                                                <div class="menu-content-section">
                                                    <div class="submenu-label-container">
                                                        <span class="submenu-label">{{ $nestedItem['label'] }}</span>
                                                        <span class="submenu-arrow">▶</span>
                                                    </div>
                                                </div>
                                            </a>
                                            <ul class="nested-dropdown level-3">
                                                @foreach($nestedItem['subItems'] as $doubleNestedItem)
                                                <li class="menu-link">
                                                    {{-- ✅ CHECK: Laravel route ya Angular route --}}
                                                    @if(isset($doubleNestedItem['isLaravelRoute']) && $doubleNestedItem['isLaravelRoute'])
                                                        {{-- ✅ Laravel Route - Same tab mein open hoga --}}
                                                        <a href="{{ url($doubleNestedItem['link']) }}" 
                                                           style="font-size: 10px"
                                                           class="nav-link">
                                                            {{ $doubleNestedItem['label'] }}
                                                        </a>
                                                    @else
                                                        <a href="{{ route('angular.redirect', ['route' => $doubleNestedItem['link']]) }}" 
                                                           style="font-size: 10px"
                                                           class="nav-link">
                                                            {{ $doubleNestedItem['label'] }}
                                                        </a>
                                                    @endif
                                                </li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @else
                                        <!-- Level 3: Direct nested link -->
                                        <li class="menu-link">
                                            {{-- ✅ CHECK: Laravel route ya Angular route --}}
                                            @if(isset($nestedItem['isLaravelRoute']) && $nestedItem['isLaravelRoute'])
                                                {{-- ✅ Laravel Route - Same tab mein open hoga --}}
                                                <a href="{{ url($nestedItem['link']) }}" 
                                                   class="nav-link">
                                                    {{ $nestedItem['label'] }}
                                                </a>
                                            @else
                                                <a href="{{ route('angular.redirect', ['route' => $nestedItem['link']]) }}" 
                                                   class="nav-link">
                                                    {{ $nestedItem['label'] }}
                                                </a>
                                            @endif
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <!-- Level 2: Direct link (no nested dropdown) -->
                        <li class="menu-link">
                            {{-- ✅ CHECK: Laravel route ya Angular route --}}
                            @if(isset($subItem['isLaravelRoute']) && $subItem['isLaravelRoute'])
                                {{-- ✅ Laravel Route - Same tab mein open hoga --}}
                                <a href="{{ url($subItem['link']) }}" 
                                   class="nav-link">
                                    {{ $subItem['label'] }}
                                </a>
                            @else
                                <a href="{{ route('angular.redirect', ['route' => $subItem['link']]) }}" 
                                   class="nav-link">
                                    {{ $subItem['label'] }}
                                </a>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
            @endif
        </li>
        @endforeach
    </ul>
    @else
    <div class="menu-error">
        <p>No menu items available for your role</p>
        <p>Please contact administrator for access permissions</p>
    </div>
    @endif
</aside>

