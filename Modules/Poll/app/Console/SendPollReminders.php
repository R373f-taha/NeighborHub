<?php

namespace Modules\Poll\app\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Jobs\SendPollReminderJob;

class SendPollReminders extends Command
{
    protected $signature = 'polls:send-reminders
                            {--dry-run : Show what would be reminded without actually sending}';

    protected $description = 'Send reminders for polls that will expire in 24 hours';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No notifications will be sent');
            $this->newLine();
        } else {
            $this->info('⏰ Sending poll reminders...');
        }

        Log::info('⏰ Sending poll reminders started', [
            'dry_run' => $isDryRun,
        ]);

        try {
            $targetTime = now()->addHours(24);

            $polls = Poll::query()
                ->where('status', PollStatus::Active)
                ->where('ends_at', '>=', $targetTime->subMinutes(5))
                ->where('ends_at', '<=', $targetTime->addMinutes(5))
                ->whereNull('closed_at')
                ->get();

            $this->info("📊 Found {$polls->count()} polls to remind");

            if ($polls->isEmpty()) {
                $this->info('✅ No polls need reminders.');
                Log::info('✅ No polls to remind');
                return Command::SUCCESS;
            }

            $this->newLine();
            $this->info('📋 Polls Details:');
            $headers = ['ID', 'Title', 'Community ID', 'Ends At'];
            $rows = [];

            foreach ($polls as $poll) {
                $rows[] = [
                    $poll->id,
                    $poll->title,
                    $poll->community_id,
                    $poll->ends_at->format('Y-m-d H:i:s'),
                ];
            }

            $this->table($headers, $rows);
            $this->newLine();

            if ($isDryRun) {
                $this->info('🔍 DRY RUN: Would send reminders for ' . $polls->count() . ' polls.');
                return Command::SUCCESS;
            }

            $this->info('📨 Sending reminders...');
            $sentCount = 0;
            $progressBar = $this->output->createProgressBar($polls->count());
            $progressBar->start();

            foreach ($polls as $poll) {
                try {
                    $this->line("\n📨 Sending reminder for: {$poll->title} (ID: {$poll->id})");

                    SendPollReminderJob::dispatch($poll)
                        ->onQueue('notifications');

                    $sentCount++;
                    $progressBar->advance();

                    Log::info('📨 Dispatched poll reminder job', [
                        'poll_id' => $poll->id,
                        'poll_title' => $poll->title,
                    ]);

                } catch (\Exception $e) {
                    $this->error("\n❌ Failed to send reminder for poll: {$poll->title}");
                    $this->error("Error: {$e->getMessage()}");

                    Log::error('❌ Failed to send poll reminder', [
                        'poll_id' => $poll->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $progressBar->finish();
            $this->newLine(2);
            $this->info("✅ Completed: Sent {$sentCount} reminders");

            Log::info('✅ Poll reminders completed', [
                'total' => $polls->count(),
                'sent' => $sentCount,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ An error occurred while sending reminders');
            $this->error("Error: {$e->getMessage()}");

            Log::error('❌ Poll reminders failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
