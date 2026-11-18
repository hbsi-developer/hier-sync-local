<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pushes unsent attendance logs to Hier Payroll, oldest first, in batches.
 *
 * Contract (POST {url}{sync.path}, HTTP Basic Auth, JSON):
 *   request : { installation_id, logs: [ { ref, device_serial, enroll_number, punched_at,
 *               verify_mode, in_out_mode, work_code } ] }          (punched_at = device local time, Y-m-d H:i:s)
 *   response: { accepted: [ref, ...], rejected: [ { ref, reason } ] }   (2xx)
 * Only refs listed in "accepted" are marked sent. "rejected" rows are parked (push_error) so they
 * do not block the queue. Anything else stays unsent and is retried with backoff.
 */
class LogPusher
{
    public function installationId(): string
    {
        $id = Setting::get('installation_id');
        if (! $id) {
            $id = (string) Str::uuid();
            Setting::put('installation_id', $id);
        }

        return $id;
    }

    /** Rows waiting to be sent (excludes rejected ones). */
    public function unsentCount(): int
    {
        return AttendanceLog::whereNull('sent_at')->whereNull('push_error')->count();
    }

    public function rejectedCount(): int
    {
        return AttendanceLog::whereNull('sent_at')->whereNotNull('push_error')->count();
    }

    /** Unsent rows whose device has no serial number yet; they cannot be identified on the server. */
    public function waitingForSerialCount(): int
    {
        return AttendanceLog::whereNull('sent_at')->whereNull('push_error')
            ->whereHas('device', fn (Builder $q) => $q->whereNull('serial_number')->orWhere('serial_number', ''))
            ->count();
    }

    public function retryRejected(): int
    {
        return AttendanceLog::whereNull('sent_at')->whereNotNull('push_error')->update(['push_error' => null]);
    }

    /** Validates a base URL: https only (plain http only in a local environment), no embedded credentials. */
    public static function normalizeUrl(string $url): ?string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (! $parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'https' && ! ($scheme === 'http' && app()->environment('local'))) {
            return null;
        }

        return $url;
    }

    /** Sends an empty batch to prove URL, credentials and server are fine. Changes no state. */
    public function test(): array
    {
        $config = $this->credentials();
        if (is_string($config)) {
            return ['ok' => false, 'message' => $config];
        }

        try {
            $response = $this->post($config, []);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->describe($e)];
        }

        if ($response->successful() && is_array($response->json('accepted'))) {
            return ['ok' => true, 'message' => 'Connection OK. Credentials accepted.'];
        }

        return ['ok' => false, 'message' => $this->failureMessage($response)];
    }

    /** Runs until the queue is empty, a batch fails, or the per-run cap is hit. Never throws. */
    public function push(bool $force = false): array
    {
        $config = $this->credentials();
        if (is_string($config)) {
            return ['ok' => false, 'sent' => 0, 'message' => $config, 'skipped' => true];
        }

        $next = (int) Setting::get('push_next_attempt_at', 0);
        if (! $force && $next > time()) {
            return ['ok' => false, 'sent' => 0, 'message' => 'Waiting before retry.', 'skipped' => true];
        }

        $sent = 0;
        $rejected = 0;

        try {
            for ($i = 0; $i < (int) config('sync.max_batches_per_run'); $i++) {
                $rows = $this->queue()->limit($this->batchSize())->get();
                if ($rows->isEmpty()) {
                    break;
                }

                $response = $this->post($config, $rows->map(fn (AttendanceLog $l) => [
                    'ref' => $l->id,
                    'device_serial' => $l->device->serial_number,
                    'enroll_number' => $l->enroll_number,
                    'punched_at' => $l->punched_at->format('Y-m-d H:i:s'),
                    'verify_mode' => $l->verify_mode,
                    'in_out_mode' => $l->in_out_mode,
                    'work_code' => $l->work_code,
                ])->all());

                if (! $response->successful() || ! is_array($response->json('accepted'))) {
                    throw new \RuntimeException($this->failureMessage($response));
                }

                $ids = $rows->pluck('id')->all();
                $accepted = array_values(array_intersect($ids, array_map('intval', $response->json('accepted'))));
                $reject = collect($response->json('rejected') ?? [])
                    ->filter(fn ($r) => is_array($r) && in_array((int) ($r['ref'] ?? 0), $ids, true))
                    ->mapWithKeys(fn ($r) => [(int) $r['ref'] => Str::limit((string) ($r['reason'] ?? 'Rejected'), 200, '…')]);

                // Mark only what the server confirmed.
                AttendanceLog::whereIn('id', $accepted)->update(['sent_at' => now(), 'push_error' => null]);
                foreach ($reject->except($accepted) as $id => $reason) {
                    AttendanceLog::whereKey($id)->update(['push_error' => $reason]);
                }
                $sent += count($accepted);
                $rejected += $reject->except($accepted)->count();

                if (count($accepted) + $reject->except($accepted)->count() === 0) {
                    // No progress: stop instead of resending the same batch forever.
                    throw new \RuntimeException('Server did not confirm any record of the batch.');
                }
            }
        } catch (\Throwable $e) {
            return $this->fail($sent, $this->describe($e));
        }

        if ($sent === 0 && $rejected === 0) {
            return ['ok' => true, 'sent' => 0, 'message' => 'Nothing to send.', 'idle' => true];
        }

        $message = "Sent {$sent} record(s)".($rejected ? ", {$rejected} rejected by the server" : '').'.';
        Setting::put('push_failures', 0);
        Setting::put('push_next_attempt_at', 0);
        $this->record(true, $message);

        return ['ok' => true, 'sent' => $sent, 'message' => $message];
    }

    private function fail(int $sent, string $message): array
    {
        $failures = (int) Setting::get('push_failures', 0) + 1;
        // 2, 4, 8, 16 ... minutes, capped at 30.
        Setting::put('push_failures', $failures);
        Setting::put('push_next_attempt_at', time() + min(1800, 60 * (2 ** $failures)));
        $this->record(false, $message);

        return ['ok' => false, 'sent' => $sent, 'message' => $message];
    }

    private function record(bool $ok, string $message): void
    {
        Setting::put('push_last_at', now()->toDateTimeString());
        Setting::put('push_last_ok', $ok ? '1' : '0');
        Setting::put('push_last_message', Str::limit($message, 240, '…'));
    }

    private function queue(): Builder
    {
        return AttendanceLog::with('device')
            ->whereNull('sent_at')->whereNull('push_error')
            ->whereHas('device', fn (Builder $q) => $q->whereNotNull('serial_number')->where('serial_number', '!=', ''))
            ->orderBy('punched_at')->orderBy('id');
    }

    private function batchSize(): int
    {
        return max(1, min(1000, (int) config('sync.batch_size')));
    }

    /** @return array{url:string,username:string,password:string}|string  A string is the "not ready" reason. */
    private function credentials(): array|string
    {
        if (Setting::get('push_enabled', '0') !== '1') {
            return 'Push to Hier Payroll is turned off.';
        }
        $url = static::normalizeUrl((string) Setting::get('push_url', ''));
        if (! $url) {
            return 'Website URL is missing or not allowed (HTTPS is required).';
        }
        $username = (string) Setting::get('push_username', '');
        $password = (string) config('sync.password');
        if ($username === '' || $password === '') {
            return 'Username or password is not set.';
        }

        return ['url' => $url, 'username' => $username, 'password' => $password];
    }

    private function post(array $config, array $logs)
    {
        return Http::withBasicAuth($config['username'], $config['password'])
            ->acceptJson()->asJson()
            ->timeout((int) config('sync.timeout'))
            ->connectTimeout((int) config('sync.connect_timeout'))
            ->withoutRedirecting() // never replay credentials to a redirect target
            ->post($config['url'].config('sync.path'), [
                'installation_id' => $this->installationId(),
                'logs' => $logs,
            ]);
    }

    private function failureMessage($response): string
    {
        $status = $response->status();
        $detail = $status === 401 ? 'Wrong username or password.' : Str::limit((string) ($response->json('message') ?? ''), 140, '…');

        return trim("Server answered HTTP {$status}. {$detail}");
    }

    private function describe(\Throwable $e): string
    {
        return Str::limit($e->getMessage(), 240, '…');
    }
}
