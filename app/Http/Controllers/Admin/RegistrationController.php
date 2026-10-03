<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Validasi registrasi user — halaman admin/operator.
 */
class RegistrationController extends Controller
{
    public function index(Request $request): Response
    {
        $statuses = array_keys(config('noc.registration_statuses'));
        $filter = (string) $request->query('status', '');

        $users = User::query()
            ->when(in_array($filter, $statuses, true), fn ($q) => $q->where('status', $filter))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Registrasi', [
            'users' => $users,
            'statuses' => config('noc.registration_statuses'),
            'filters' => ['status' => in_array($filter, $statuses, true) ? $filter : ''],
        ]);
    }

    /**
     * Setujui / tolak registrasi (status saja — role tidak diubah di sini).
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([User::STATUS_APPROVED, User::STATUS_REJECTED])],
        ]);

        if ($user->canReview()) {
            return back()->with('error', 'Akun admin/operator tidak bisa divalidasi lewat halaman ini.');
        }

        $user->update(['status' => $data['status']]);

        $label = config('noc.registration_statuses.'.$data['status'], $data['status']);

        return back()->with('success', 'Registrasi '.$user->name.' → '.$label.'.');
    }
}
