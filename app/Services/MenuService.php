<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class MenuService
{
    protected $javaBaseUrl;

    public function __construct()
    {
        $this->javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
    }

    public function getFilteredAngularMenu()
    {
        $userAccessData = $this->fetchUserAccessFromJavaBackend();
        
        $userPermissions = $userAccessData['business_functions'] ?? [];
        
        Log::info('Java Backend se User Access Data: ', $userAccessData);
        Log::info('User Permissions: ', $userPermissions);
        
        $fullMenu = $this->getAngularMenu();
        $filteredMenu = $this->filterMenuByPermissions($fullMenu, $userPermissions);
        
        Log::info('Filtered Menu Count: ' . count($filteredMenu));
        
        return $filteredMenu;
    }

    private function fetchUserAccessFromJavaBackend()
    {
        try {
            $token = $this->getJavaAuthToken();
            Log::info('token',['token'=>$token]);
            
            if (!$token) {
                Log::error('Java auth token not available');
                return $this->getDefaultAccessData();
            }

            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(30)
              ->get($this->javaBaseUrl . '/api/admin/user_access');

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Java API Raw Response: ', $data);
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    // ✅ NEW: Save the new token from response to session
                    if (isset($data['token'])) {
                        $this->saveJavaTokenToSession($data['token']);
                        Log::info('New Java token saved to session');
                    }
                    
                    return [
                        'user_id' => $data['user_id'],
                        'roles' => $data['roles'],
                        'business_functions' => $data['business_functions']
                    ];
                } else {
                    Log::error('Java API Error: ' . ($data['message'] ?? 'Unknown error'));
                    return $this->getDefaultAccessData();
                }
            } else {
                Log::error('Java API HTTP Error: ' . $response->status() . ' - ' . $response->body());
                
                // ✅ NEW: If token expired, clear it from session
                if ($response->status() === 401) {
                    $this->clearJavaTokenFromSession();
                    Log::info('Cleared expired token from session');
                }
                
                return $this->getDefaultAccessData();
            }
            
        } catch (\Exception $e) {
            Log::error('Java API Exception: ' . $e->getMessage());
            return $this->getDefaultAccessData();
        }
    }

    private function getJavaAuthToken()
    {
        // ✅ FIRST: Try to get token from session
        $sessionToken = Session::get('java_backend_token');
        if ($sessionToken) {
            Log::info('Using token from session');
            return $sessionToken;
        }

        // ✅ SECOND: If no session token, use hardcoded token and save to session
        $hardcodedToken = 'eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJzdXBlcmFkbWluIiwiYXV0aEtleSI6IlBFeVNWa29vIiwiY29tcGFueUlkIjoic3VwZXJhZG1pbiIsImFjY2VzcyI6WyJQUEFIVENEIiwiUFBBSFRDRSIsIlZQUmVxTCIsIlJUeXBlIiwiQnJyQ29uZiIsIlZQQ2xvTERlbCIsIlBQQUwiLCJDcG5JbmYiLCJSUEJSRXgiLCJDUENMVkEiLCJQUEFIVENNIiwiVlBQTCIsIlBQUkwiLCJDVENvbmYiLCJCQ1JMIiwiQk5hbWUiLCJXSExDb25mIiwiUFBHSUV4IiwiUkNQIiwiUlBQTUciLCJCSUNMUmVsIiwiUFBDTCIsIkJDQ0xSZWwiLCJWUEFMIiwiY1ZBIiwiUFBFVENNIiwiUFBVIiwiUFBFVENFIiwiUFBFVENEIiwiVlBSTCIsIkNpdHlJbmYiLCJNR0lPIiwiQ1BSTEUiLCJzVlAiLCJWUFJlakxEZWwiLCJCQ0NMIiwiUFBTTCIsIkNJbmYiLCJWUENMIiwiUlBQTSIsIm15UFAiLCJDTkNWUFJMIiwiTENJbmYiLCJNTE9HSU4iLCJDUFJMZWciLCJDTkNWUEFMIiwiUm9sZSIsIkNQUkxEQSIsIlBQR0kiLCJDcG5QIiwiTlNDUiIsIkJSQ29uZiIsIkNQUkxEUiIsIkNQUkxEVSIsIkRJbmYiLCJCSVJMIiwiUlBQUyIsIkNOQ1ZQQ0wiLCJCSUNMIiwiUFBJTCIsIlBQT1dJRXgiLCJDUEFMREEiLCJSUkNvbmYiLCJWUEludkwiLCJMQ2xhc3MiLCJWUFJlakwiLCJCSVJMQXBwciIsIlJQQlIiLCJQUFN1c0wiLCJDUFJEQXBwIiwiQ1BBTERVIiwiQ05DVlBSZWpMRGVsIiwiQ1BBTERSIiwiQVBQQ29uZiIsIkNQQUwiLCJteVZQIiwiQlR5cGUiLCJDaENvbSIsIlZpblR5cGUiLCJkYXNoMSIsIkRFU0luZiIsIkNQUlNPIiwiQ1BSTCIsIkNQUkgiLCJDTkNWUENsb0xEZWwiLCJSVlNTIiwiU0xDSW5mIiwiQ1BDTCIsIm15Q05DVlAiLCJTUFAiLCJDUFJMRURSIiwiTFZDSW5mIiwiQ1BSTEVEVSIsIlBQUmVqTCIsIkNhdGVJbmYiLCJDTkNWUFJlakwiLCJVc2VyIiwiQkNSTEFwcHIiLCJTUFBEVCIsIkxJbmYiLCJDUFJMRURBIiwiUFBQTCIsIlN0YXRlSW5mIiwiUFBBSFRDIiwiUFBPV0kiLCJSQ1AyIiwiUFBFVEMiLCJDVFAiXSwicm9sZSI6WyJTVVBFUiBBRE1JTiJdLCJjcmVhdGVkIjoxNzYzNTM3MTE3NjY1LCJkaXNwbGF5TmFtZSI6IlN1cGVyIEFkbWluIiwiZXhwIjoxNzYzNjIzNTE3fQ._5JiHT8vhwVfr1Ws6CLIxQRlYwOrPH1mkCTU508J5VZicMCvBppbGKRJNW62UO-00gvjtV0ze75vBOIWojjQ1g';
        
        // Save hardcoded token to session for future use
        $this->saveJavaTokenToSession($hardcodedToken);
        Log::info('Hardcoded token saved to session');
        
        return $hardcodedToken;
    }

    private function saveJavaTokenToSession($token)
    {
        Session::put('java_backend_token', $token);
        Session::save(); // Ensure session is saved immediately
    }

    private function clearJavaTokenFromSession()
    {
        Session::forget('java_backend_token');
        Session::save();
    }

    public function getJavaTokenFromSession()
    {
        return Session::get('java_backend_token');
    }

    // ✅ NEW: Check if token exists in session
    public function hasJavaTokenInSession()
    {
        return Session::has('java_backend_token');
    }


    private function getDefaultAccessData()
    {
        return [
            'user_id' => Auth::id(),
            'roles' => [],
            'business_functions' => [] // Empty array - koi permission nahi
        ];
    }

private function filterMenuByPermissions($menu, $userPermissions)
{
    $filteredMenu = [];

    foreach ($menu as $item) {
        // Check main item permission
        $hasMainPermission = $this->checkPermission($item['isAuth'] ?? '', $userPermissions);
        
        Log::info('Checking menu item: ' . $item['label'] . ' with isAuth: ' . $item['isAuth'] . ' - Result: ' . ($hasMainPermission ? 'true' : 'false'));

        if ($hasMainPermission) {
            $filteredItem = $item;
            
            // Filter subItems
            if (isset($item['subItems']) && is_array($item['subItems'])) {
                $filteredSubItems = [];
                
                foreach ($item['subItems'] as $subItem) {
                    $hasSubPermission = $this->checkPermission($subItem['isAuth'] ?? '', $userPermissions);
                    
                    Log::info('Checking sub item: ' . $subItem['label'] . ' with isAuth: ' . $subItem['isAuth'] . ' - Result: ' . ($hasSubPermission ? 'true' : 'false'));

                    if ($hasSubPermission) {
                        // Filter nested subItems
                        if (isset($subItem['subItems']) && is_array($subItem['subItems'])) {
                            $filteredNestedItems = [];
                            
                            foreach ($subItem['subItems'] as $nestedItem) {
                                $hasNestedPermission = $this->checkPermission($nestedItem['isAuth'] ?? '', $userPermissions);
                                
                                Log::info('Checking nested item: ' . $nestedItem['label'] . ' with isAuth: ' . $nestedItem['isAuth'] . ' - Result: ' . ($hasNestedPermission ? 'true' : 'false'));

                                if ($hasNestedPermission) {
                                    $filteredNestedItems[] = $nestedItem;
                                }
                            }
                            
                            $subItem['subItems'] = $filteredNestedItems;
                        }
                        
                        $filteredSubItems[] = $subItem;
                    }
                }
                
                $filteredItem['subItems'] = $filteredSubItems;
            }
            
            // Only add item if it has subItems or is a direct link
            if (isset($filteredItem['subItems']) && count($filteredItem['subItems']) > 0) {
                $filteredMenu[] = $filteredItem;
            } else {
                Log::info('Skipping menu item because no subItems left: ' . $item['label']);
            }
        }
    }

    Log::info('Final filtered menu count: ' . count($filteredMenu));
    return $filteredMenu;
}

    private function checkPermission($requiredPermissions, $userPermissions)
    {
        // BLACKLIST ka isAuth: 'BIRL,BICL,BCRL,BCCL,BVCL'
        $requiredArray = explode(',', $requiredPermissions); // ['BIRL', 'BICL', 'BCRL', 'BCCL', 'BVCL']
        
        // Check if user has at least one required permission
        foreach ($requiredArray as $permission) {
            $permission = trim($permission);
            if (in_array($permission, $userPermissions)) {
                return true; // ✅ Menu item show hoga
            }
        }

        return false; // ❌ Menu item hide hoga
    }

    public function getAngularMenu()
    {
        return [
            [
                'id' => 1.0,
                'label' => "DASHBOARD",
                'icon' => "bx-home-circle",
                'isAuth' => 'VPDASH,STADASH,EMGDASH,VEHDASH,GAMDASH,PPDASH,STDDASH',
                'subItems' => [
                    [
                        'id' => 1.1,
                        'label' => "VISITOR",
                        'link' => "dashboard/dashboard",
                        'isAuth' => 'VPDASH',
                        'parentId' => 1.0,
                    ],
                    [
                        'id' => 1.3,
                        'label' => "EMERGENCY",
                        'link' => "dashboard/dashboard-emergency",
                        'isAuth' => 'EMGDASH',
                        'parentId' => 1.0,
                    ],
                    [
                        'id' => 1.4,
                        'label' => "VEHICLE",
                        'link' => "dashboard/dashboard-vehicle",
                        'isAuth' => 'VEHDASH',
                        'parentId' => 1.0,
                    ],
                    [
                        'id' => 1.5,
                        'label' => "GATE MONITORING",
                        'link' => "dashboard/dashboard-truck-monitoring",
                        'isAuth' => 'GAMDASH',
                        'parentId' => 1.0,
                    ],
                    [
                        'id' => 1.6,
                        'label' => "VENDOR PASS",
                        'link' => "dashboard/dashboard-port-pass",
                        'isAuth' => 'PPDASH',
                        'parentId' => 1.0,
                    ],
                    [
                        'id' => 1.8,
                        'label' => "STUDENT",
                        'link' => "dashboard/dashboard-student",
                        'isAuth' => 'STDDASH',
                        'parentId' => 1.0,
                    ]
                ]
            ],

            [
                'id' => 2.0,
                'label' => "PASS",
                'icon' => "bx-id-card",
                'isAuth' => 'myPP,myVPP,VPInvL,VPReqL,VPPL,PPRL,PPRejL,PPAL,PPU,PPSusL,PPPL,PPIL,PPCL,SPP,PPGI,PPTI,PPETC,ChCom,PPSFL,PPSTL',
                'subItems' => [
                    [
                        'id' => 2.1,
                        'label' => "MY PASS",
                        'link' => "port-pass/my-port-pass",
                        'isAuth' => 'myPP',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.2,
                        'label' => "STAFF",
                        'link' => "port-pass/staff",
                        'isAuth' => 'PPSFL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.3,
                        'label' => "STUDENT",
                        'link' => "port-pass/student",
                        'isAuth' => 'PPSTL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.4,
                        'label' => "VISITOR",
                        'parentId' => 2.0,
                        'isAuth' => 'VPInvL,VPReqL,VPPL',
                        'subItems' => [
                            [
                                'id' => 2.5,
                                'label' => "INVITATION",
                                'link' => "port-pass/visitor-invitation",
                                'isAuth' => 'VPInvL',
                                'parentId' => 2.2,
                            ],
                            [
                                'id' => 2.6,
                                'label' => "REQUEST LIST",
                                'link' => "port-pass/visitor-request-list",
                                'isAuth' => 'VPReqL',
                                'parentId' => 2.2,
                            ],
                            [
                                'id' => 2.7,
                                'label' => "PASS LIST",
                                'link' => "port-pass/visitor-pass-list",
                                'isAuth' => 'VPPL',
                                'parentId' => 2.2,
                            ],
                        ],
                    ],
                    [
                        'id' => 2.8,
                        'label' => "REQUEST LIST",
                        'link' => "port-pass/port-pass-request-list",
                        'isAuth' => 'PPRL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.9,
                        'label' => "REJECT LIST",
                        'link' => "port-pass/port-pass-reject-list",
                        'isAuth' => 'PPRejL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.10,
                        'label' => "APPROVED LIST",
                        'link' => "port-pass/port-pass-approved-list",
                        'isAuth' => 'PPAL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.11,
                        'label' => "URINE TEST",
                        'link' => "port-pass/urine-test",
                        'isAuth' => 'PPU',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.12,
                        'label' => "SUSPEND LIST",
                        'link' => "port-pass/suspend-list",
                        'isAuth' => 'PPSusL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.13,
                        'label' => "PROCESS LIST",
                        'link' => "port-pass/process-list",
                        'isAuth' => 'PPPL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.14,
                        'label' => "ISSUANCE LIST",
                        'link' => "port-pass/issuance-list",
                        'isAuth' => 'PPIL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.15,
                        'label' => "CLOSED LIST",
                        'link' => "port-pass/closed-list",
                        'isAuth' => 'PPCL',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.16,
                        'label' => "SEARCH",
                        'link' => "port-pass/search",
                        'isAuth' => 'SPP',
                        'parentId' => 2.0,
                    ],
                    [
                        'id' => 2.17,
                        'label' => "TRAINING CALENDAR",
                        'isAuth' => 'PPETC',
                        'parentId' => 2.0,
                        'subItems' => [
                            [
                                'id' => 2.18,
                                'label' => "HSE & SECURITY",
                                'link' => "port-pass/hse-and-security",
                                'isAuth' => 'PPETC',
                                'parentId' => 2.17,
                            ],
                        ],
                    ],
                    [
                        'id' => 2.40,
                        'label' => "ONLINE TRAINING",
                        'isAuth' => 'PPOT',
                        'parentId' => 2.0,
                        'subItems' => [
                            [
                                'id' => 2.41,
                                'label' => "REQUEST LIST",
                                'link' => "onlinetraining/online-training-list",
                                'isAuth' => 'PPOT',
                                'parentId' => 2.40,
                            ],
                            [
                                'id' => 2.42,
                                'label' => "CLOSED LIST",
                                'link' => "onlinetraining/online-training-closed-list",
                                'isAuth' => 'PPOT',
                                'parentId' => 2.40,
                            ],
                        ],
                    ],
                    [
                        'id' => 2.20,
                        'label' => "CHANGE COMPANY",
                        'link' => "port-pass/change-company",
                        'isAuth' => 'ChCom',
                        'parentId' => 2.0,
                    ],
                ],
            ],

            [
                'id' => 8.0,
                'label' => "BLACKLIST",
                'icon' => "bx-user-x",
                'isAuth' => 'BIRL,BICL,BCRL,BCCL,BVCL',
                'subItems' => [
                    [
                        'id' => 8.1,
                        'label' => "INDIVIDUAL",
                        'isAuth' => 'BIRL,BICL',
                        'parentId' => 8.0,
                        'subItems' => [
                            [
                                'id' => 8.2,
                                'label' => "REQUEST LIST",
                                'link' => "blacklist/individual-request-list",
                                'isAuth' => 'BIRL',
                                'parentId' => 8.1,
                            ],
                            [
                                'id' => 8.3,
                                'label' => "CLOSED LIST",
                                'link' => "blacklist/individual-closed-list",
                                'isAuth' => 'BICL',
                                'parentId' => 8.1,
                            ],
                        ]
                    ],
                    [
                        'id' => 8.4,
                        'label' => "COMPANY",
                        'isAuth' => 'BCRL,BCCL',
                        'parentId' => 8.0,
                        'subItems' => [
                            [
                                'id' => 8.5,
                                'label' => "REQUEST LIST",
                                'link' => "blacklist/company-request-list",
                                'isAuth' => 'BCRL',
                                'parentId' => 8.4,
                            ],
                            [
                                'id' => 8.6,
                                'label' => "CLOSED LIST",
                                'link' => "blacklist/company-closed-list",
                                'isAuth' => 'BCCL',
                                'parentId' => 8.4,
                            ],
                        ]
                    ],
                ]
            ],

            [
                'id' => 3.0,
                'label' => "VEHICLE MANAGEMENT",
                'icon' => "bxs-truck",
                'isAuth' => '',
                'subItems' => [
                    [
                        'id' => 3.1,
                        'label' => "VEP",
                        'link' => "vehicle-management/new-vehicle-entry-pass",
                        'isAuth' => '',
                        'parentId' => 3.0,
                    ],
                    [
                        'id' => 3.2,
                        'label' => "VEHICLE REQUEST LIST",
                        'link' => "vehicle-management/new-vehicle-request-pass",
                        'isAuth' => '',
                        'parentId' => 3.0,
                    ],
                    [
                        'id' => 3.3,
                        'label' => "VEHICLE REJECT LIST",
                        'link' => "vehicle-management/new-vehicle-reject-pass",
                        'isAuth' => '',
                        'parentId' => 3.0,
                    ],
                    [
                        'id' => 3.4,
                        'label' => "VEHICLE APPROVED LIST",
                        'link' => "vehicle-management/approve-list",
                        'isAuth' => '',
                        'parentId' => 3.0,
                    ],
                ],
            ],

            [
                'id' => 4.0,
                'label' => "WEIGHBRIDGE",
                'icon' => "bx-carousel",
                'isAuth' => '',
                'subItems' => [
                    [
                        'id' => 4.1,
                        'label' => "VESSEL SCHEDULE",
                        'link' => "weigh-bridge/vessel-schedule-new",
                        'isAuth' => '',
                        'parentId' => 4.0,
                    ],
                    [
                        'id' => 4.2,
                        'label' => "CARGO INFORMATION",
                        'link' => "weigh-bridge/cargo-information",
                        'isAuth' => '',
                        'parentId' => 4.0,
                    ],
                    [
                        'id' => 4.3,
                        'label' => "WB MONITOR",
                        'link' => "weigh-bridge/wb-monitor",
                        'isAuth' => '',
                        'parentId' => 4.0,
                    ],
                ],
            ],

            [
                'id' => 5.0,
                'label' => "REPORTING",
                'icon' => "bxs-report",
                'isAuth' => 'REPO',
                'subItems' => [
                    [
                        'id' => 5.1,
                        'label' => "WB REPORT",
                        'link' => "report/wb-report",
                        'isAuth' => 'VPDASH',
                        'parentId' => 5.0,
                    ],
                    [
                        'id' => 5.2,
                        'label' => "CARGO REPORT",
                        'link' => "report/cargo-report",
                        'isAuth' => '',
                        'parentId' => 5.0,
                    ],
                    [
                        'id' => 5.3,
                        'label' => "VEHICLE REPORT",
                        'link' => "report/vehicle-report",
                        'isAuth' => '',
                        'parentId' => 5.0,
                    ],
                ],
            ],

            [
                'id' => 9.0,
                'label' => "REPORT",
                'icon' => "bxs-report",
                'isAuth' => 'RPVIEW,RSDf,ATTENDANCE_REPORT',
                'subItems' => [
                    [
                        'id' => 9.1,
                        'label' => "ATTENDANCE REPORT",
                        'link' => "report/report",
                        'isAuth' => 'ATTENDANCE_REPORT',
                        'parentId' => 9.0,
                    ],
                    [
                        'id' => 9.2,
                        'label' => "VISITOR",
                        'link' => "report/visitorReport",
                        'isAuth' => 'RPVIEW',
                        'parentId' => 9.0,
                    ],
                    [
                        'id' => 9.3,
                        'label' => "VISITOR IN PREMISE",
                        'link' => "report/visitorInPremise",
                        'isAuth' => 'RPVIEW',
                        'parentId' => 9.0,
                    ],
                    [
                        'id' => 9.4,
                        'label' => "VISITOR OUT OF WINDOW",
                        'link' => "report/visitorOutofWindow",
                        'isAuth' => 'RPVIEW',
                        'parentId' => 9.0,
                    ],
                ]
            ],

            [
                'id' => 10.0,
                'label' => "MONITORING",
                'icon' => "bx-desktop",
                'isAuth' => 'MGIO,ORMGIO,MT',
                'subItems' => [
                    [
                        'id' => 10.1,
                        'label' => "BOOKING",
                        'link' => "monitoring/booking-list",
                        'isAuth' => 'MGIO',
                        'parentId' => 10.0,
                    ],
                    [
                        'id' => 10.2,
                        'label' => "ON HOLD / REJECT",
                        'link' => "monitoring/onhold-reject-booking-list",
                        'isAuth' => 'ORMGIO',
                        'parentId' => 10.0,
                    ],
                    [
                        'id' => 10.3,
                        'label' => "MONITORING",
                        'link' => "monitoring/transaction-list",
                        'isAuth' => 'MT',
                        'parentId' => 10.0,
                    ],
                    [
                        'id' => 10.4,
                        'label' => "2D MONITORING",
                        'link' => "monitoring/working-monitoring",
                        'isAuth' => 'MT',
                        'parentId' => 10.0,
                    ],
                    [
                        'id' => 10.5,
                        'label' => "DASHBOARD",
                        'link' => "monitoring/worker-dashboard",
                        'isAuth' => 'MT',
                        'parentId' => 10.0,
                    ]
                ]
            ],

            [
                'id' => 15.0,
                'label' => "VEHICLE",
                'icon' => "bx-car",
                'isAuth' => 'myVP,NSCR,sVP,VPRejL,VPRL,VPAL,VPCL',
                'subItems' => [
                    [
                        'id' => 15.1,
                        'label' => "MY VEHICLE PASS",
                        'link' => "vehicle/pass-list",
                        'isAuth' => 'myVP',
                        'parentId' => 15.0,
                    ],
                    [
                        'id' => 15.2,
                        'label' => "NEW STAFF REQUEST",
                        'link' => "vehicle/pass-request-staff",
                        'isAuth' => 'NSCR',
                        'parentId' => 15.0,
                    ],
                    [
                        'id' => 15.3,
                        'label' => "STAFF VEHICLE PASS",
                        'link' => "vehicle/staff-pass-list",
                        'isAuth' => 'sVP',
                        'parentId' => 15.0,
                    ],
                    [
                        'id' => 15.4,
                        'label' => "REJECT LIST",
                        'link' => "vehicle/reject-list",
                        'isAuth' => 'VPRejL',
                        'parentId' => 15.0,
                    ],
                    [
                        'id' => 15.5,
                        'label' => "REQUEST LIST",
                        'link' => "vehicle/request-list",
                        'isAuth' => 'VPRL',
                        'parentId' => 15.0,
                    ],
                    [
                        'id' => 15.6,
                        'label' => "APPROVED LIST",
                        'link' => "vehicle/accept-list",
                        'isAuth' => 'VPAL',
                        'parentId' => 15.0,
                    ],
                    [
                        'id' => 15.7,
                        'label' => "CLOSED LIST",
                        'link' => "vehicle/closed-list",
                        'isAuth' => 'VPCL',
                        'parentId' => 15.0,
                    ],
                ]
            ],

            [
                'id' => 16.0,
                'label' => "ATTENDANCE",
                'icon' => "bx-user",
                'isAuth' => 'STAFFLIST,VENDORLIST,STUDENTLIST',
                'subItems' => [
                    [
                        'id' => 16.1,
                        'label' => "STAFF LIST",
                        'link' => "workers/workers-list",
                        'isAuth' => 'STAFFLIST',
                        'parentId' => 16.0,
                    ],
                    [
                        'id' => 16.2,
                        'label' => "VENDOR LIST",
                        'link' => "student/vendor-list",
                        'isAuth' => 'VENDORLIST',
                        'parentId' => 16.0,
                    ],
                    [
                        'id' => 16.3,
                        'label' => "STUDENT LIST",
                        'link' => "student/student-list",
                        'isAuth' => 'STUDENTLIST',
                        'parentId' => 16.0,
                    ]
                ]
            ],

            [
                'id' => 17.0,
                'label' => "STAFF KPI",
                'icon' => "bx-line-chart",
                'isAuth' => 'WKR,WKRR,WKRC,ZONE,PROJECT,WKRPROJECT',
                'subItems' => [
                    [
                        'id' => 17.1,
                        'label' => "WORKER REGISTRATION",
                        'link' => "worker-kpi/worker-registration-list",
                        'isAuth' => 'WKR',
                        'parentId' => 17.0,
                    ],
                    [
                        'id' => 17.2,
                        'label' => "REQUEST LIST",
                        'link' => "worker-kpi/worker-request-list",
                        'isAuth' => 'WKRR',
                        'parentId' => 17.0,
                    ],
                    [
                        'id' => 17.3,
                        'label' => "CLOSED LIST",
                        'link' => "worker-kpi/worker-closed-list",
                        'isAuth' => 'WKRC',
                        'parentId' => 17.0,
                    ],
                    [
                        'id' => 17.4,
                        'label' => "BUILDING",
                        'link' => "worker-kpi/building",
                        'isAuth' => 'ZONE',
                        'parentId' => 17.0,
                    ],
                    [
                        'id' => 17.5,
                        'label' => "LOCATION",
                        'link' => "worker-kpi/location",
                        'isAuth' => 'ZONE',
                        'parentId' => 17.0,
                    ],
                    [
                        'id' => 17.6,
                        'label' => "ZONE",
                        'link' => "worker-kpi/zone",
                        'isAuth' => 'ZONE',
                        'parentId' => 17.0,
                    ],
                    [
                        'id' => 17.7,
                        'label' => "PROJECT",
                        'link' => "worker-kpi/project",
                        'isAuth' => 'PROJECT',
                        'parentId' => 17.0,
                    ],
                    [
                        'id' => 17.8,
                        'label' => "STAFF PROJECT",
                        'link' => "worker-kpi/worker-project",
                        'isAuth' => 'WKRPROJECT',
                        'parentId' => 17.0,
                    ]
                ]
            ],

            [
                'id' => 6.0,
                'label' => "CONFIG",
                'icon' => "bx-cog",
                'isAuth' => 'Role,User,CpnInf,CInf,StateInf,CityInf,DInf,DESInf,LCInf,LInf,RType,BType,RRConf,VinType,LClass,BName,CTEB,LVCInf,ANNInf,DUInf,BRConf,CTConf,WHLConf,APPConf,VCConf,BrrConf,MeSesC,ITempConf,MCConf,OHConf,AWPConf,EVConf,WGConf,GSConf,WAConf,VQR,VIDEO_QUESTION',
                'subItems' => [
                    [
                        'id' => 6.1,
                        'label' => "APP CONFIG",
                        'link' => "config/app-self-config",
                        'isAuth' => 'APPConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.2,
                        'label' => "DASHBOARD UPLOAD",
                        'link' => "config/dashboard-upload",
                        'isAuth' => 'DUInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.3,
                        'label' => "ROLE",
                        'link' => "config/role",
                        'isAuth' => 'Role',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.4,
                        'label' => "MEETING SESSION",
                        'link' => "config/meeting-session",
                        'isAuth' => 'MeSesC',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.5,
                        'label' => "USER",
                        'link' => "config/user-info",
                        'isAuth' => 'User',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.6,
                        'label' => "COMPANY",
                        'link' => "config/company-info",
                        'isAuth' => 'CpnInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.7,
                        'label' => "COUNTRY",
                        'link' => "config/country-info",
                        'isAuth' => 'CInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.8,
                        'label' => "STATE",
                        'link' => "config/state-info",
                        'isAuth' => 'StateInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.9,
                        'label' => "CITY",
                        'link' => "config/city-info",
                        'isAuth' => 'CityInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.10,
                        'label' => "DEPARTMENT",
                        'link' => "config/department-info",
                        'isAuth' => 'DInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.11,
                        'label' => "DESIGNATION",
                        'link' => "config/designation-info",
                        'isAuth' => 'DESInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.12,
                        'label' => "LOCATION ACCESS",
                        'link' => "config/location-info",
                        'isAuth' => 'LCInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.13,
                        'label' => "SUB LOCATION ACCESS",
                        'link' => "config/sub-location-info",
                        'isAuth' => 'SLCInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.14,
                        'label' => "LANE",
                        'link' => "config/lane-info",
                        'isAuth' => 'LInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.15,
                        'label' => "REGISTRATION TYPE",
                        'link' => "config/registration-type",
                        'isAuth' => 'RType',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.16,
                        'label' => "BUSINESS TYPE",
                        'link' => "config/business-type",
                        'isAuth' => 'BType',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.17,
                        'label' => "REJECT REASON",
                        'link' => "config/reject-reason",
                        'isAuth' => 'RRConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.18,
                        'label' => "VEHICLE TYPE",
                        'link' => "config/vin-type",
                        'isAuth' => 'VinType',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.19,
                        'label' => "LICENSE CLASS",
                        'link' => "config/license-class",
                        'isAuth' => 'LClass',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.20,
                        'label' => "BANK NAME",
                        'link' => "config/bank-name",
                        'isAuth' => 'BName',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.21,
                        'label' => "SUB BUSINESS TYPE",
                        'link' => "config/sub-categories-info",
                        'isAuth' => 'CateInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.22,
                        'label' => "CARD TEMPLATE",
                        'isAuth' => 'CTP',
                        'parentId' => 6.0,
                        'icon' => "bx-cog",
                        'subItems' => [
                            [
                                'id' => 6.23,
                                'label' => "CARD TEMPLATE",
                                'link' => "config/card-template-preprint",
                                'isAuth' => 'CTP',
                                'parentId' => 6.22,
                            ]
                        ]
                    ],
                    [
                        'id' => 6.24,
                        'label' => "LOCATION VISITED",
                        'link' => "config/location-visited-info",
                        'isAuth' => 'LVCInf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.25,
                        'label' => "BLACKLIST REASON",
                        'link' => "config/blacklist-reason",
                        'isAuth' => 'BRConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.26,
                        'label' => "RELEASE REASON",
                        'link' => "config/release-reason",
                        'isAuth' => 'BrrConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.27,
                        'label' => "CARGO TYPE",
                        'link' => "config/cargo-type",
                        'isAuth' => 'CTConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.28,
                        'label' => "WAREHOUSE LOCATION",
                        'link' => "config/warehouse-loc",
                        'isAuth' => 'WHLConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.29,
                        'label' => "VISITOR CARD",
                        'link' => "config/visitor-card",
                        'isAuth' => 'VCConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.30,
                        'label' => "MODULE CONFIG",
                        'link' => "config/module-config",
                        'isAuth' => 'MCConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.31,
                        'label' => "OPERATING HOURS",
                        'link' => "config/operating-hours",
                        'isAuth' => 'OHConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.32,
                        'label' => "INSPECTION TEMPLATE",
                        'link' => "config/inspection-template",
                        'isAuth' => 'ITempConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.33,
                        'label' => "SUMMON OFFENCE RATE",
                        'link' => "config/offence-rate",
                        'isAuth' => 'OfRConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.34,
                        'label' => "SHIFT",
                        'link' => "config/shift-info",
                        'isAuth' => 'ShftConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.35,
                        'label' => "ADDITIONAL PERMIT TYPE",
                        'link' => "config/add-permit-type",
                        'isAuth' => 'AWPConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.36,
                        'label' => "EVACUATION",
                        'link' => "config/evacuation-config",
                        'isAuth' => 'EVConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.37,
                        'label' => "STAFF GROUPS",
                        'link' => "config/worker-groups-config",
                        'isAuth' => 'WGConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.43,
                        'label' => "PUBLIC HOLIDAYS",
                        'link' => "config/public-holiday",
                        'isAuth' => 'PUBLIC_HOLIDAY',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.38,
                        'label' => "GROUPS SHIFT",
                        'link' => "config/groups-shift-config",
                        'isAuth' => 'GSConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.39,
                        'label' => "STAFF AVAILABILITY",
                        'link' => "config/worker-availability-config",
                        'isAuth' => 'WAConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.42,
                        'label' => "BRANCH OFFICE",
                        'link' => "config/branch-office",
                        'isAuth' => 'BRANCH_OFFICE',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.40,
                        'label' => "VIDEO & QUESTIONNAIRE",
                        'link' => "config/training-vid-conf",
                        'isAuth' => 'VIDEO_QUESTION',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.41,
                        'label' => "EVACUATION FEATURE",
                        'link' => "config/evacuation-feature-conf",
                        'isAuth' => 'MCConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.42,
                        'label' => "DOOR INFO",
                        'link' => "config/door-info",
                        'isAuth' => 'WGConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.43,
                        'label' => "VISIT REASON",
                        'link' => "config/visit-reason",
                        'isAuth' => 'WGConf',
                        'parentId' => 6.0,
                    ],
                    [
                        'id' => 6.44,
                        'label' => "VISITOR QR CODE",
                        'link' => "config/visitor-qr-code",
                        'isAuth' => 'VQR',
                        'parentId' => 6.0,
                    ],
                ],
            ],
        ];
    }
}
