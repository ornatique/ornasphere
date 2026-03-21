<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // ✅ List Users
    public function index()
    {
        $users = User::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    // ✅ Store User
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ]);
    }

    // ✅ Show Single User
    public function show($id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    // ✅ Update User
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    // ✅ Delete User
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }
}