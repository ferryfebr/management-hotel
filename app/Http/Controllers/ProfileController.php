<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Form profil owner: ubah nama/email (username) & password sendiri.
     */
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'current_password' => ['required', 'string'],
        ], [
            'email.unique' => 'Email sudah dipakai akun lain.',
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
        ]);

        // Verifikasi password saat ini sebelum mengubah apa pun.
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini salah.']);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        // Ganti password hanya bila field diisi.
        if (! empty($request->input('password'))) {
            $request->validate([
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ], [
                'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
                'password.min' => 'Kata sandi baru minimal 6 karakter.',
            ]);

            $user->update(['password' => Hash::make($request->input('password'))]);
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}