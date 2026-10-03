<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['email']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'no_hp';
        if ($field === 'email') {
            $login = strtolower($login);
        }

        if (! Auth::attempt([$field => $login, 'password' => $data['password'], 'aktif' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email/No. HP atau password salah, atau akun dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
