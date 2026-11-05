<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class RetryFailedNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:retry-failed {--limit=50 : Maximum number of failed notifications to retry} {--max-retries=3 : Maximum retries allowed}';

    /**
     * The console command description.
     */
    protected $description = 'Retry failed notification deliveries that have not exceeded the max retry threshold';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $service): int
    {
        $limit = (int) $this->option('limit');
        $maxRetries = (int) $this->option('max-retries');

        $failedLogs = NotificationLog::where('status', 'failed')
            ->where('retry_count', '<', $maxRetries)
            ->oldest()
            ->limit($limit)
            ->get();

        if ($failedLogs->isEmpty()) {
            $this->info('No failed notifications eligible for retry.');
            return Command::SUCCESS;
        }

        $this->info("Found {$failedLogs->count()} failed notification(s) to retry...");

        $successCount = 0;
        $failedCount = 0;

        foreach ($failedLogs as $log) {
            $result = $service->retryFailedLog($log);
            if ($result) {
                $successCount++;
                $this->line("<info>[SUCCESS]</info> Log #{$log->id} ({$log->channel}) to {$log->recipient}");
            } else {
                $failedCount++;
                $this->line("<error>[FAILED]</error> Log #{$log->id} ({$log->channel}) to {$log->recipient} - Error: {$log->error_message}");
            }
        }

        $this->info("Retry process completed: {$successCount} succeeded, {$failedCount} failed.");

        return Command::SUCCESS;
    }
}
