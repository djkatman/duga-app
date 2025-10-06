<?php
// app/Console/Kernel.php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // 1) 新着順：毎日 03:10 に直近数ページを同期
        $schedule->command('duga:sync --sort=new --pages=3 --hits=60')
            ->dailyAt('03:10')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_duga_sync.log'));

        // 2) 人気順：毎日 03:40 に補助的に回す（重複対策で時刻をずらす）
        $schedule->command('duga:sync --sort=favorite --pages=3 --hits=60')
            ->dailyAt('03:40')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_duga_sync.log'));

        // 3) 軽い増分（新着1ページ）を 4時間毎に回し取りこぼし防止
        $schedule->command('duga:sync --sort=new --pages=1 --hits=60')
            ->everyFourHours()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_duga_sync.log'));

        // (任意) 健康チェック：1日1回だけログに記録
        $schedule->call(fn() => \Log::info('[scheduler] alive'))->dailyAt('00:05');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}