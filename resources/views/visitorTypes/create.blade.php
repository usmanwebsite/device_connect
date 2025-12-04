@extends('layout.main_layout')

@section('title', 'Add Visitor Type')

@section('content')
<div class="container mt-4 mb-3">
    <h4>Add Visitor Type</h4>

    <div class="card p-4">
        <form action="{{ route('visitor-types.store') }}" method="POST">
            @csrf
            
            <div class="mb-3">
                <label class="form-label">Visitor Type</label>
                <input type="text" name="visitor_type" class="form-control" required>
                @error('visitor_type')
                    <div class="text-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Select Path</label>
                <select name="path_id" class="form-control" required>
                    <option value="">-- Select Path --</option>
                    @foreach($paths as $path)
                        <option value="{{ $path->id }}" {{ old('path_id') == $path->id ? 'selected' : '' }}>
                            {{ $path->name }}
                        </option>
                    @endforeach
                </select>
                @error('path_id')
                    <div class="text-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('visitor-types.index') }}" class="btn btn-secondary">
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
