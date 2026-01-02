@extends('layout.main_layout')

@section('content')
<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="content-card">
                {{-- Header --}}
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="mb-1">SECURITY ALERT PRIORITIES</h2>
                        <p class="text-muted mb-0">Manage security alert priority levels</p>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- Main Table --}}
                <div class="table-container-wrapper" id="priorityTableContainer">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-fixed-header" id="priorityTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Security Alert</th>
                                    <th>Priority</th>
                                    <th>Created At</th>
                                    <th>Updated At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($priorities as $index => $priority)
                                <tr>
                                    <td class="text-center">{{ $priorities->firstItem() + $index }}</td>
                                    <td>
                                        <div class="alert-name" style="min-height: 40px; display: flex; align-items: center;">
                                            {{ $priority->security_alert }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($priority->priority == 'high') bg-danger
                                            @elseif($priority->priority == 'medium') bg-warning text-dark
                                            @else bg-success
                                            @endif">
                                            {{ ucfirst($priority->priority) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="date-time">
                                            <div class="date">{{ $priority->created_at->format('d-m-Y') }}</div>
                                            <div class="time text-muted">{{ $priority->created_at->format('H:i') }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-time">
                                            <div class="date">{{ $priority->updated_at->format('d-m-Y') }}</div>
                                            <div class="time text-muted">{{ $priority->updated_at->format('H:i') }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('security-alert-priority.edit', $priority) }}" 
                                           class="btn btn-info btn-sm w-100" style="white-space: nowrap;">
                                            <i class="fas fa-edit me-1"></i> Set Priority
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <h5 class="d-inline">No priority settings found</h5>
                                            <p class="mb-0 mt-2">No security alert priorities available.</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pagination --}}
                @if($priorities->hasPages() || $priorities->count() > 0)
                <div class="row mt-4 align-items-center">
                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-2">Showing</span>
                            <span class="fw-bold">{{ $priorities->firstItem() ?? 0 }}-{{ $priorities->lastItem() ?? 0 }}</span>
                            <span class="text-muted mx-2">of</span>
                            <span class="fw-bold">{{ $priorities->total() }}</span>
                            <span class="text-muted ms-2">entries</span>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm justify-content-center justify-content-md-end mb-0">
                                {{-- Previous Page Link --}}
                                @if($priorities->onFirstPage())
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $priorities->previousPageUrl() }}" aria-label="Previous">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                @endif

                                {{-- Page Numbers --}}
                                @php
                                    $current = $priorities->currentPage();
                                    $last = $priorities->lastPage();
                                    $start = max(1, $current - 2);
                                    $end = min($last, $current + 2);
                                    
                                    if($end - $start < 4) {
                                        if($start == 1) {
                                            $end = min(5, $last);
                                        } else if($end == $last) {
                                            $start = max(1, $last - 4);
                                        }
                                    }
                                @endphp

                                {{-- First Page --}}
                                @if($start > 1)
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $priorities->url(1) }}">1</a>
                                    </li>
                                    @if($start > 2)
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    @endif
                                @endif

                                {{-- Page Range --}}
                                @for($i = $start; $i <= $end; $i++)
                                    @if($i == $current)
                                        <li class="page-item active">
                                            <span class="page-link">{{ $i }}</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $priorities->url($i) }}">{{ $i }}</a>
                                        </li>
                                    @endif
                                @endfor

                                {{-- Last Page --}}
                                @if($end < $last)
                                    @if($end < $last - 1)
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    @endif
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $priorities->url($last) }}">{{ $last }}</a>
                                    </li>
                                @endif

                                {{-- Next Page Link --}}
                                @if($priorities->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $priorities->nextPageUrl() }}" aria-label="Next">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                @else
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@section('styles')
<style>
/* Additional inline styles for better table display */
.date-time {
    line-height: 1.2;
}

.date {
    font-weight: 500;
}

.time {
    font-size: 12px;
}

.alert-name {
    word-break: break-word;
    overflow-wrap: break-word;
}

.table-container-wrapper::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.table-container-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-container-wrapper::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.table-container-wrapper::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        });
    }
    
    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.sidebar');
        const mobileToggle = document.getElementById('mobileMenuToggle');
        
        if (window.innerWidth <= 768 && 
            !sidebar.contains(e.target) && 
            !mobileToggle.contains(e.target) &&
            sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Adjust table height on window resize
    function adjustTableHeight() {
        const tableContainer = document.getElementById('priorityTableContainer');
        if (tableContainer) {
            const windowHeight = window.innerHeight;
            const containerOffset = tableContainer.getBoundingClientRect().top;
            const calculatedHeight = windowHeight - containerOffset - 200; // 200px for pagination and margins
            
            if (calculatedHeight > 300) {
                tableContainer.style.maxHeight = calculatedHeight + 'px';
            }
        }
    }
    
    // Initial adjustment
    adjustTableHeight();
    
    // Adjust on window resize
    window.addEventListener('resize', adjustTableHeight);
});
</script>
@endsection

