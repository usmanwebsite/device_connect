@extends('layout.main_layout')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Edit Device Assignment</h4>
        <a href="{{ route('device-assignments.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Update Assignment</h5>
        </div>
        <div class="card-body">
            @if(session('error'))
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('device-assignments.update', $assignment->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="mb-3">
                    <label for="device_id" class="form-label">Device</label>
                    <select name="device_id" id="device_id" class="form-select" required>
                        <option value="">-- Select Device --</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->id }}" 
                                {{ $assignment->device_id == $device->id ? 'selected' : '' }}>
                                {{ $device->device_id }} - {{ $device->device_name ?? 'Unnamed' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="location_id" class="form-label">Location</label>
                    <select name="location_id" id="location_id" class="form-select" required>
                        <option value="">-- Select Location --</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" 
                                {{ $assignment->location_id == $location->id ? 'selected' : '' }}>
                                {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="is_type" class="form-label">Assignment Type</label>
                    <select name="is_type" id="is_type" class="form-select" required>
                        @foreach($types as $key => $value)
                            <option value="{{ $key }}" 
                                {{ $assignment->is_type == $key ? 'selected' : '' }}>
                                {{ $value }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" >
                        <i class="fas fa-save"></i> Update
                    </button>
                    <a href="{{ route('device-assignments.index') }}" class="btn btn-secondary" style="height: 44px; width:100px;line-height: 28px">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

