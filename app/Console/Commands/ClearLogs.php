<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clear {--file=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear logs from storage/logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->option('file');
        if (!$file) {
            $this->error('please add --file=filename.log');
        } else {
            if ($file === 'all') {
                $files = scandir(storage_path('logs'));
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    $logPath = storage_path("logs/$file");
                    if (file_exists($logPath) && $file !== '.gitignore') {
                        file_put_contents($logPath, '');
                        $this->info('Logs have been cleared! File: ' . $file);
                    } else {
                        $this->error('No logs to clear!');
                    }
                }
                return;
            } else {
                $logPath = storage_path("logs/$file");
                if (file_exists($logPath)) {
                    file_put_contents($logPath, '');
                    $this->info('Logs have been cleared! File: ' . $file);
                } else {
                    $this->error('No logs to clear!'.$file);
                }
            }
        }
    }
}
