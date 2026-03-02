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

Web UI page:

- `GET /register-user`

Endpoint:

- `POST /api/zkteco/register-user`
- `PUT /api/zkteco/device-users/{id}`
- `DELETE /api/zkteco/device-users/{id}`
- `POST /api/zkteco/sync-device-users`
- `GET /api/zkteco/device-users-list`
- `GET /api/zkteco/command-status/{device_sn}/{pin}`
- `GET /api/zkteco/known-devices`
- `GET /api/zkteco/registration-stats`

Web page behavior (`/register-user`):
- command queue rows are merged per logical command, so `getrequest` + `service_control` duplicates appear as one line
- user list now includes **Edit** and **Delete** actions
- delete is queued to the device first; app-side removal happens only after a follow-up device user query confirms the PIN is absent
- deleting an already-absent local user returns a non-error info response (`already_absent=true`) so stale UI rows do not cause hard failures

Dashboard behavior (`/dashboard`):
- the **Users** card and **Users** tab now use canonical records from `zkteco_device_users` (not only raw `tabledata/user` logs)
- the **Users** tab is filtered by the latest full `querydata` user snapshot per device, so displayed users always match the machine-reported list
- Users tab includes **Edit** and **Delete** actions
- Users edit flow uses a single SweetAlert2 popup form (no browser prompt dialogs)
- Users delete and sync actions use SweetAlert2 confirmation/toast dialogs (no browser alert/confirm)
- Users tab includes **Sync From Device** button, which queues a `DATA QUERY tablename=user` command via `POST /api/zkteco/sync-device-users`
- incoming device user uploads from `POST /iclock/cdata` (`tabledata/user`) and `POST /iclock/querydata` are upserted into `zkteco_device_users`
- delete queue no longer hides users early; users remain visible in web until reconciliation confirms removal
- delete acknowledgments trigger a follow-up `DATA QUERY tablename=user` reconciliation pass
- user sync/delete command callbacks (including failures) can trigger reconciliation queries, so app state self-heals to machine state
- sync status badges count only active profile sync command families (`user_sync`, `face_push`, `fingerprint_push`) and ignore delete/query legacy noise

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
  "fingerprint_template": "<base64-or-template-string>"
}
```

Command delivery:
- queued per device serial number
- delivered when device polls `GET /iclock/getrequest` and `GET /iclock/service/control`
- command results are tracked from `POST /iclock/devicecmd` and `POST /iclock/service/control`
- command statuses: `pending` → `sent` → `acked|failed`
- max 3 commands are delivered per poll per channel
- cross-channel duplicates are auto-resolved when one channel returns success ack
- user deletion is queued with compatibility command variants for broader firmware support (`DATA DELETE ...`, `DELETE USER`, `DeleteUser`)

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

- `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass; ./serve-php82.ps1 -BindHost 0.0.0.0 -Port 8000`

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