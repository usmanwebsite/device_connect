<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MenuService;
use Illuminate\Support\Facades\Log;

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
            
            return view('dashboard', compact('angularMenu'));
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            $angularMenu = [];
            return view('dashboard', compact('angularMenu'));
        }
    }
}
