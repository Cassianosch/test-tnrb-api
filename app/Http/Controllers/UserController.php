<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function store(UserRequest $request)
    {
        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        return response()->json($newUser);
    }

    public function update(Request $request)
    {
        $user_to_update = User::findOrFail(Auth::id());
        $user_to_update->fill([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user_to_update->save();
        return response()->json($user_to_update);
    }
}
