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
                        <h2 class="mb-1">SET SECURITY ALERT PRIORITY</h2>
                        <p class="text-muted mb-0">Update priority level for security alert</p>
                    </div>
                </div>

                {{-- Back Button --}}
                <div class="row mb-4">
                    <div class="col-12">
                        <a href="{{ route('security-alert-priority.index') }}" class="btn btn-outline-secondary mb-3">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                {{-- Form --}}
                <div class="row">
                    <div class="col-12 col-md-8 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Set Priority for: {{ $securityAlertPriority->security_alert }}</h5>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('security-alert-priority.update', $securityAlertPriority) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    
                                    @if($errors->any())
                                        <div class="alert alert-danger">
                                            <ul class="mb-0">
                                                @foreach($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div class="mb-4">
                                        <label for="security_alert" class="form-label">Security Alert</label>
                                        <input type="text" 
                                               class="form-control bg-light" 
                                               value="{{ $securityAlertPriority->security_alert }}" 
                                               readonly>
                                        <small class="text-muted">Security alert cannot be modified</small>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="priority" class="form-label">Priority Level *</label>
                                        <div class="priority-options">
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="form-check priority-option">
                                                        <input class="form-check-input" type="radio" 
                                                               name="priority" id="priority_low" 
                                                               value="low" 
                                                               {{ old('priority', $securityAlertPriority->priority) == 'low' ? 'checked' : '' }}>
                                                        <label class="form-check-label w-100" for="priority_low">
                                                            <div class="priority-card priority-low p-3 text-center">
                                                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                                <h5 class="mb-1">Low</h5>
                                                                <small>Normal priority alerts</small>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="form-check priority-option">
                                                        <input class="form-check-input" type="radio" 
                                                               name="priority" id="priority_medium" 
                                                               value="medium" 
                                                               {{ old('priority', $securityAlertPriority->priority) == 'medium' ? 'checked' : '' }}>
                                                        <label class="form-check-label w-100" for="priority_medium">
                                                            <div class="priority-card priority-medium p-3 text-center">
                                                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                                                <h5 class="mb-1">Medium</h5>
                                                                <small>Important alerts</small>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="form-check priority-option">
                                                        <input class="form-check-input" type="radio" 
                                                               name="priority" id="priority_high" 
                                                               value="high" 
                                                               {{ old('priority', $securityAlertPriority->priority) == 'high' ? 'checked' : '' }}>
                                                        <label class="form-check-label w-100" for="priority_high">
                                                            <div class="priority-card priority-high p-3 text-center">
                                                                <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                                                                <h5 class="mb-1">High</h5>
                                                                <small>Critical alerts</small>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @error('priority')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="{{ route('security-alert-priority.index') }}" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Priority
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer text-muted">
                                <small>
                                    <i class="fas fa-info-circle"></i> Priority will determine how alerts are handled in the system.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Current Status --}}
                    <div class="col-12 col-md-4 col-lg-6 mt-4 mt-md-0">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Priority Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert-status mb-4">
                                    <h6 class="mb-2">Current Priority:</h6>
                                    <div class="d-flex align-items-center">
                                        <span class="badge 
                                            @if($securityAlertPriority->priority == 'high') bg-danger
                                            @elseif($securityAlertPriority->priority == 'medium') bg-warning text-dark
                                            @else bg-success
                                            @endif px-4 py-2 me-3 fs-6">
                                            {{ ucfirst($securityAlertPriority->priority) }}
                                        </span>
                                        <small class="text-muted">
                                            @if($securityAlertPriority->priority == 'high')
                                                <i class="fas fa-exclamation-circle text-danger"></i> Critical Priority
                                            @elseif($securityAlertPriority->priority == 'medium')
                                                <i class="fas fa-exclamation-triangle text-warning"></i> Important Priority
                                            @else
                                                <i class="fas fa-check-circle text-success"></i> Normal Priority
                                            @endif
                                        </small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h6 class="mb-3">Priority Impact:</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <small>Response Time</small>
                                        <small class="text-primary">
                                            @if($securityAlertPriority->priority == 'high')
                                                Immediate
                                            @elseif($securityAlertPriority->priority == 'medium')
                                                Within 1 hour
                                            @else
                                                Within 24 hours
                                            @endif
                                        </small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <small>Notification Level</small>
                                        <small class="text-primary">
                                            @if($securityAlertPriority->priority == 'high')
                                                All Staff
                                            @elseif($securityAlertPriority->priority == 'medium')
                                                Security Team
                                            @else
                                                System Only
                                            @endif
                                        </small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <small>Created</small>
                                        <small>{{ $securityAlertPriority->created_at->format('d-m-Y H:i') }}</small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <small>Last Updated</small>
                                        <small>{{ $securityAlertPriority->updated_at->format('d-m-Y H:i') }}</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
    
    // Priority card selection styling
    const priorityOptions = document.querySelectorAll('.priority-option input');
    priorityOptions.forEach(option => {
        option.addEventListener('change', function() {
            // Remove active class from all cards
            document.querySelectorAll('.priority-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Add active class to selected card
            const selectedCard = this.closest('.priority-option').querySelector('.priority-card');
            selectedCard.classList.add('active');
        });
        
        // Initialize active state
        if (option.checked) {
            const selectedCard = option.closest('.priority-option').querySelector('.priority-card');
            selectedCard.classList.add('active');
        }
    });
});
</script>
@endsection


