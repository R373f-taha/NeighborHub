<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Capture security-channel log records in memory without touching the
 * production security log file.
 */
trait CapturesSecurityLog
{
    protected TestHandler $securityLogHandler;

    protected function captureSecurityLog(): void
    {
        $this->securityLogHandler = new TestHandler();

        $logger = Log::channel('security')->getLogger();

        if ($logger instanceof Logger) {
            $logger->setHandlers([$this->securityLogHandler]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function securityRecords(): array
    {
        $records = [];

        foreach ($this->securityLogHandler->getRecords() as $record) {
            if ($record instanceof LogRecord) {
                $records[] = [
                    'message' => $record->message,
                    'level' => $record->level->name,
                    'context' => $record->context,
                ];
            } else {
                $records[] = (array) $record;
            }
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function securityContexts(): array
    {
        return array_map(static fn (array $record): array => $record['context'] ?? [], $this->securityRecords());
    }

    /**
     * @return list<string>
     */
    protected function securityEvents(): array
    {
        return array_map(static fn (array $record): string => (string) ($record['message'] ?? ''), $this->securityRecords());
    }

    protected function serializedSecurityRecords(): string
    {
        return (string) json_encode($this->securityRecords(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
