@extends('layout.main_layout')

@section('title', 'Create Sync Configuration')

@section('content')
<div class="container mt-4 mb-3">
    <h4>Create Sync Configuration</h4>

    <div class="card p-4">
        <form action="{{ route('sync-settings.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label">IP / Host <span class="text-danger">*</span></label>
                <input type="text" name="ip_host" class="form-control" value="{{ old('ip_host') }}" required>
                @error('ip_host') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Database Name <span class="text-danger">*</span></label>
                <input type="text" name="db_name" class="form-control" value="{{ old('db_name') }}" required>
                @error('db_name') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="db_user" class="form-control" value="{{ old('db_user') }}" required>
                @error('db_user') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="db_password" class="form-control" placeholder="Minimum 4 characters">
                <small>Password will be encrypted.</small>
                @error('db_password') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('sync-settings.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

