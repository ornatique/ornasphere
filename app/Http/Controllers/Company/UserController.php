<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function store(Request $r)
    {
        $company = auth()->user()->company;

        /*
    LIMIT CHECK
    */
        if ($company->users()->count() >= $company->max_users) {
            return back()->withErrors('Contact superadmin to add more users');
        }

        $user = User::create([
            'name' => $r->name,
            'email' => $r->email,
            'password' => Hash::make('123456'),
            'company_id' => $company->id
        ]);

        $user->assignRole($r->role);

        return back();
    }
}
