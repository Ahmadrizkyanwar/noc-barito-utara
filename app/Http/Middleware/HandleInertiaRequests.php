<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Props default yang tersedia di setiap halaman Inertia.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Lonceng notifikasi in-app (hanya saat login)
            'notifications' => fn () => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()
                    ->latest()
                    ->take(8)
                    ->get(['id', 'type', 'data', 'read_at', 'created_at'])
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'read' => $n->read_at !== null,
                        'title' => $n->data['title'] ?? 'Notifikasi',
                        'body' => $n->data['body'] ?? '',
                        'link' => $n->data['link'] ?? null,
                        'kind' => $n->data['kind'] ?? null,
                        'created_at' => $n->created_at->toIso8601String(),
                    ]),
            ] : null,
        ];
    }
}
