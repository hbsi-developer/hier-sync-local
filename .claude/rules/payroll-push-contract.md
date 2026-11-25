# Hier Payroll push contract

Source of truth for the HTTP contract with the payroll server. If the server's behaviour changes, update this file,
`docs/PUSH-API.md`, `config/sync.php`, `LogPusher`, and the tests together.

Routes (prefix `/api/sync-local/v1`, HTTP Basic Auth, JSON, throttled server-side):

| Route | Use in this app |
|---|---|
| `GET devices` | **Test connection.** Read-only; 200 means URL and credentials are good. |
| `POST logs` | Push attendance. One request per device. |
| `POST status` | Not used yet. Needs `deviceSerial` and a `status` value; the valid values are not known. |

`POST logs` body: `{ "deviceSerial": "...", "logs": [ { "enrollNumber", "punchedAt", "inOutMode", "verifyMode"?, "workCode"? } ] }`

- Required per entry: `enrollNumber`, `punchedAt` (`Y-m-d H:i:s`, device local time), `inOutMode` (int).
- Field names are **camelCase**. Never send the old `installation_id` / `device_serial` / `ref` shape.
- A batch is treated as accepted when the response is 2xx and `success` is not `false`. The server does not
  report per-record results, so there is no per-record rejection.
- `404 Unknown device.` means the serial is not registered for the credential's company. It must not block other
  devices: failures are collected per device and the run reports all of them.
- `401` means wrong credentials. Redirects are never followed (credentials must not be replayed elsewhere).
- Only HTTPS is allowed, except plain HTTP when `APP_ENV=local`. URLs with embedded credentials are refused.

Push behaviour (do not change silently):
- **Auto run** (`logs:push --scheduled`): every unsent log plus every log of *today*, resent each interval.
- **Range run** (`--from/--to`): every log in the range, including already sent ones.
- Batch size 500; auto runs stop after `sync.max_batches_per_run`; range runs continue until done.
- Failure backoff 2^n minutes (cap 30); "Save settings" or a forced run clears it.
- The interval setting (`push_interval`, 1–60 min) is enforced inside the command, so the timer can tick every minute.
