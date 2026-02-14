<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CompanyDashboardController extends Controller
{
    public function index()
    {
        $cid = auth()->user()->company_id;

        return view('company.dashboard', [
            'users' => User::where('company_id', $cid)->count()
        ]);
    }
}
