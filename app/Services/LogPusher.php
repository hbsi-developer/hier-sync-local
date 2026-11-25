<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pushes unsent attendance logs to Hier Payroll, oldest first, in batches.
 *
 * Contract (POST {url}{sync.path}; connection check: GET {url}{sync.status_path}, HTTP Basic Auth, JSON):
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

    /** Calls the devices route to prove URL, credentials and server are fine. Changes no state. */
    public function test(): array
    {
        $result = $this->runTest();
        SyncLog::record('test', $result['ok'] ? 'success' : 'error', $result['message']);

        return $result;
    }

    private function runTest(): array
    {
        $config = $this->credentials();
        if (is_string($config)) {
            return ['ok' => false, 'message' => $config];
        }

        try {
            $response = $this->get($config);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->describe($e)];
        }

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Connection OK. Credentials accepted.'];
        }

        return ['ok' => false, 'message' => $this->failureMessage($response)];
    }

    /**
     * Auto run (no dates): every unsent log, plus every log of today (resent each interval).
     * Range run ($from/$to): every log in that range, already sent or not.
     * Runs until nothing is left, a batch fails, or the per-run cap is hit. Never throws.
     */
    public function push(bool $force = false, ?string $from = null, ?string $to = null, bool $scheduled = false): array
    {
        $config = $this->credentials();
        if (is_string($config)) {
            SyncLog::record('skipped', 'warning', $config, dedupe: true);

            return ['ok' => false, 'sent' => 0, 'message' => $config, 'skipped' => true];
        }

        $next = (int) Setting::get('push_next_attempt_at', 0);
        if (! $force && $next > time()) {
            SyncLog::record('skipped', 'warning', 'Waiting before retry after a failed push.', dedupe: true);

            return ['ok' => false, 'sent' => 0, 'message' => 'Waiting before retry.', 'skipped' => true];
        }
        if ($scheduled && time() - (int) Setting::get('push_last_run_ts', 0) < $this->intervalMinutes() * 60 - 5) {
            return ['ok' => true, 'sent' => 0, 'message' => 'Not due yet.', 'skipped' => true, 'idle' => true];
        }
        Setting::put('push_last_run_ts', time());

        $isRange = (bool) ($from || $to);
        $cap = (int) config('sync.max_batches_per_run');
        $batches = 0;
        $sent = 0;
        $rejected = 0;
        $errors = [];
        $rangeText = ($from || $to) ? ' for '.($from ?: 'the start').' to '.($to ?: 'today') : '';

        try {
            // The server takes one device per request, so send device by device.
            $deviceIds = $this->queue($from, $to)->reorder()->distinct()->pluck('device_id');

            foreach ($deviceIds as $deviceId) {
                $cursor = null;
                $devSent = 0;
                $devModel = null;
                $devStart = microtime(true);
                while ($isRange || $batches < $cap) {
                    $rows = $this->queue($from, $to, $cursor)->where('device_id', $deviceId)->limit($this->batchSize())->get();
                    if ($rows->isEmpty()) {
                        break;
                    }
                    $batches++;
                    $devModel = $rows->first()->device;
                    $serial = (string) $devModel->serial_number;

                    try {
                        $response = $this->post($config, $serial, $rows->map(fn (AttendanceLog $l) => [
                            'enrollNumber' => (string) $l->enroll_number,
                            'punchedAt' => $l->punched_at->format('Y-m-d H:i:s'),
                            'inOutMode' => (int) $l->in_out_mode,
                            'verifyMode' => $l->verify_mode,
                            'workCode' => $l->work_code,
                        ])->all());
                    } catch (\Throwable $e) {
                        $errors[$serial] = $this->describe($e);
                        break;
                    }

                    if (! $response->successful() || $response->json('success') === false) {
                        $errors[$serial] = $this->failureMessage($response);
                        break;
                    }

                    // The server confirmed the whole device batch.
                    AttendanceLog::whereIn('id', $rows->pluck('id'))->update(['sent_at' => now(), 'push_error' => null]);
                    $sent += $rows->count();
                    $devSent += $rows->count();
                    $last = $rows->last();
                    $cursor = [$last->punched_at->format('Y-m-d H:i:s'), $last->id];
                }

                if ($devModel) {
                    $ms = (int) round((microtime(true) - $devStart) * 1000);
                    $devError = $errors[(string) $devModel->serial_number] ?? null;
                    if ($devError) {
                        SyncLog::record('push', 'error', $devError, $devModel, $devSent, $ms);
                    } elseif ($devSent > 0) {
                        SyncLog::record('push', 'success', "Sent {$devSent} record(s){$rangeText}.", $devModel, $devSent, $ms);
                    }
                }
            }
        } catch (\Throwable $e) {
            SyncLog::record('push', 'error', $this->describe($e), null, $sent);

            return $this->fail($sent, $this->describe($e));
        }

        if ($errors) {
            $detail = collect($errors)->map(fn ($m, $serial) => "{$serial}: {$m}")->implode(' | ');

            return $this->fail($sent, Str::limit(($sent ? "Sent {$sent}, but " : '').$detail, 240, '…'));
        }

        $range = $rangeText;

        if ($sent === 0 && $rejected === 0) {
            return ['ok' => true, 'sent' => 0, 'message' => "Nothing to send{$range}.", 'idle' => true];
        }

        $message = "Sent {$sent} record(s){$range}".($rejected ? ", {$rejected} rejected by the server" : '').'.';
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

    /**
     * @param  string|null  $from  Y-m-d, inclusive (device local date)
     * @param  string|null  $to  Y-m-d, inclusive
     * @param  array{0:string,1:int}|null  $cursor  [punched_at, id] of the last row already handled in this run
     */
    private function queue(?string $from = null, ?string $to = null, ?array $cursor = null): Builder
    {
        $q = AttendanceLog::with('device')
            ->whereHas('device', fn (Builder $d) => $d->whereNotNull('serial_number')->where('serial_number', '!=', ''));

        if ($from || $to) {
            $q->when($from, fn (Builder $b) => $b->where('punched_at', '>=', $from.' 00:00:00'))
                ->when($to, fn (Builder $b) => $b->where('punched_at', '<=', $to.' 23:59:59'));
        } else {
            $q->where(fn (Builder $w) => $w
                ->where(fn (Builder $u) => $u->whereNull('sent_at')->whereNull('push_error'))
                ->orWhere('punched_at', '>=', now()->toDateString().' 00:00:00'));
        }

        if ($cursor) {
            $q->where(fn (Builder $w) => $w
                ->where('punched_at', '>', $cursor[0])
                ->orWhere(fn (Builder $x) => $x->where('punched_at', $cursor[0])->where('id', '>', $cursor[1])));
        }

        return $q->orderBy('punched_at')->orderBy('id');
    }

    public function intervalMinutes(): int
    {
        return max(1, min(60, (int) Setting::get('push_interval', 2)));
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

    private function get(array $config)
    {
        return Http::withBasicAuth($config['username'], $config['password'])
            ->withUserAgent('HierSyncLocal/'.config('app.version'))
            ->acceptJson()
            ->timeout((int) config('sync.timeout'))
            ->connectTimeout((int) config('sync.connect_timeout'))
            ->withoutRedirecting()
            ->get($config['url'].config('sync.status_path'));
    }

    private function post(array $config, string $deviceSerial, array $logs)
    {
        return Http::withBasicAuth($config['username'], $config['password'])
            ->withUserAgent('HierSyncLocal/'.config('app.version'))
            ->acceptJson()->asJson()
            ->timeout((int) config('sync.timeout'))
            ->connectTimeout((int) config('sync.connect_timeout'))
            ->withoutRedirecting() // never replay credentials to a redirect target
            ->post($config['url'].config('sync.path'), [
                'deviceSerial' => $deviceSerial,
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
