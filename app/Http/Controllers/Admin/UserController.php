<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pengguna', [
            'users' => User::orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR, User::ROLE_USER])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'status' => User::STATUS_APPROVED, // dibuat admin → langsung aktif
            'email_verified_at' => now(),      // dibuat admin → email dianggap terverifikasi
        ]);

        return response()->json([
            'ok' => true,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role' => ['sometimes', 'required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR, User::ROLE_USER])],
        ]);

        // Tidak boleh menurunkan peran diri sendiri (mengunci diri sendiri).
        if ($request->user()->id === $user->id && ($data['role'] ?? $user->role) !== User::ROLE_ADMIN) {
            return response()->json([
                'ok' => false,
                'error' => 'Anda tidak bisa menurunkan peran akun sendiri.',
            ], 422);
        }

        // Password hanya diganti bila diisi; KOSONG = biarkan semula.
        // `unset` SELALU dijalankan — jika tidak, 'password' => null ikut fill()
        // dan menyimpan role menjadi error 500 (Column 'password' cannot be null).
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        unset($data['password']);

        $user->fill($data)->save();

        return response()->json([
            'ok' => true,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'ok' => false,
                'error' => 'Anda tidak bisa menghapus akun sendiri.',
            ], 422);
        }

        $user->delete();

        return response()->json(['ok' => true, 'message' => 'Pengguna dihapus.']);
    }
}
