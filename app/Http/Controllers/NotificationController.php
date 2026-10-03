<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Lonceng notifikasi in-app — tandai sudah dibaca.
 */
class NotificationController extends Controller
{
    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        if ($notification->notifiable_type !== $request->user()->getMorphClass()
            || (int) $notification->notifiable_id !== $request->user()->id) {
            abort(404);
        }

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back();
    }
}
