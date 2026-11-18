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
            'lastAt' => Setting::get('push_last_at'),
            'lastOk' => Setting::get('push_last_ok'),
            'lastMessage' => Setting::get('push_last_message'),
            'nextAttempt' => (int) Setting::get('push_next_attempt_at', 0),
            'unsent' => $pusher->unsentCount(),
            'rejected' => $pusher->rejectedCount(),
            'noSerial' => $pusher->waitingForSerialCount(),
            'httpAllowed' => app()->environment('local'),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
        ]);

        $url = LogPusher::normalizeUrl($data['url']);
        if (! $url) {
            return back()->withInput()->withErrors(['url' => 'Enter a valid https:// website address.']);
        }

        Setting::put('push_url', $url);
        Setting::put('push_username', trim($data['username']));
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
