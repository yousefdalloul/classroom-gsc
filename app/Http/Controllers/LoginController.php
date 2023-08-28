<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function create()
    {
        return view('login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'user'=>'required',
            'password'=>'required'
        ]);

        $credentials =[
            'email'=>$request->email,
            'password'=>$request->password,
            'status'=>'active',
        ];

        $result = Auth::attempt($credentials, $request->boolean('remember'));

        if ($result){
            //Authenticated
            return redirect()->intended('/');
        }

        return back()->withInput()->withErrors([
            'email'=>'Invalid credentials'
        ]);
    }
}
