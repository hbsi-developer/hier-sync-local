<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Setting;
use App\Services\LogPusher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LogPushTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://payroll.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('push_enabled', '1');
        Setting::put('push_url', self::URL);
        Setting::put('push_username', 'sync-user');
        config(['sync.password' => 's3cret', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    }

    private function device(?string $serial = 'SN-1'): Device
    {
        return Device::create(['name' => 'Main', 'ip' => '10.0.0.2', 'serial_number' => $serial]);
    }

    private function log(Device $d, string $enroll, string $at): AttendanceLog
    {
        return AttendanceLog::create(['device_id' => $d->id, 'enroll_number' => $enroll, 'punched_at' => $at]);
    }

    private function ackAll(): \Closure
    {
        return fn (Request $r) => Http::response(['success' => true]);
    }

    public function test_sends_oldest_first_with_basic_auth_and_marks_sent(): void
    {
        $d = $this->device();
        $late = $this->log($d, '2', '2026-11-02 08:00:00');
        $early = $this->log($d, '1', '2026-11-01 08:00:00');
        Http::fake([self::URL.'/*' => $this->ackAll()]);

        $r = app(LogPusher::class)->push();

        $this->assertTrue($r['ok']);
        $this->assertSame(2, $r['sent']);
        Http::assertSent(function (Request $req) use ($early, $late) {
            return $req->url() === self::URL.'/api/sync-local/v1/logs'
                && $req->hasHeader('Authorization', 'Basic '.base64_encode('sync-user:s3cret'))
                && $req['deviceSerial'] === 'SN-1'
                && array_column($req['logs'], 'enrollNumber') === ['1', '2']
                && $req['logs'][0]['punchedAt'] === '2026-11-01 08:00:00'
                && $req['logs'][0]['inOutMode'] === 0
                && ! array_key_exists('company_id', $req->data());
        });
        $this->assertNotNull($early->fresh()->sent_at);
        $this->assertNotNull($late->fresh()->sent_at);
    }

    public function test_installation_id_is_stable(): void
    {
        $this->assertSame(app(LogPusher::class)->installationId(), app(LogPusher::class)->installationId());
    }

    public function test_failure_keeps_rows_unsent_and_backs_off(): void
    {
        $d = $this->device();
        $row = $this->log($d, '1', '2026-11-01 08:00:00');
        $down = true;
        Http::fake([self::URL.'/*' => function (Request $r) use (&$down) {
            return $down
                ? Http::response(['message' => 'down'], 500)
                : Http::response(['success' => true]);
        }]);

        $r = app(LogPusher::class)->push();

        $this->assertFalse($r['ok']);
        $this->assertNull($row->fresh()->sent_at);
        $this->assertSame('0', Setting::get('push_last_ok'));
        $this->assertGreaterThan(time(), (int) Setting::get('push_next_attempt_at'));

        // During the backoff nothing is sent; --force overrides it.
        $down = false;
        Http::assertSentCount(1);
        $this->assertTrue(app(LogPusher::class)->push()['skipped']);
        Http::assertSentCount(1);
        $this->assertTrue(app(LogPusher::class)->push(true)['ok']);
        $this->assertNotNull($row->fresh()->sent_at);
        $this->assertSame('0', Setting::get('push_failures'));
    }

    public function test_offline_is_a_clean_failure(): void
    {
        $d = $this->device();
        $row = $this->log($d, '1', '2026-11-01 08:00:00');
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $r = app(LogPusher::class)->push();

        $this->assertFalse($r['ok']);
        $this->assertNull($row->fresh()->sent_at);
    }

    public function test_one_failing_device_does_not_block_the_others(): void
    {
        $good = $this->device('SN-GOOD');
        $bad = $this->device('SN-BAD');
        $a = $this->log($good, '1', '2026-11-01 08:00:00');
        $b = $this->log($bad, '2', '2026-11-01 08:01:00');
        Http::fake([self::URL.'/*' => fn (Request $r) => $r['deviceSerial'] === 'SN-BAD'
            ? Http::response(['success' => false, 'message' => 'Unknown device.'], 404)
            : Http::response(['success' => true])]);

        $r = app(LogPusher::class)->push();

        $this->assertFalse($r['ok']);
        $this->assertSame(1, $r['sent']);
        $this->assertStringContainsString('SN-BAD', $r['message']);
        $this->assertStringContainsString('Unknown device', $r['message']);
        $this->assertNotNull($a->fresh()->sent_at);
        $this->assertNull($b->fresh()->sent_at);
    }

    public function test_batches_by_configured_size(): void
    {
        $d = $this->device();
        foreach (range(1, 5) as $i) {
            $this->log($d, (string) $i, "2026-11-01 08:0{$i}:00");
        }
        config(['sync.batch_size' => 2]);
        Http::fake([self::URL.'/*' => $this->ackAll()]);

        $this->assertSame(5, app(LogPusher::class)->push()['sent']);
        Http::assertSentCount(3);
    }

    public function test_date_range_sends_only_logs_inside_it(): void
    {
        $d = $this->device();
        $before = $this->log($d, '1', '2026-10-31 23:59:59');
        $first = $this->log($d, '2', '2026-11-01 00:00:00');
        $last = $this->log($d, '3', '2026-11-02 23:59:59');
        $after = $this->log($d, '4', '2026-11-03 00:00:00');
        Http::fake([self::URL.'/*' => $this->ackAll()]);

        $r = app(LogPusher::class)->push(true, '2026-11-01', '2026-11-02');

        $this->assertSame(2, $r['sent']);
        $this->assertNull($before->fresh()->sent_at);
        $this->assertNotNull($first->fresh()->sent_at);
        $this->assertNotNull($last->fresh()->sent_at);
        $this->assertNull($after->fresh()->sent_at);
    }

    public function test_date_range_resends_already_sent_logs_across_batches(): void
    {
        $d = $this->device();
        $sent = $this->log($d, '1', '2026-11-01 08:00:00');
        $sent->update(['sent_at' => now()->subDay()]);
        $this->log($d, '2', '2026-11-01 09:00:00');
        $this->log($d, '3', '2026-11-01 10:00:00');
        config(['sync.batch_size' => 2]);
        Http::fake([self::URL.'/*' => $this->ackAll()]);

        $r = app(LogPusher::class)->push(true, '2026-11-01', '2026-11-01');

        $this->assertSame(3, $r['sent']);
        Http::assertSentCount(2);
    }

    public function test_auto_push_resends_todays_logs_and_old_unsent_but_not_old_sent(): void
    {
        $d = $this->device();
        $oldSent = $this->log($d, '1', '2026-11-01 08:00:00');
        $oldSent->update(['sent_at' => now()->subDays(2)]);
        $oldUnsent = $this->log($d, '2', '2026-11-01 09:00:00');
        $todaySent = $this->log($d, '3', now()->format('Y-m-d').' 00:00:01');
        $todaySent->update(['sent_at' => now()->subHour()]);
        Http::fake([self::URL.'/*' => $this->ackAll()]);

        $r = app(LogPusher::class)->push();

        $this->assertSame(2, $r['sent']);
        Http::assertSent(fn (Request $req) => array_column($req['logs'], 'enrollNumber') === ['2', '3']);
    }

    public function test_scheduled_run_waits_for_the_configured_interval(): void
    {
        $this->log($this->device(), '1', '2026-11-01 08:00:00');
        Setting::put('push_interval', '5');
        Http::fake([self::URL.'/*' => $this->ackAll()]);

        $this->assertSame(1, app(LogPusher::class)->push(false, null, null, true)['sent']);
        $this->assertTrue(app(LogPusher::class)->push(false, null, null, true)['skipped']);

        Setting::put('push_last_run_ts', time() - 301);
        $this->assertFalse(app(LogPusher::class)->push(false, null, null, true)['skipped'] ?? false);
    }

    public function test_settings_page_and_payroll_sync_page_render(): void
    {
        $this->get('/settings')->assertOk()->assertSee('Save settings')->assertSee('Device auto-sync')->assertSee('Test connection');
        $this->get('/payroll-sync')->assertOk()->assertSee('Push by date range')->assertDontSee('Save settings');
        $this->get('/devices')->assertOk()->assertDontSee('Every (min. 5 minutes)');
    }

    public function test_interval_setting_is_saved(): void
    {
        $this->post('/payroll-sync', ['url' => 'https://pay.example.com', 'username' => 'u', 'interval' => '10'])->assertSessionHasNoErrors();
        $this->assertSame(10, app(LogPusher::class)->intervalMinutes());
        $this->post('/payroll-sync', ['url' => 'https://pay.example.com', 'username' => 'u', 'interval' => '0'])->assertSessionHasErrors('interval');
    }

    public function test_command_rejects_bad_dates(): void
    {
        $this->artisan('logs:push', ['--from' => 'nope', '--json' => true])->assertFailed();
        $this->artisan('logs:push', ['--from' => '2026-11-05', '--to' => '2026-11-01', '--json' => true])->assertFailed();
    }

    public function test_devices_without_serial_are_not_sent(): void
    {
        $this->log($this->device(null), '1', '2026-11-01 08:00:00');
        Http::fake();

        $r = app(LogPusher::class)->push();

        $this->assertTrue($r['idle']);
        Http::assertNothingSent();
        $this->assertSame(1, app(LogPusher::class)->waitingForSerialCount());
    }

    public function test_plain_http_and_credentials_in_url_are_refused(): void
    {
        $this->assertNull(LogPusher::normalizeUrl('http://payroll.example.com'));
        $this->assertNull(LogPusher::normalizeUrl('https://user:pw@payroll.example.com'));
        $this->assertNull(LogPusher::normalizeUrl('not a url'));
        $this->assertSame('https://payroll.example.com', LogPusher::normalizeUrl(' https://payroll.example.com/ '));

        Setting::put('push_url', 'http://payroll.example.com');
        Http::fake();
        $this->assertFalse(app(LogPusher::class)->test()['ok']);
        Http::assertNothingSent();
    }

    public function test_not_sent_without_password_or_when_off(): void
    {
        $this->log($this->device(), '1', '2026-11-01 08:00:00');
        Http::fake();

        config(['sync.password' => '']);
        $this->assertTrue(app(LogPusher::class)->push()['skipped']);

        config(['sync.password' => 's3cret']);
        Setting::put('push_enabled', '0');
        $this->assertTrue(app(LogPusher::class)->push()['skipped']);
        Http::assertNothingSent();
    }

    public function test_connection_test_sends_empty_batch_and_changes_nothing(): void
    {
        $row = $this->log($this->device(), '1', '2026-11-01 08:00:00');
        $good = true;
        Http::fake([self::URL.'/*' => function () use (&$good) {
            return $good ? Http::response(['status' => 'ok']) : Http::response([], 401);
        }]);

        $this->assertTrue(app(LogPusher::class)->test()['ok']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === self::URL.'/api/sync-local/v1/devices');
        $this->assertNull($row->fresh()->sent_at);

        $good = false;
        $r = app(LogPusher::class)->test();
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Wrong username or password', $r['message']);
    }

    public function test_redirects_are_not_followed(): void
    {
        $this->log($this->device(), '1', '2026-11-01 08:00:00');
        Http::fake([self::URL.'/*' => Http::response('', 302, ['Location' => 'https://evil.example'])]);

        $this->assertFalse(app(LogPusher::class)->push()['ok']);
        Http::assertSentCount(1);
    }

    public function test_command_prints_json_and_password_is_not_in_output(): void
    {
        $this->log($this->device(), '1', '2026-11-01 08:00:00');
        Http::fake([self::URL.'/*' => $this->ackAll()]);

        $this->artisan('logs:push', ['--json' => true])
            ->expectsOutputToContain('"ok":true')
            ->doesntExpectOutputToContain('s3cret')
            ->assertSuccessful();
    }

    public function test_settings_page_saves_and_rejects_http(): void
    {
        $this->post('/payroll-sync', ['url' => 'http://x.example.com', 'username' => 'u'])->assertSessionHasErrors('url');

        $this->post('/payroll-sync', ['url' => 'https://pay.example.com/', 'username' => 'u2', 'enabled' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://pay.example.com', Setting::get('push_url'));
        $this->assertSame('u2', Setting::get('push_username'));

        $this->get('/payroll-sync')->assertOk()->assertSee('Unsent records')->assertDontSee('s3cret');
    }
}
