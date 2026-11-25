<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\LogPusher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** URL, username and on/off switch. The password never passes through here (Electron keeps it). */
class SyncSettingsController extends Controller
{
    public function index(LogPusher $pusher): View
    {
        return view('sync.index', [
            'enabled' => Setting::get('push_enabled', '0') === '1',
            'url' => Setting::get('push_url', ''),
            'username' => Setting::get('push_username', ''),
            'interval' => $pusher->intervalMinutes(),
            'lastAt' => Setting::get('push_last_at'),
            'lastOk' => Setting::get('push_last_ok'),
            'lastMessage' => Setting::get('push_last_message'),
            'nextAttempt' => (int) Setting::get('push_next_attempt_at', 0),
            'unsent' => $pusher->unsentCount(),
            'rejected' => $pusher->rejectedCount(),
            'noSerial' => $pusher->waitingForSerialCount(),
            'httpAllowed' => app()->environment('local'),
            'configured' => Setting::get('push_enabled', '0') === '1' && Setting::get('push_url', '') !== '' && Setting::get('push_username', '') !== '',
        ]);
    }

    public function settings(LogPusher $pusher): View
    {
        return view('settings.index', [
            'enabled' => Setting::get('push_enabled', '0') === '1',
            'url' => Setting::get('push_url', ''),
            'username' => Setting::get('push_username', ''),
            'interval' => $pusher->intervalMinutes(),
            'httpAllowed' => app()->environment('local'),
            'autoEnabled' => Setting::get('auto_sync_enabled', '1') === '1',
            'autoMinutes' => max(5, (int) Setting::get('auto_sync_minutes', 30)),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'interval' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $url = LogPusher::normalizeUrl($data['url']);
        if (! $url) {
            return back()->withInput()->withErrors(['url' => 'Enter a valid https:// website address.']);
        }

        $changes = [];
        if (Setting::get('push_url', '') !== $url) {
            $changes[] = "website URL set to {$url}";
        }
        if (Setting::get('push_username', '') !== trim($data['username'])) {
            $changes[] = 'username changed';
        }
        if ((string) Setting::get('push_interval', 2) !== (string) ($data['interval'] ?? 2)) {
            $changes[] = 'push interval set to '.($data['interval'] ?? 2).' min';
        }
        $enabled = $request->boolean('enabled') ? '1' : '0';
        if (Setting::get('push_enabled', '0') !== $enabled) {
            $changes[] = 'push '.($enabled === '1' ? 'turned on' : 'turned off');
        }
        if ($changes) {
            \App\Models\SyncLog::record('settings', 'info', ucfirst(implode(', ', $changes)).'.');
        }

        Setting::put('push_url', $url);
        Setting::put('push_username', trim($data['username']));
        Setting::put('push_interval', (string) ($data['interval'] ?? 2));
        Setting::put('push_enabled', $request->boolean('enabled') ? '1' : '0');
        // A changed target deserves an immediate retry instead of waiting out an old backoff.
        Setting::put('push_failures', 0);
        Setting::put('push_next_attempt_at', 0);

        return back()->with('ok', 'Payroll sync settings saved.');
    }

    public function retryRejected(LogPusher $pusher): RedirectResponse
    {
        $count = $pusher->retryRejected();

        return back()->with('ok', "{$count} rejected record(s) queued to send again.");
    }
}
