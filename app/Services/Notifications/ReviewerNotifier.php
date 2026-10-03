<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\VpsRequest;
use App\Notifications\Notice;
use Illuminate\Support\Collection;

/**
 * Pengirim notifikasi in-app ke pihak terkait:
 * - reviewer (admin+operator) untuk event baru / upload kredensial,
 * - user pemilik request untuk hasil review / kredensial siap.
 */
class ReviewerNotifier
{
    /**
     * Semua admin + operator.
     *
     * @return Collection<int, User>
     */
    public function reviewers(): Collection
    {
        return User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERATOR])->get();
    }

    public function registrationReceived(User $user): void
    {
        $this->toReviewers(new Notice(
            'register',
            'Registrasi user baru',
            $user->name.' ('.$user->email.') mendaftar — menunggu validasi.',
            route('admin.registrations.index', [], false),
        ));
    }

    public function vpsRequestReceived(VpsRequest $request): void
    {
        $this->toReviewers(new Notice(
            'vps_request',
            'Request VPS baru',
            $request->code.' — '.$request->instansi.' ('.$request->name.') menunggu review.',
            route('admin.vps.index', [], false),
        ));
    }

    public function vpsStatusUpdated(VpsRequest $request): void
    {
        $label = config('noc.vps_statuses.'.$request->status, $request->status);

        $request->user?->notify(new Notice(
            'vps_status',
            'Request VPS '.$label,
            $request->code.' — '.$label.($request->admin_note ? '. Catatan: '.$request->admin_note : '.'),
            route('vps.index', [], false),
        ));
    }

    public function credentialsReady(VpsRequest $request): void
    {
        $request->user?->notify(new Notice(
            'vps_credentials',
            'Kredensial VPS tersedia',
            'Dokumen kredensial untuk '.$request->code.' telah diunggah — silakan unduh.',
            route('vps.index', [], false),
        ));
    }

    public function credentialsUploaded(VpsRequest $request, User $by): void
    {
        $this->toReviewers(
            new Notice(
                'vps_credentials',
                'Kredensial VPS diunggah',
                $request->code.' — dokumen kredensial diunggah oleh '.$by->name.'.',
                route('admin.vps.index', [], false),
            ),
            except: $by,
        );
    }

    /**
     * Kirim ke semua reviewer (opsional kecuali satu user).
     */
    protected function toReviewers(Notice $notice, ?User $except = null): void
    {
        foreach ($this->reviewers() as $reviewer) {
            if ($except !== null && $reviewer->id === $except->id) {
                continue;
            }

            $reviewer->notify($notice);
        }
    }
}
