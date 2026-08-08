<?php

declare(strict_types=1);

namespace Modules\Poll\App\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Jobs\CloseExpiredPollJob;

class CloseExpiredPolls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polls:close-expired
                            {--dry-run : Show what would be closed without actually closing}
                            {--sync : Run synchronously without queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close all expired polls automatically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isSync = $this->option('sync');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        } else {
            $this->info('🔄 Starting: Closing expired polls...');
        }

        Log::info('🔄 Auto-close expired polls started', [
            'dry_run' => $isDryRun,
            'sync' => $isSync,
        ]);

        try {
            $expiredPolls = Poll::query()
                ->where('status', PollStatus::Active)
                ->where('ends_at', '<', now())
                ->get();

            $this->info("📊 Found {$expiredPolls->count()} expired polls");

            if ($expiredPolls->isEmpty()) {
                $this->info('✅ No expired polls found.');
                Log::info('✅ No expired polls to close');
                return Command::SUCCESS;
            }

            $this->newLine();
            $this->info('📋 Expired Polls Details:');
            $this->line(str_repeat('-', 60));

            $headers = ['ID', 'Title', 'Community ID', 'Ends At'];
            $rows = [];

            foreach ($expiredPolls as $poll) {
                $rows[] = [
                    $poll->id,
                    $poll->title,
                    $poll->community_id,
                    $poll->ends_at->format('Y-m-d H:i:s'),
                ];
            }

            $this->table($headers, $rows);
            $this->line(str_repeat('-', 60));
            $this->newLine();

            if ($isDryRun) {
                $this->info('🔍 DRY RUN: Would close ' . $expiredPolls->count() . ' polls.');
                $this->info('✅ To actually close them, run without --dry-run option.');
                return Command::SUCCESS;
            }

            // ✅ إغلاق الاستطلاعات
            $this->info('🔒 Closing expired polls...');
            $closedCount = 0;

            $progressBar = $this->output->createProgressBar($expiredPolls->count());
            $progressBar->start();

            foreach ($expiredPolls as $poll) {
                try {
                    $this->line("\n🔒 Closing poll: {$poll->title} (ID: {$poll->id})");

                    if ($isSync) {
                        $job = new CloseExpiredPollJob($poll);
                        $job->handle();
                    } else {
                        CloseExpiredPollJob::dispatch($poll)
                            ->onQueue('polls');
                    }

                    $closedCount++;
                    $progressBar->advance();

                    Log::info('📨 Dispatched close expired poll job', [
                        'poll_id' => $poll->id,
                        'poll_title' => $poll->title,
                        'sync' => $isSync,
                    ]);

                } catch (\Exception $e) {
                    $this->error("\n❌ Failed to close poll: {$poll->title}");
                    $this->error("Error: {$e->getMessage()}");

                    Log::error('❌ Failed to dispatch close poll job', [
                        'poll_id' => $poll->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("✅ Completed: Dispatched {$closedCount} close jobs");

            Log::info('✅ Auto-close completed', [
                'total' => $expiredPolls->count(),
                'closed' => $closedCount,
                'sync' => $isSync,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ An error occurred while closing expired polls');
            $this->error("Error: {$e->getMessage()}");

            Log::error('❌ Auto-close failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
