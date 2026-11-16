<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;

/**
 * Talks to ZKTeco devices through bridge\zkbridge.exe, a small .NET tool that wraps the
 * SDK (zkemkeeper.dll). PHP's own COM layer cannot marshal the SDK's by-reference integer
 * out-parameters, so each call runs the bridge and reads its JSON output.
 */
class ZkemClient
{
    private string $ip = '';

    private int $port = 4370;

    public function __construct(private int $machineNumber = 1)
    {
    }

    public static function bridgePath(): string
    {
        return base_path('bridge/zkbridge.exe');
    }

    public static function environmentProblem(): ?string
    {
        if (! is_file(self::bridgePath())) {
            return 'bridge\\zkbridge.exe is missing. Build it with bridge\\build.bat.';
        }

        exec('reg query "HKCR\\zkemkeeper.ZKEM" 2>&1', $out, $code);
        if ($code !== 0) {
            return 'The ZKTeco SDK is not registered. Run sdk\\register-sdk.bat as Administrator.';
        }

        return null;
    }

    public function connect(string $ip, int $port = 4370): static
    {
        if ($problem = self::environmentProblem()) {
            throw new RuntimeException($problem);
        }

        // The SDK can block for a minute or more on an unreachable host, which would freeze
        // the single-threaded PHP server, so fail fast first.
        $probe = @fsockopen($ip, $port, $errno, $errstr, 3);
        if ($probe === false) {
            throw new RuntimeException("Device {$ip}:{$port} is not reachable ({$errstr}). Check the IP, port, network and that the device is on.");
        }
        fclose($probe);

        $this->ip = $ip;
        $this->port = $port;

        return $this;
    }

    public function disconnect(): void
    {
        // Each bridge call opens and closes its own connection.
    }

    public function serialNumber(): ?string
    {
        return $this->call('info')['serial'] ?: null;
    }

    public function deviceTime(): ?Carbon
    {
        $time = $this->call('info')['time'] ?? null;

        return $time ? Carbon::parse($time) : null;
    }

    public function syncTime(): bool
    {
        return (bool) $this->call('settime');
    }

    /** @return array<int, array{enroll_number:string,name:string,card_number:string,privilege:int,enabled:bool}> */
    public function users(): array
    {
        return $this->call('users');
    }

    /**
     * Edit an existing user on the device: set the card number (blank clears it) and optionally the name.
     *
     * @return array{enroll_number:string,name:string,card_number:string}
     */
    public function setUser(string $enrollNumber, string $cardNumber, ?string $name = null): array
    {
        return $this->call('setuser', [$enrollNumber, $cardNumber === '' ? '0' : $cardNumber, $name === null ? '' : base64_encode($name)]);
    }

    /** @return array<int, array{enroll_number:string,punched_at:Carbon,verify_mode:int,in_out_mode:int,work_code:int}> */
    public function attendance(): array
    {
        return array_map(
            fn (array $row) => [...$row, 'punched_at' => Carbon::parse($row['punched_at'])],
            $this->call('logs')
        );
    }

    private function call(string $command, array $extra = []): mixed
    {
        $cmd = sprintf('"%s" %s %s %d %d', self::bridgePath(), $command, escapeshellarg($this->ip), $this->port, $this->machineNumber);
        foreach ($extra as $arg) {
            $cmd .= ' '.escapeshellarg((string) $arg);
        }
        $output = shell_exec($cmd.' 2>&1');
        $result = json_decode((string) $output, true);

        if (! is_array($result) || ! array_key_exists('ok', $result)) {
            throw new RuntimeException('Unexpected response from the SDK bridge: '.trim((string) $output));
        }

        if (! $result['ok']) {
            throw new RuntimeException($result['error'] ?? 'SDK bridge failed.');
        }

        return $result['data'];
    }
}
