<?php

namespace App\Console\Commands;

use App\Services\LogPusher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PushLogs extends Command
{
    protected $signature = 'logs:push
        {--force : Ignore the retry backoff}
        {--test : Only check the connection and credentials (sends an empty batch)}
        {--batch= : Records per request (default from sync.batch_size)}
        {--scheduled : Called by the timer: run only when the configured interval has passed}
        {--from= : Only send logs punched on or after this date (Y-m-d)}
        {--to= : Only send logs punched on or before this date (Y-m-d)}
        {--json : Print the result as one JSON line}';

    protected $description = 'Send unsent attendance logs to Hier Payroll';

    public function handle(LogPusher $pusher): int
    {
        if ($this->option('batch')) {
            config(['sync.batch_size' => (int) $this->option('batch')]);
        }

        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;
        foreach (['from' => $from, 'to' => $to] as $name => $value) {
            if ($value !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $this->finish(['ok' => false, 'sent' => 0, 'message' => "--{$name} must be a date like 2026-11-01."]);
            }
        }
        if ($from && $to && $from > $to) {
            return $this->finish(['ok' => false, 'sent' => 0, 'message' => 'The start date is after the end date.']);
        }

        if ($this->option('test')) {
            return $this->finish($pusher->test());
        }

        // The desktop shell ticks every 2 minutes; never overlap with a run that is still going.
        $lock = Cache::lock('logs-push', 600);
        if (! $lock->get()) {
            return $this->finish(['ok' => true, 'sent' => 0, 'message' => 'Another push is already running.', 'skipped' => true]);
        }

        try {
            return $this->finish($pusher->push((bool) $this->option('force'), $from, $to, (bool) $this->option('scheduled')));
        } finally {
            $lock->release();
        }
    }

    private function finish(array $result): int
    {
        $this->line($this->option('json') ? json_encode($result) : $result['message']);

        return ($result['ok'] || ! empty($result['skipped'])) ? self::SUCCESS : self::FAILURE;
    }
}
