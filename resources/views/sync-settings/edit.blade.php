@extends('layout.main_layout')

@section('title', 'Edit Sync Configuration')

@section('content')
<div class="container mt-4 mb-3">
    <h4>Edit Sync Configuration</h4>

    <div class="card p-4">
        <form action="{{ route('sync-settings.update', $setting->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">IP / Host <span class="text-danger">*</span></label>
                <input type="text" name="ip_host" class="form-control" 
                       value="{{ old('ip_host', $setting->ip_host) }}" required>
                @error('ip_host') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Database Name <span class="text-danger">*</span></label>
                <input type="text" name="db_name" class="form-control" 
                       value="{{ old('db_name', $setting->db_name) }}" required>
                @error('db_name') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="db_user" class="form-control" 
                       value="{{ old('db_user', $setting->db_user) }}" required>
                @error('db_user') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="db_password" class="form-control" 
                       placeholder="Leave blank to keep current password">
                <small>Current password is stored (encrypted). Enter new one only if you want to change.</small>
                @error('db_password') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('sync-settings.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
