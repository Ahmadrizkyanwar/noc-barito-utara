<?php

namespace App\Console\Commands;

use App\Services\Network\MonitorPoller;
use Illuminate\Console\Command;

class MonitorPoll extends Command
{
    protected $signature = 'monitor:poll';

    protected $description = 'Poll semua perangkat jaringan (ICMP/SNMP/RouterOS) sekali siklus';

    public function handle(MonitorPoller $poller): int
    {
        $stats = $poller->pollAll();

        $this->info(sprintf(
            'poll selesai: %d perangkat (%d up, %d down)',
            $stats['polled'],
            $stats['up'],
            $stats['down']
        ));

        return self::SUCCESS;
    }
}
