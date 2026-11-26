<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceUser;
use App\Models\SyncLog;
use App\Services\ZkemClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEnabledTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $enabled = true): DeviceUser
    {
        $device = Device::create(['name' => 'Door', 'ip' => '127.0.0.1', 'port' => 4370]);

        return DeviceUser::create([
            'device_id' => $device->id, 'enroll_number' => '7', 'name' => 'Test User',
            'card_number' => '12345', 'enabled' => $enabled,
        ]);
    }

    /** Swap the SDK bridge for a fake that records what it was asked to write. */
    private function fakeDevice(bool $deviceState = true): object
    {
        $spy = new class($deviceState) extends ZkemClient
        {
            public array $calls = [];

            /** When set, the "device" stores this name regardless of what it was sent. */
            public ?string $storedName = null;

            /** What a user pull returns. */
            public array $deviceUsers = [];

            public function users(): array
            {
                return $this->deviceUsers;
            }

            public function __construct(public bool $deviceState)
            {
                parent::__construct();
            }

            public function connect(string $ip, int $port = 4370): static
            {
                return $this;
            }

            public function setUser(string $enrollNumber, string $cardNumber, ?string $name = null, ?bool $enabled = null): array
            {
                $this->calls[] = [$enrollNumber, $cardNumber, $name, $enabled];

                return ['enroll_number' => $enrollNumber, 'name' => $this->storedName ?? $name ?? 'Test User', 'card_number' => $cardNumber, 'enabled' => $this->deviceState = $enabled ?? $this->deviceState];
            }
        };
        $this->app->bind(ZkemClient::class, fn () => $spy);

        return $spy;
    }

    public function test_user_can_be_disabled_from_the_edit_form(): void
    {
        $user = $this->user(true);
        $spy = $this->fakeDevice();

        $this->put(route('users.update', $user), ['name' => 'Test User', 'card_number' => '12345', 'enabled' => '0'])
            ->assertSessionHas('ok');

        $this->assertSame(['7', '12345', 'Test User', false], $spy->calls[0]);
        $this->assertFalse($user->fresh()->enabled);
        $this->assertSame(1, SyncLog::where('type', 'settings')->where('message', 'like', '%disabled%')->count());
    }

    public function test_user_can_be_enabled_again(): void
    {
        $user = $this->user(false);
        $spy = $this->fakeDevice();

        $this->put(route('users.update', $user), ['card_number' => '12345', 'enabled' => '1'])->assertSessionHas('ok');

        $this->assertTrue($spy->calls[0][3]);
        $this->assertTrue($user->fresh()->enabled);
    }

    public function test_omitting_the_field_leaves_the_device_state_alone(): void
    {
        $user = $this->user(false);
        $spy = $this->fakeDevice(false);

        $this->put(route('users.update', $user), ['card_number' => '12345'])->assertSessionHas('ok');

        $this->assertNull($spy->calls[0][3]);
        $this->assertFalse($user->fresh()->enabled);
        $this->assertSame(0, SyncLog::where('type', 'settings')->count());
    }

    public function test_a_full_name_is_kept_here_when_the_device_truncates_it(): void
    {
        $user = $this->user(true);
        $spy = $this->fakeDevice();
        $spy->storedName = 'LACAMBRA';

        $this->put(route('users.update', $user), ['name' => 'LACAMBRA, jhon', 'card_number' => '12345'])
            ->assertSessionHas('ok');

        $fresh = $user->fresh();
        $this->assertSame('LACAMBRA', $fresh->name);
        $this->assertSame('LACAMBRA, jhon', $fresh->display_name);
        $this->assertSame('LACAMBRA, jhon', $fresh->shown_name);
        $this->assertSame(1, SyncLog::where('type', 'settings')->where('message', 'like', '%kept in this app%')->count());
    }

    public function test_a_pull_keeps_the_full_name_until_the_device_name_changes(): void
    {
        $user = $this->user(true);
        $user->update(['name' => 'LACAMBRA', 'display_name' => 'LACAMBRA, jhon']);
        $spy = $this->fakeDevice();
        $row = fn (string $name) => ['enroll_number' => '7', 'name' => $name, 'card_number' => '12345', 'privilege' => 0, 'enabled' => true];

        $spy->deviceUsers = [$row('LACAMBRA')];
        $this->post(route('devices.sync-users', $user->device));
        $this->assertSame('LACAMBRA, jhon', $user->fresh()->shown_name);

        // Renamed on the terminal itself: the device wins and the stale full name is dropped.
        $spy->deviceUsers = [$row('OTHER')];
        $this->post(route('devices.sync-users', $user->device));
        $this->assertSame('OTHER', $user->fresh()->shown_name);
        $this->assertNull($user->fresh()->display_name);
    }

    public function test_a_name_the_device_keeps_in_full_needs_no_display_name(): void
    {
        $user = $this->user(true);
        $this->fakeDevice();

        $this->put(route('users.update', $user), ['name' => 'Short', 'card_number' => '12345'])->assertSessionHas('ok');

        $this->assertNull($user->fresh()->display_name);
        $this->assertSame('Short', $user->fresh()->name);
    }

    public function test_invalid_value_is_rejected_without_touching_the_device(): void
    {
        $user = $this->user(true);
        $spy = $this->fakeDevice();

        $this->put(route('users.update', $user), ['card_number' => '12345', 'enabled' => 'maybe'])->assertSessionHasErrors('enabled');

        $this->assertSame([], $spy->calls);
        $this->assertTrue($user->fresh()->enabled);
    }
}
