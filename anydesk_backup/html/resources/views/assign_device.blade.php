@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form action="{{ route('assign.device.store') }}" method="POST">
    @csrf
    <div>
        <label>Device:</label>
        <select name="device_id" required>
            <option value="">Select Device</option>
            @foreach($devices as $device)
                <option value="{{ $device->id }}">{{ $device->device_id }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label>Location:</label>
        <select name="location_id" required>
            <option value="">Select Location</option>
            @foreach($locations as $location)
                <option value="{{ $location->id }}">{{ $location->name }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit">Assign Device</button>
</form>
