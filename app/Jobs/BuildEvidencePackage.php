<?php

namespace App\Jobs;

use App\Domain\Evidence\EvidencePackageBuilder;
use App\Domain\Notifications\Notifier;
use App\Models\EvidencePackage;
use App\Notifications\SmmNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildEvidencePackage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1700;

    public function __construct(public int $packageId) {}

    public function handle(EvidencePackageBuilder $builder, Notifier $notifier): void
    {
        $pkg = EvidencePackage::find($this->packageId);
        if (! $pkg || $pkg->status !== 'antri') {
            return;
        }
        @set_time_limit(1700);
        $pkg = $builder->build($pkg);
        $notifier->send($pkg->creator, new SmmNotification(
            $pkg->status === 'selesai' ? 'Paket Bukti siap diunduh' : 'Paket Bukti gagal dibuat',
            [$pkg->status === 'selesai' ? 'Paket Bukti '.$pkg->date_from->format('d-m-Y').' s.d. '.$pkg->date_to->format('d-m-Y').' sudah selesai.' : 'Penyebab: '.$pkg->error],
            'Buka', route('evidence.index'), $pkg->status === 'selesai' ? 'success' : 'danger'
        ));
    }
}
