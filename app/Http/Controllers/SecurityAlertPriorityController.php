<?php

namespace App\Http\Controllers;

use App\Models\SecurityAlertPriority;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityAlertPriorityController extends Controller
{
    /**
     * Display a listing of the security alert priorities.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get all security alert priorities with pagination (10 per page)
        $priorities = SecurityAlertPriority::orderBy('created_at', 'desc')->paginate(10);
        
        return view('securityAlertPriority.index', compact('priorities'));
    }

    /**
     * Show the form for editing the specified security alert priority.
     *
     * @param  \App\Models\SecurityAlertPriority  $securityAlertPriority
     * @return \Illuminate\Http\Response
     */
    public function edit(SecurityAlertPriority $securityAlertPriority)
    {
        return view('securityAlertPriority.edit', compact('securityAlertPriority'));
    }

    /**
     * Update the specified security alert priority in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\SecurityAlertPriority  $securityAlertPriority
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, SecurityAlertPriority $securityAlertPriority)
    {
        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high'
        ]);

        try {
            // Update only the priority
            $securityAlertPriority->update([
                'priority' => $validated['priority']
            ]);
            
            return redirect()->route('security-alert-priority.index')
                ->with('success', 'Security alert priority updated successfully.');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update security alert priority. Please try again.');
        }
    }

}
