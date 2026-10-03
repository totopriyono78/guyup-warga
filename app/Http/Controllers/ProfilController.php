<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfilController extends Controller
{
    public function edit()
    {
        return view('profil.edit', ['user' => $this->user()]);
    }

    public function update(Request $request)
    {
        $user = $this->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'password_lama' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'password_lama.current_password' => 'Password lama tidak sesuai.',
        ]);

        $user->fill(collect($data)->only(['name', 'email', 'no_hp'])->all());
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('sukses', 'Profil diperbarui.');
    }
}
