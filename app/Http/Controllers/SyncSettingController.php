<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SyncSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SyncSettingController extends Controller
{
    /**
     * Display current sync settings (only one record exists).
     * If no record, redirect to create.
     */
    public function index()
    {
        $setting = SyncSetting::first();
        if (!$setting) {
            return redirect()->route('sync-settings.create')
                ->with('info', 'No sync configuration found. Please create one.');
        }
        return view('sync-settings.index', compact('setting'));
    }

    /**
     * Show form to create new sync settings.
     */
    public function create()
    {
        // Check if already exists
        if (SyncSetting::exists()) {
            return redirect()->route('sync-settings.index')
                ->with('error', 'Sync configuration already exists. You can edit it.');
        }
        return view('sync-settings.create');
    }

    /**
     * Store a new sync settings record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ip_host' => ['required', 'regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}(:[0-9]+)?$/'],
            'db_name'    => 'required|string|max:255',
            'db_user'    => 'required|string|max:255',
            'db_password' => 'nullable|string|min:4', // optional but recommended
        ]);

        // Encrypt password before saving
        if (!empty($validated['db_password'])) {
            $validated['db_password'] = Crypt::encryptString($validated['db_password']);
        } else {
            unset($validated['db_password']); // will use nullable default
        }

        SyncSetting::create($validated);

        return redirect()->route('sync-settings.index')
            ->with('success', 'Sync configuration created successfully.');
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $setting = SyncSetting::findOrFail($id);
        // Decrypt password for display (optional, you may leave blank)
        $decryptedPassword = '';
        if ($setting->db_password) {
            try {
                $decryptedPassword = Crypt::decryptString($setting->db_password);
            } catch (\Exception $e) {
                // If decryption fails, keep blank
            }
        }
        return view('sync-settings.edit', compact('setting', 'decryptedPassword'));
    }

    /**
     * Update the specified sync settings.
     */
    public function update(Request $request, $id)
    {
        $setting = SyncSetting::findOrFail($id);

        $validated = $request->validate([
            'ip_host'    => ['required', 'regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}(:[0-9]+)?$/'],
            'db_name'    => 'required|string|max:255',
            'db_user'    => 'required|string|max:255',
            'db_password' => 'nullable|string|min:4',
        ]);

        $updateData = [
            'ip_host' => $validated['ip_host'],
            'db_name' => $validated['db_name'],
            'db_user' => $validated['db_user'],
        ];

        // Only update password if a new one is provided
        if (!empty($validated['db_password'])) {
            $updateData['db_password'] = Crypt::encryptString($validated['db_password']);
        }

        $setting->update($updateData);

        return redirect()->route('sync-settings.index')
            ->with('success', 'Sync configuration updated successfully.');
    }

    /**
     * Remove the sync settings (optional, rarely needed).
     */
    public function destroy($id)
    {
        $setting = SyncSetting::findOrFail($id);
        $setting->delete();

        return redirect()->route('sync-settings.create')
            ->with('success', 'Sync configuration deleted. You can create a new one.');
    }
} 

