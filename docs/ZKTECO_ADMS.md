# ZKTeco ADMS (iClock) receiver

This Laravel 10 project exposes minimal endpoints to receive raw attendance/access logs from ZKTeco devices via the ADMS/iClock protocol and store every hit in MySQL for later analysis.

## Endpoints (device -> server)

- `GET /iclock/cdata?SN=...`
  - Device fetches options.
  - Server responds with plain-text option lines.

- `POST /iclock/cdata?SN=...`
  - Device pushes data (for SenseFace 3A this is commonly `table=rtlog|rtstate|tabledata|options`).
  - Server stores the raw request and responds `OK`.

- `GET /iclock/getrequest?SN=...`
  - Device asks if the server has pending commands.
  - Server stores the raw request and responds `OK` (no commands).

- `GET /iclock/service/control?SN=...`
  - Security PUSH devices may poll this endpoint instead of `getrequest`.
  - Server returns queued command lines or `OK`.

- `GET|POST /iclock/registry?SN=...`
  - Some devices hit this during registration.
  - Server stores the raw request and responds `OK`.

## Storage

All requests are stored in the `zkteco_raw_logs` table:
- query params (`SN`, `table`, stamps, etc.)
- headers
- raw body

## App-side user registration and device escalation

You can now register a user in the Laravel app and automatically queue device commands for:
- user profile (`DATA UPDATE user`)
- face template (`DATA UPDATE biophoto`) when provided
- fingerprint template (`DATA UPDATE biodata`) when provided
- device-side biometric capture trigger (`ENROLL_FP`) for face/fingerprint when requested

Web UI page:

- `GET /register-user`

Endpoint:

- `POST /api/zkteco/register-user`
- `GET /api/zkteco/device-users-list`
- `GET /api/zkteco/command-status/{device_sn}/{pin}`
- `GET /api/zkteco/enrollment-status/{device_sn}/{pin}`
- `GET /api/zkteco/known-devices`
- `GET /api/zkteco/registration-stats`

Example JSON payload:

```json
{
  "device_sn": "VGU6251500098",
  "pin": 123,
  "name": "John Doe",
  "privilege": 0,
  "card_no": "",
  "group_id": 1,
  "disabled": false,
  "face_template": "<base64-or-template-string>",
  "fingerprint_template": "<base64-or-template-string>",
  "enroll_face": true,
  "enroll_fingerprint": false
}
```

Command delivery:
- queued per device serial number
- delivered when device polls `GET /iclock/getrequest` and `GET /iclock/service/control`
- command results are tracked from `POST /iclock/devicecmd` and `POST /iclock/service/control`
- command statuses: `pending` → `sent` → `acked|failed`
- max 3 commands are delivered per poll per channel

Database tables used by this feature:
- `zkteco_device_users` (upserted app-side user profile + optional face/fingerprint template fields)
- `zkteco_device_commands` (outbound command queue and ack tracking)

Recent schema note:
- `zkteco_device_commands` now includes nullable `pin` for per-user command tracking/status APIs

## MySQL (Laragon / XAMPP)

Set DB credentials in `.env` and create the database (example: `zkteco_adms_api`).

On a fresh PC migration:
1. Import your backup SQL (optional, if you already have production data)
2. Run migrations to add any new tables introduced after backup

Recommended migration command:

- `./php82-artisan.ps1 migrate`

The helper script now auto-detects PHP 8.2+ from Laragon and XAMPP.

## Start the server (PHP 8.2+)

Recommended (always uses PHP 8.2+):

- `./serve-php82.ps1 -BindHost 0.0.0.0 -Port 8000`

If you prefer calling PHP directly (Laragon/XAMPP):

- `php artisan serve --host=0.0.0.0 --port=8000`

If you want to tune the option response for `GET /iclock/cdata`, edit `config/zkteco.php`.

Insert your PC’s reachable URL (from the device’s network) as the ADMS/Server address, pointing to the `iclock` path:

```
Server/ADMS URL: http://<your-pc-lan-ip>:8000/iclock
```

Example (if your PC IP is `192.168.1.50`):

```
http://192.168.1.50:8000/iclock
```

That’s it—your device will call:

- `/iclock/cdata`
- `/iclock/getrequest`

and POST logs to `/iclock/cdata`.

If the device UI asks separately for IP and Port:

```
IP: <your-pc-lan-ip>
Port: 8000
Path/URI (if available): /iclock
```