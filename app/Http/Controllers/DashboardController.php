<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MenuService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use App\Models\DeviceAccessLog;

class DashboardController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function index()
    {
        try {
            // getFilteredAngularMenu() use karen jo Java API call karega
            $angularMenu = $this->menuService->getFilteredAngularMenu();
            
            // ✅ Java API se user access data le aayein
            $userAccessData = $this->menuService->getUserAccessData();
            
            // Agar userAccessData milta hai to use karein
            $todayAppointmentCount = 0;
            $upcomingAppointments = [];
            $todayAppointments = [];
            
            
            if ($userAccessData && isset($userAccessData['today_appointment_count'])) {
                $todayAppointmentCount = $userAccessData['today_appointment_count'];
                $todayAppointments = $userAccessData['today_appointments'] ?? [];
                $upcomingAppointments = $userAccessData['upcoming_appointments'] ?? [];
            }
            
            // ✅ POINT 1: Currently On-Site - Sirf device_access_logs se data
            $visitorsOnSite = [];
            $activeSecurityAlertsCount = 0;
            
        $visitorsOnSite = DeviceAccessLog::where('access_granted', 1)
            ->get();

            
            // ✅ Pehle hi sabhi visitors ke liye Java API data fetch karein
            $visitorsOnSite = $this->getVisitorsOnSiteWithJavaData($visitorsOnSite);
            
            // ✅ Active Security Alerts
            $activeSecurityAlertsCount = DeviceAccessLog::where('access_granted', 0)
                ->whereDate('created_at', now()->format('Y-m-d'))
                ->count();

            // ✅ DYNAMIC GRAPH DATA: Device access logs se hourly data lein
            $hourlyTrafficData = $this->getHourlyTrafficData();

            // ✅ POINT 2: Visitor Overstay Alerts - Java API se data leke check karein
            $visitorOverstayAlerts = $this->getVisitorOverstayAlerts($visitorsOnSite);

            $deniedAccessLogs = DeviceAccessLog::where('access_granted', 0)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();


            return view('dashboard', compact(
                'angularMenu', 
                'todayAppointmentCount', 
                'visitorsOnSite',
                'todayAppointments',
                'upcomingAppointments',
                'activeSecurityAlertsCount',
                'hourlyTrafficData',
                'visitorOverstayAlerts',
                'deniedAccessLogs'
            ));
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            
            // Default values in case of error
            $angularMenu = [];
            $todayAppointmentCount = 0;
            $visitorsOnSite = [];
            $todayAppointments = [];
            $upcomingAppointments = [];
            $activeSecurityAlertsCount = 0;
            $hourlyTrafficData = $this->getDefaultHourlyTrafficData();
            $visitorOverstayAlerts = [];
            $deniedAccessLogs = [];
            
            return view('dashboard', compact(
                'angularMenu', 
                'todayAppointmentCount', 
                'visitorsOnSite',
                'todayAppointments',
                'upcomingAppointments',
                'activeSecurityAlertsCount',
                'hourlyTrafficData',
                'visitorOverstayAlerts',
                'deniedAccessLogs'
            ));
        }
    }

    // ✅ NAYA METHOD: Sabhi visitors ke liye Java API data ek sath fetch karein
    private function getVisitorsOnSiteWithJavaData($visitors)
    {
        foreach ($visitors as &$visitor) {
            try {
                $javaApiResponse = $this->callJavaVendorApi($visitor['staff_no']);

                if ($javaApiResponse && isset($javaApiResponse['data'])) {
                    $data = $javaApiResponse['data'];

                    // fullName fix
                    $visitor['full_name'] = $data['fullName'] 
                                            ?? $data['name'] 
                                            ?? 'N/A';

                    // host fix
                    $visitor['person_visited'] = $data['personVisited']
                                                ?? $data['visitedPerson']
                                                ?? 'N/A';

                } else {
                    $visitor['full_name'] = 'N/A';
                    $visitor['person_visited'] = 'N/A';
                }
            } catch (\Exception $e) {
                Log::error('Java API error for staff_no ' . $visitor['staff_no'] . ': ' . $e->getMessage());
                $visitor['full_name'] = 'N/A';
                $visitor['person_visited'] = 'N/A';
            }
        }

        return $visitors;
    }


    // ✅ UPDATED METHOD: Visitor Overstay Alerts - Java API se data leke check karein
    private function getVisitorOverstayAlerts($visitorsOnSite)
    {
        $overstayAlerts = [];
        $currentTime = now();

        foreach ($visitorsOnSite as $visitor) {
            try {
                // ✅ Java API call karein staff_no ke liye
                $javaApiResponse = $this->callJavaVendorApi($visitor['staff_no']);
                
                if ($javaApiResponse && isset($javaApiResponse['data'])) {
                    $visitorData = $javaApiResponse['data'];
                    
                    // ✅ Check karein ke dateOfVisitTo current time se zyada hai ya nahi
                    if (isset($visitorData['dateOfVisitTo'])) {
                        $dateOfVisitTo = \Carbon\Carbon::parse($visitorData['dateOfVisitTo']);
                        
                        if ($currentTime->greaterThan($dateOfVisitTo)) {
                            // ✅ Agar current time dateOfVisitTo se zyada hai, toh overstay alert banayein
                            $overstayMinutes = $currentTime->diffInMinutes($dateOfVisitTo);
                            $overstayHours = floor($overstayMinutes / 60);
                            $remainingMinutes = $overstayMinutes % 60;
                            
                            $overstayAlerts[] = [
                                'visitor_name' => $visitorData['fullName'] ?? 'N/A',
                                'staff_no' => $visitor['staff_no'],
                                'expected_end_time' => $dateOfVisitTo->format('h:i A'),
                                'current_time' => $currentTime->format('h:i A'),
                                'check_in_time' => \Carbon\Carbon::parse($visitor['created_at'])->format('h:i A'),
                                'location' => $visitor['location_name'] ?? 'N/A',
                                'overstay_minutes' => $overstayMinutes,
                                'overstay_duration' => $overstayHours . ' hours ' . $remainingMinutes . ' minutes',
                                'host' => $visitorData['personVisited'] ?? 'N/A'
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error checking overstay for staff_no ' . $visitor['staff_no'] . ': ' . $e->getMessage());
                continue;
            }
        }

        return $overstayAlerts;
    }

    // ✅ Naya method: Java Vendor API call ke liye
    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $token = 'eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJzdXBlcmFkbWluIiwiYXV0aEtleSI6IjZ3bDFtdUQ3IiwiY29tcGFueUlkIjoic3VwZXJhZG1pbiIsImFjY2VzcyI6WyJQUEFIVENEIiwiUFBBSFRDRSIsIlZQUmVxTCIsIlJUeXBlIiwiQnJyQ29uZiIsIlZQQ2xvTERlbCIsIlBQQUwiLCJDcG5JbmYiLCJSUEJSRXgiLCJDUENMVkEiLCJQUEFIVENNIiwiVlBQTCIsIlBQUkwiLCJDVENvbmYiLCJCQ1JMIiwiQk5hbWUiLCJXSExDb25mIiwiUFBHSUV4IiwiUkNQIiwiUlBQTUciLCJCSUNMUmVsIiwiUFBDTCIsIkJDQ0xSZWwiLCJWUEFMIiwiY1ZBIiwiUFBFVENNIiwiUFBVIiwiUFBFVENFIiwiUFBFVENEIiwiVlBSTCIsIkNpdHlJbmYiLCJNR0lPIiwiQ1BSTEUiLCJzVlAiLCJWUFJlakxEZWwiLCJCQ0NMIiwiUFBTTCIsIkNJbmYiLCJWUENMIiwiUlBQTSIsIm15UFAiLCJDTkNWUFJMIiwiTENJbmYiLCJNTE9HSU4iLCJDUFJMZWciLCJDTkNWUEFMIiwiUm9sZSIsIkNQUkxEQSIsIlBQR0kiLCJDcG5QIiwiTlNDUiIsIkJSQ29uZiIsIkNQUkxEUiIsIkNQUkxEVSIsIkRJbmYiLCJCSVJMIiwiUlBQUyIsIkNOQ1ZQQ0wiLCJCSUNMIiwiUFBJTCIsIlBQT1dJRXgiLCJDUEFMREEiLCJSUkNvbmYiLCJWUEludkwiLCJMQ2xhc3MiLCJWUFJlakwiLCJCSVJMQXBwciIsIlJQQlIiLCJQUFN1c0wiLCJDUFJEQXBwIiwiQ1BBTERVIiwiQ05DVlBSZWpMRGVsIiwiQ1BBTERSIiwiQVBQQ29uZiIsIkNQQUwiLCJteVZQIiwiQlR5cGUiLCJDaENvbSIsIlZpblR5cGUiLCJkYXNoMSIsIkRFU0luZiIsIkNQUlNPIiwiQ1BSTCIsIkNQUkgiLCJDTkNWUENsb0xEZWwiLCJSVlNTIiwiU0xDSW5mIiwiQ1BDTCIsIm15Q05DVlAiLCJTUFAiLCJDUFJMRURSIiwiTFZDSW5mIiwiQ1BSTEVEVSIsIlBQUmVqTCIsIkNhdGVJbmYiLCJDTkNWUFJlakwiLCJVc2VyIiwiQkNSTEFwcHIiLCJTUFBEVCIsIkxJbmYiLCJDUFJMRURBIiwiUFBQTCIsIlN0YXRlSW5mIiwiUFBBSFRDIiwiUFBPV0kiLCJSQ1AyIiwiUFBFVEMiLCJDVFAiXSwicm9sZSI6WyJTVVBFUiBBRE1JTiJdLCJjcmVhdGVkIjoxNzYzNzIxMjAyMjMyLCJkaXNwbGF5TmFtZSI6IlN1cGVyIEFkbWluIiwiZXhwIjoxNzYzODA3NjAyfQ.tM-AlDjgt3WIggA_ERJr1vFq3lzIK-7Wq2H2xa5abPBL3ioBKJul33Yf3aprM2IqYyTyrG6ClZKthhFR031fBw'; // Aapka Java token
            
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(10)
              ->get($javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . $staffNo);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Java API error for staff_no ' . $staffNo . ': ' . $response->status());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Java API exception for staff_no ' . $staffNo . ': ' . $e->getMessage());
            return null;
        }
    }

    // ✅ Naya method: Hourly traffic data ke liye
// ✅ UPDATED METHOD: Hourly traffic data ke liye - Cumulative count
private function getHourlyTrafficData()
{
    try {
        // Aaj ke din ke liye data lein
        $today = now()->format('Y-m-d');
        
        // ✅ Pehle aaj ke saare successful access logs lein
        $todayAccessLogs = DeviceAccessLog::whereDate('created_at', $today)
            ->where('access_granted', 1)
            ->orderBy('created_at')
            ->get();
        
        // ✅ 24 hours ke liye array prepare karein
        $cumulativeData = [];
        $labels = [];
        
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $timeLabel = $i < 12 ? "{$hour} AM" : ($i == 12 ? "12 PM" : ($i - 12) . " PM");
            
            $labels[] = $timeLabel;
            
            // ✅ Current hour tak ke saare active visitors count karein
            $currentHourEnd = Carbon::createFromFormat('Y-m-d H', $today . ' ' . $i);
            
            // ✅ Us hour tak jitne bhi visitors check-in kar chuke hain, woh cumulative count
            $cumulativeCount = $todayAccessLogs
                ->filter(function ($log) use ($currentHourEnd) {
                    return Carbon::parse($log->created_at)->lte($currentHourEnd);
                })
                ->count();
            
            $cumulativeData[] = $cumulativeCount;
        }
        
        return [
            'labels' => $labels,
            'data' => $cumulativeData
        ];
        
    } catch (\Exception $e) {
        Log::error('Hourly traffic data error: ' . $e->getMessage());
        return $this->getDefaultHourlyTrafficData();
    }
}

    // ✅ Default data agar koi error ho
    private function getDefaultHourlyTrafficData()
    {
        $labels = ['8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM'];
        $data = [10, 30, 25, 40, 20, 35, 45, 30, 25, 15];
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }
}

