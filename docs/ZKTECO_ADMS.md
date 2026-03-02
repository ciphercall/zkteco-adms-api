# ZKTeco ADMS (iClock) receiver

This Laravel 10 project exposes minimal endpoints to receive raw attendance/access logs from ZKTeco devices via the ADMS/iClock protocol and store every hit in MySQL for later analysis.

## Endpoints (device -> server)

- `GET /iclock/cdata?SN=...`
  - Device fetches options.
  - Server responds with plain-text option lines.

- `POST /iclock/cdata?SN=...`
  - Device pushes data (often `table=ATTLOG` and a `Data` payload).
  - Server stores the raw request and responds `OK`.

- `GET /iclock/getrequest?SN=...`
  - Device asks if the server has pending commands.
  - Server stores the raw request and responds `OK` (no commands).

- `GET|POST /iclock/registry?SN=...`
  - Some devices hit this during registration.
  - Server stores the raw request and responds `OK`.

## Storage

All requests are stored in the `zkteco_raw_logs` table:
- query params (`SN`, `table`, stamps, etc.)
- headers
- raw body

## MySQL (XAMPP)

Use your XAMPP MySQL credentials in `.env` (already set to `DB_CONNECTION=mysql`). Create a database (example: `zkteco_adms_api`) and run migrations:

- Use PHP 8.2+ from XAMPP (your machine has PHP 8.2 at `F:\\xampp\\php\\php.exe`).
- Recommended (always uses PHP 8.2+): `./php82-artisan.ps1 migrate`

If you prefer calling PHP directly:

- `F:\\xampp\\php\\php.exe artisan migrate`

## Start the server (PHP 8.2)

Recommended (always uses PHP 8.2+):

- `./serve-php82.ps1 -BindHost 0.0.0.0 -Port 8000`

If you prefer calling PHP directly:

- `F:\\xampp\\php\\php.exe artisan serve --host=0.0.0.0 --port=8000`
- `Set-Location "E:\OneDrive\Documents\GitHub\zkteco-adms-api"; .\serve-php82.ps1 -BindHost 0.0.0.0 -Port 8000`

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