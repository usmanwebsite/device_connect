@extends('layout.main_layout')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Assign Device to Location</h4>
        <a href="{{ route('device-assignments.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">New Assignment</h5>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('device-assignments.store') }}" method="POST">
                @csrf
                
                <div class="mb-3">
                    <label for="device_id" class="form-label">Select Device <span class="text-danger">*</span></label>
                    <select name="device_id" id="device_id" class="form-select" required>
                        <option value="">-- Select Device --</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->id }}" {{ old('device_id') == $device->id ? 'selected' : '' }}>
                                {{ $device->device_id }} - {{ $device->ip ?? 'Unnamed' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="location_id" class="form-label">Select Location <span class="text-danger">*</span></label>
                    <select name="location_id" id="location_id" class="form-select" required>
                        <option value="">-- Select Location --</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="is_type" class="form-label">Assignment Type <span class="text-danger">*</span></label>
                    <select name="is_type" id="is_type" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        <option value="check_in" {{ old('is_type') == 'check_in' ? 'selected' : '' }}>Check-In</option>
                        <option value="check_out" {{ old('is_type') == 'check_out' ? 'selected' : '' }}>Check-Out</option>
                    </select>
                    <small class="text-muted">Check-In: For entry devices, Check-Out: For exit devices</small>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Assign Device
                    </button>
                    <a href="{{ route('device-assignments.index') }}" class="btn btn-secondary" style="width: 
                    145px; height: 44px; line-height: 25px">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

