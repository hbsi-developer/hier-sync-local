# Hier Payroll push API

Base URL = the **Website URL** saved in Settings (HTTPS required; plain HTTP only when `APP_ENV=local`).
Authentication: HTTP Basic (the credential's username and password). Requests send
`User-Agent: HierSyncLocal/<version>` and `Accept: application/json`. Redirects are never followed.

Paths are in `config/sync.php` (`path`, `status_path`).

## `GET /api/sync-local/v1/devices` — Test connection
Read-only. `200 {"success":true,"data":[...]}` means the URL and credentials are good. `401` means wrong
credentials ("Wrong username or password" in the app).

## `POST /api/sync-local/v1/logs` — push attendance
One request per device.

```json
{
  "deviceSerial": "AGIG230760152",
  "logs": [
    { "enrollNumber": "9", "punchedAt": "2026-11-25 08:02:11", "inOutMode": 0, "verifyMode": 1, "workCode": 0 }
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `deviceSerial` | yes | The device's serial number. It must be registered in Hier Payroll for the credential's company, otherwise `404 {"success":false,"message":"Unknown device."}` |
| `logs[].enrollNumber` | yes | The user's ID on that device (string) |
| `logs[].punchedAt` | yes | `Y-m-d H:i:s`, the device's local time |
| `logs[].inOutMode` | yes | `0` check-in, `1` check-out, `2` break-out, `3` break-in, `4` overtime-in, `5` overtime-out. Many devices always send `0` |
| `logs[].verifyMode` | no | Device verification type (e.g. fingerprint, card, password) |
| `logs[].workCode` | no | Device work code |

**Success:** HTTP 2xx and `success` not `false`. The whole device batch is then marked sent. The server does not
report per-record results.
**Errors:** `422` validation (a message names the field), `401` credentials, `404` unknown device, `5xx` server.

## `POST /api/sync-local/v1/status` — not used
Requires `deviceSerial` and a `status` value whose allowed values are unknown. Reserved for a future device status
report.

## Push behaviour
| Run | Trigger | Sends |
|---|---|---|
| Auto | Timer, every `push_interval` minutes (1–60, default 2) | Every unsent log **plus every log of today** (today's logs are resent each run) |
| Range | **Push range** on Payroll Sync | Every log between From and To (inclusive), including ones already sent |

- Batches of 500 logs per request. Auto runs stop after `sync.max_batches_per_run`; range runs continue until done.
- Devices without a serial number are skipped (the serial is read automatically on the first sync, or press **Test**).
- If one device fails, the others still send. The result message names each failing device and the reason.
- After a failure the next attempt waits 2, 4, 8… minutes (maximum 30). **Save settings** clears the wait.
- Every device result, test, skipped run and settings change is written to **Sync logs**.

## Open questions for the server team
- Does the server ignore duplicate punches? Auto push resends today's logs, so it must.
- Valid `status` values for `POST /status`.
- Should the success response report accepted/rejected counts?
