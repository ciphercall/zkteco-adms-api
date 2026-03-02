# ZKTeco SenseFace 3A — Laravel ADMS Integration Guide

> **Complete reference for integrating ZKTeco SenseFace 3A (and compatible A&C PUSH devices) with a Laravel-based server using the ZKTeco Security PUSH Communication Protocol v3.1.2.**

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture](#2-architecture)
3. [Device Configuration](#3-device-configuration)
4. [Protocol Flow](#4-protocol-flow)
5. [Server Endpoints Reference](#5-server-endpoints-reference)
6. [Data Formats](#6-data-formats)
7. [Laravel Implementation](#7-laravel-implementation)
8. [Database Schema](#8-database-schema)
9. [Dashboard & API](#9-dashboard--api)
10. [Sending Commands to the Device](#10-sending-commands-to-the-device)
11. [Integrating with an Existing Laravel HR System](#11-integrating-with-an-existing-laravel-hr-system)
12. [Troubleshooting](#12-troubleshooting)
13. [Event Code Reference](#13-event-code-reference)
14. [Security Considerations](#14-security-considerations)

---

## 1. Overview

### What This Document Covers

This document is a **single-source integration guide** for connecting a **ZKTeco SenseFace 3A** AI face recognition access control terminal to a **Laravel 10/11** backend server using the **ADMS (Automatic Device Management System)** protocol.

### Device Specifications

| Property | Value |
|---|---|
| **Model** | ZKTeco SenseFace 3A |
| **Type** | AI Face Recognition Access Control Terminal |
| **Protocol** | Security PUSH Communication Protocol v3.1.2 |
| **Device Type** | `acc` (Access Control) — NOT attendance (`att`) |
| **Push Version** | `3.1.2` |
| **Communication** | HTTP PUSH (device initiates all connections to server) |
| **Data Format** | Tab-separated key=value pairs (plain text) |
| **Verification** | Face (type 15), Fingerprint (type 1), Card (type 2), Password (type 0) |
| **Event Types** | Real-time access events (`rtlog`), door/sensor status (`rtstate`), user data (`tabledata`) |

### Key Characteristics

- **PUSH model**: The device connects TO the server (not the other way around). The server never needs to initiate connections to the device.
- **A&C PUSH**: Access & Control PUSH protocol — uploads `rtlog` events, NOT `ATTLOG` (attendance log). This is the critical distinction from time & attendance terminals.
- **Polling-based command delivery**: The device polls `GET /iclock/getrequest` every ~30 seconds. The server queues commands and delivers them in response.
- **No SDK required**: Communication is plain HTTP + text. No proprietary SDK, DLL, or binary protocol.

---

## 2. Architecture

```
┌─────────────────────┐         HTTP (port 8000)         ┌──────────────────────┐
│                     │ ─────────────────────────────────▶│                      │
│  ZKTeco SenseFace   │   GET  /iclock/cdata   (init)    │   Laravel Server     │
│  3A Device          │   POST /iclock/registry           │                      │
│                     │   POST /iclock/push    (config)   │   ┌──────────────┐   │
│  SN: VGU6251500098  │   POST /iclock/cdata   (upload)   │   │ AdmsController│  │
│  IP: 192.168.10.97  │   GET  /iclock/getrequest (poll)  │   └──────┬───────┘   │
│                     │   GET  /iclock/ping    (heartbeat)│          │           │
│                     │   POST /iclock/devicecmd (result) │   ┌──────▼───────┐   │
│                     │ ◀─────────────────────────────────│   │  Database    │   │
│                     │   (text/plain responses)          │   │  (raw_logs)  │   │
└─────────────────────┘                                   │   └──────────────┘   │
                                                          │                      │
                                                          │   ┌──────────────┐   │
                                                          │   │  Dashboard   │   │
                                                          │   │  /dashboard  │   │
                                                          │   └──────────────┘   │
                                                          └──────────────────────┘
```

### Communication Model

1. Device **always initiates** HTTP requests to the server
2. Server **never connects** to the device
3. All responses are `text/plain; charset=UTF-8`
4. Line separator: `\r\n` (CRLF)
5. Field separator within data: `\t` (tab)
6. Key-value format: `key=value`

---

## 3. Device Configuration

### ADMS Settings on the Device

Navigate to the device menu: **COMM** → **ADMS Settings** (or via network settings).

| Setting | Value | Notes |
|---|---|---|
| **Enable ADMS** | Yes | Must be enabled |
| **Server URL** | `http://YOUR_SERVER_IP:8000/iclock` | Replace with your server IP |
| **Server Port** | `8000` | Or whichever port your Laravel server runs on |
| **Communication Mode** | PUSH | Device pushes data to server |

### Example Configuration

For a server running at `192.168.10.89:8000`:

```
Server URL: http://192.168.10.89:8000/iclock
```

The device will automatically append specific paths (`/cdata`, `/registry`, `/getrequest`, etc.) to this base URL.

### Device Information Sent During Init

When the device connects, it sends these query parameters on the first `GET /iclock/cdata`:

```
SN=VGU6251500098          # Serial number (unique per device)
options=all               # Request all configuration
pushver=3.1.2             # Push protocol version
DeviceType=acc            # Device type: acc = access control
PushOptionsFlag=1         # Supports push options
language=83               # Language code
pushflag=ABCDEF...        # Capability flags
```

---

## 4. Protocol Flow

### Complete Initialization Sequence

```
DEVICE                                             SERVER
  │                                                  │
  │  1. GET /iclock/cdata?SN=xxx&options=all         │
  │ ────────────────────────────────────────────────▶ │
  │                                                  │  ← Returns registry=ok + config
  │  ◀──────────────────────────────────────────────  │
  │                                                  │
  │  2. POST /iclock/registry?SN=xxx                 │
  │ ────────────────────────────────────────────────▶ │
  │     Body: device capabilities                    │  ← Returns RegistryCode + Set-Cookie
  │  ◀──────────────────────────────────────────────  │
  │                                                  │
  │  3. POST /iclock/push?SN=xxx                     │
  │ ────────────────────────────────────────────────▶ │
  │                                                  │  ← Returns ServerVersion, Realtime, etc.
  │  ◀──────────────────────────────────────────────  │
  │                                                  │
  │  4. POST /iclock/cdata?table=tabledata            │
  │ ────────────────────────────────────────────────▶ │  (uploads user data, photos, etc.)
  │  ◀──────────────────────────────────────────────  │  ← Returns tablename=count
  │                                                  │
  │  5. POST /iclock/cdata?table=options              │
  │ ────────────────────────────────────────────────▶ │  (uploads device parameters)
  │  ◀──────────────────────────────────────────────  │  ← Returns OK
  │                                                  │
  │  ┌─── STEADY STATE (repeats forever) ───┐        │
  │  │                                      │        │
  │  │  6. GET /iclock/getrequest?SN=xxx    │        │
  │  │ ──────────────────────────────────▶  │        │
  │  │                                      │  ← OK (no commands) or C:id:CMD
  │  │  ◀──────────────────────────────────  │        │
  │  │                                      │        │
  │  │  (every ~30 seconds)                 │        │
  │  └──────────────────────────────────────┘        │
  │                                                  │
  │  ── ON ACCESS EVENT ──                           │
  │  7. POST /iclock/cdata?table=rtlog               │
  │ ────────────────────────────────────────────────▶ │  (face scan, door event)
  │  ◀──────────────────────────────────────────────  │  ← OK
  │                                                  │
  │  8. POST /iclock/cdata?table=rtstate             │
  │ ────────────────────────────────────────────────▶ │  (door sensor/relay status)
  │  ◀──────────────────────────────────────────────  │  ← OK
  │                                                  │
  │  ── DURING LARGE UPLOADS ──                      │
  │  9. GET /iclock/ping?SN=xxx                      │
  │ ────────────────────────────────────────────────▶ │  (heartbeat / keep-alive)
  │  ◀──────────────────────────────────────────────  │  ← OK
```

### Timing

| Interval | Duration | Description |
|---|---|---|
| **getrequest polling** | ~30 seconds | Device polls for commands in idle state |
| **Realtime upload** | Immediate | rtlog/rtstate sent instantly on events |
| **TransInterval** | 1 minute | Minimum interval for batch uploads |
| **ErrorDelay** | 60 seconds | Retry delay after server error |
| **RequestDelay** | 5 seconds | Delay between consecutive requests |

---

## 5. Server Endpoints Reference

All endpoints are relative to the base URL (e.g., `http://server:8000`).

### 5.1 `GET /iclock/cdata` — Initialization

**Purpose**: Device requests configuration on boot or reconnection.

**Query Parameters**:

| Param | Example | Description |
|---|---|---|
| `SN` | `VGU6251500098` | Device serial number |
| `options` | `all` | Request all config options |
| `pushver` | `3.1.2` | Protocol version |
| `DeviceType` | `acc` | `acc` = access control, `att` = attendance |
| `PushOptionsFlag` | `1` | Supports push options |
| `language` | `83` | Language code |
| `pushflag` | `ABCDEF...` | Capability bitmask |

**Response** (device registered):

```
registry=ok
RegistryCode=a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4
ServerVersion=3.0.1
ServerName=LaravelADMS
PushProtVer=3.1.2
ErrorDelay=60
RequestDelay=5
TransTimes=00:00;14:00
TransInterval=1
TransTables=User Transaction
Realtime=1
SessionID=ABCDEF1234567890
TimeoutSec=10
```

**Response** (device NOT registered):

```
OK
```

**Key Parameters Explained**:

| Parameter | Description |
|---|---|
| `registry=ok` | Tells device it's registered and should proceed |
| `RegistryCode` | Unique registration token (up to 32 bytes, we use MD5 of SN) |
| `Realtime=1` | Enable real-time event uploading |
| `TransInterval=1` | Upload interval in minutes |
| `TransTables` | Which tables to upload (space-separated) |
| `RequestDelay=5` | Seconds between getrequest polls |
| `SessionID` | Session identifier for the connection |

### 5.2 `POST /iclock/registry` — Registration

**Purpose**: Device registers itself and sends its capabilities.

**Query Parameters**: `SN=VGU6251500098`

**Request Body** (example):

```
options=all
pushver=3.1.2
DeviceType=acc
...device capabilities...
```

**Response**:

```
RegistryCode=a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4
```

**Response Headers**:

```
Content-Type: text/plain; charset=UTF-8
Set-Cookie: PHPSESSID=SESSION_ID; Path=/; HttpOnly
```

### 5.3 `POST /iclock/push` — Download Configuration

**Purpose**: Device requests server configuration parameters after registration.

**Query Parameters**: `SN=VGU6251500098`

**Response**:

```
ServerVersion=3.0.1
ServerName=LaravelADMS
PushVersion=3.1.2
ErrorDelay=60
RequestDelay=5
TransTimes=00:00;14:00
TransInterval=1
TransTables=User Transaction
Realtime=1
SessionID=ABCDEF1234567890
TimeoutSec=10
```

### 5.4 `POST /iclock/cdata` — Data Upload

**Purpose**: Device uploads event data, status, user data, and biometric data.

**Query Parameters**:

| Param | Example | Description |
|---|---|---|
| `SN` | `VGU6251500098` | Device serial number |
| `table` | `rtlog` | Table type being uploaded |
| `tablename` | `user` | Sub-table name (for `tabledata` type) |
| `count` | `1` | Number of records in upload |
| `stamp` | `1234567890` | Timestamp marker |

**Table Types**:

| table | tablename | Description |
|---|---|---|
| `rtlog` | - | Real-time access events (face scan, door events) |
| `rtstate` | - | Real-time hardware status (sensor, relay, door, alarm) |
| `tabledata` | `user` | User records (PIN, name, privilege) |
| `tabledata` | `biophoto` | Face photo templates |
| `tabledata` | `biodata` | Fingerprint templates |
| `tabledata` | `userpic` | User profile photos |
| `options` | - | Device configuration parameters |

**Acknowledgment Responses**:

| Table Type | Response |
|---|---|
| `rtlog` | `OK` |
| `rtstate` | `OK` |
| `options` | `OK` |
| `tabledata` (user) | `user=<count>` |
| `tabledata` (biophoto) | `biophoto=<count>` |
| `tabledata` (biodata) | `biodata=<count>` |
| `tabledata` (userpic) | `userpic=<count>` |

⚠️ **Critical**: For `tabledata` uploads, the response MUST be `<tablename>=<count>`. Responding with just `OK` will cause the device to re-upload the same data repeatedly.

### 5.5 `GET /iclock/getrequest` — Command Polling

**Purpose**: Device polls for pending commands from the server.

**Query Parameters**: `SN=VGU6251500098`

**Response** (no pending commands):

```
OK
```

**Response** (with commands):

```
C:12345:DATA QUERY tablename=transaction,fielddesc=*,filter=*
```

**Command Format**: `C:<CommandID>:<CommandDescription> <CommandDetail>`

See [Section 10: Sending Commands](#10-sending-commands-to-the-device) for details.

### 5.6 `GET /iclock/ping` — Heartbeat

**Purpose**: Keep-alive during large data uploads.

**Query Parameters**: `SN=VGU6251500098`

**Response**:

```
OK
```

### 5.7 `POST /iclock/devicecmd` — Command Result

**Purpose**: Device reports the result of a previously delivered command.

**Query Parameters**: `SN=VGU6251500098`

**Request Body**:

```
ID=12345&Return=0&CMD=DATA
```

| Field | Description |
|---|---|
| `ID` | Command ID that was executed |
| `Return` | Result code (0 = success) |
| `CMD` | Command type |

**Response**: `OK`

### 5.8 `POST /iclock/exchange` — Key Exchange (Optional)

**Purpose**: Exchange encryption keys for secure communication.

**Query Parameters**: `SN=VGU6251500098&type=publickey`

In most deployments, encryption is not used. Simply respond with `OK`.

### 5.9 Fallback Handler

Some devices send double-path URLs (e.g., `/iclock/iclock/cdata`). The server includes a fallback route that catches `{any}` path under `/iclock/` and routes it to the correct handler based on URL pattern matching.

---

## 6. Data Formats

### 6.1 rtlog — Access Event

**Content-Type**: `text/plain`
**Separator**: Tab (`\t`)

```
time=2026-02-23 14:13:48\tpin=2\tcardno=0\teventaddr=1\tevent=3\tinoutstatus=0\tverifytype=15\tindex=140\tsitecode=0\tlinkid=0\tmaskflag=0\ttemperature=0\tconvtemperature=0
```

| Field | Type | Description |
|---|---|---|
| `time` | datetime | Event timestamp (device local time) |
| `pin` | integer | User PIN (0 = no user identified) |
| `cardno` | string | Card number (0 if none) |
| `eventaddr` | integer | Door/reader number |
| `event` | integer | Event code (see [Section 13](#13-event-code-reference)) |
| `inoutstatus` | integer | 0=Entry, 1=Exit, 2=N/A |
| `verifytype` | integer | Verification method: 0=Password, 1=Fingerprint, 2=Card, 15=Face, 200=System |
| `index` | integer | Sequential event index |
| `sitecode` | integer | Site code |
| `linkid` | integer | Linked event ID |
| `maskflag` | integer | 0=No mask, 1=Mask detected |
| `temperature` | float | Body temperature (if supported) |
| `convtemperature` | float | Converted temperature |

### 6.2 rtstate — Hardware Status

```
time=2026-02-23 14:13:48\tsensor=01\trelay=00\talarm=0000000000000000\tdoor=01
```

| Field | Type | Description |
|---|---|---|
| `time` | datetime | Status timestamp |
| `sensor` | hex | Door sensor state: `01`=detected/closed, `02`=open |
| `relay` | hex | Relay state |
| `alarm` | hex (16 chars) | Alarm flags bitmask |
| `door` | hex | Door state: `00`=closed, `01`=open |

### 6.3 tabledata/user — User Records

```
user uid=1\tcardno=\tpin=1\tpassword=\tgroup=1\tstarttime=0\tendtime=0\tname=John Doe\tprivilege=14\tdisable=0\tverify=0
```

| Field | Description |
|---|---|
| `uid` | Internal user ID |
| `pin` | User PIN (unique identifier) |
| `name` | User display name |
| `privilege` | 0=User, 2=Enroller, 6=Admin, 14=Super Admin |
| `cardno` | Assigned card number |
| `group` | Access group |
| `starttime` / `endtime` | Access validity period (0 = unlimited) |
| `disable` | 0=Active, 1=Disabled |
| `verify` | Verification mode |

### 6.4 tabledata/biophoto — Face Templates

Binary face photo data with metadata. The body contains base64-encoded or raw binary face template data.

### 6.5 tabledata/biodata — Fingerprint Templates

Binary fingerprint template data (type=1 for standard fingerprint).

### 6.6 options — Device Parameters

Key=value pairs representing device configuration options.

---

## 7. Laravel Implementation

### 7.1 Project Structure

```
zkteco-adms-api/
├── app/
│   ├── Http/Controllers/ZKTeco/
│   │   ├── AdmsController.php       # ADMS protocol handler
│   │   ├── DashboardController.php  # Dashboard & API
│   │   └── UserRegistrationController.php  # App-side registration/sync API
│   ├── Services/ZKTeco/
│   │   └── DeviceCommandQueue.php   # Persistent device command queue
│   └── Models/
│       ├── ZktecoRawLog.php         # Raw log model
│       ├── ZktecoDeviceUser.php     # User sync state per device
│       └── ZktecoDeviceCommand.php  # Outbound command queue records
├── config/
│   └── zkteco.php                   # Configuration
├── database/migrations/
│   ├── 2026_02_22_000000_create_zkteco_raw_logs_table.php
│   ├── 2026_03_02_000001_create_zkteco_device_users_table.php
│   ├── 2026_03_02_000002_create_zkteco_device_commands_table.php
│   └── 2026_03_02_000003_add_pin_to_zkteco_device_commands_table.php
├── resources/views/zkteco/
│   ├── dashboard.blade.php          # Dashboard UI
│   └── register.blade.php           # User registration + sync wizard UI
└── routes/
    ├── web.php                      # ADMS + dashboard + registration page routes
    └── api.php                      # register-user + status APIs
```

### 7.2 Routes (`routes/web.php`)

```php
use App\Http\Controllers\ZKTeco\AdmsController;
use App\Http\Controllers\ZKTeco\DashboardController;
use App\Http\Controllers\ZKTeco\UserRegistrationController;

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/register-user', [UserRegistrationController::class, 'index']);

// Dashboard API
Route::prefix('api/zkteco')->group(function () {
    Route::get('stats', [DashboardController::class, 'stats']);
    Route::get('access-events', [DashboardController::class, 'accessEvents']);
    Route::get('device-status', [DashboardController::class, 'deviceStatus']);
    Route::get('users', [DashboardController::class, 'users']);
    Route::get('raw-logs', [DashboardController::class, 'rawLogs']);
    Route::get('timeline', [DashboardController::class, 'timeline']);
});

// ADMS Protocol Endpoints
Route::prefix('iclock')->group(function () {
    Route::match(['GET', 'POST'], 'cdata', [AdmsController::class, 'cdata']);
    Route::match(['GET', 'POST'], 'registry', [AdmsController::class, 'registry']);
    Route::match(['GET', 'POST'], 'push', [AdmsController::class, 'push']);
    Route::match(['GET', 'POST'], 'ping', [AdmsController::class, 'ping']);
    Route::match(['GET', 'POST'], 'exchange', [AdmsController::class, 'exchange']);
    Route::match(['GET', 'POST'], 'getrequest', [AdmsController::class, 'getRequest']);
    Route::match(['GET', 'POST'], 'getreq', [AdmsController::class, 'getRequest']);
    Route::match(['GET', 'POST'], 'devicecmd', [AdmsController::class, 'deviceCmd']);
    Route::match(['GET', 'POST'], 'service/control', [AdmsController::class, 'serviceControl']);
    Route::match(['GET', 'POST'], 'querydata', [AdmsController::class, 'queryData']);
    Route::match(['GET', 'POST'], 'fdata', [AdmsController::class, 'fdata']);
    Route::match(['GET', 'POST'], '{any}', [AdmsController::class, 'fallback'])->where('any', '.*');
});
```

**Important**: The `{any}` fallback route MUST be last. It catches double-path bugs and unknown endpoints.

### 7.2.1 Routes (`routes/api.php`) — App-side user registration/sync

```php
use App\Http\Controllers\ZKTeco\UserRegistrationController;

Route::post('/zkteco/register-user', [UserRegistrationController::class, 'store']);
Route::put('/zkteco/device-users/{id}', [UserRegistrationController::class, 'update']);
Route::delete('/zkteco/device-users/{id}', [UserRegistrationController::class, 'destroy']);
Route::post('/zkteco/sync-device-users', [UserRegistrationController::class, 'syncDeviceUsers']);
Route::get('/zkteco/device-users-list', [UserRegistrationController::class, 'deviceUsersList']);
Route::get('/zkteco/command-status/{device_sn}/{pin}', [UserRegistrationController::class, 'commandStatus']);
Route::get('/zkteco/known-devices', [UserRegistrationController::class, 'knownDevices']);
Route::get('/zkteco/registration-stats', [UserRegistrationController::class, 'registrationStats']);
```

Request validation rules implemented by the endpoint:
- required: `device_sn`, `pin`, `name`
- optional: `privilege`, `card_no`, `group_id`, `disabled`, `face_template`, `fingerprint_template`

### 7.3 CSRF Exemption

The device sends raw HTTP requests without CSRF tokens. You MUST exempt the `/iclock/*` routes from CSRF verification.

In `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'iclock/*',
];
```

### 7.4 AdmsController — Core Protocol Handler

```php
// app/Http/Controllers/ZKTeco/AdmsController.php

class AdmsController extends Controller
{
    // Every request is first stored as a raw log
    private function storeRaw(Request $request, string $endpoint): void
    {
        ZktecoRawLog::create([
            'device_sn' => $request->query('SN'),
            'endpoint'  => $endpoint,
            'method'    => strtoupper($request->method()),
            'ip'        => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'query_params' => $request->query(),
            'form_params'  => $request->request->all(),
            'headers'      => $request->headers->all(),
            'raw_body'     => $request->getContent(),
        ]);
    }

    // GET /iclock/cdata — Initialization
    public function cdata(Request $request)
    {
        $this->storeRaw($request, '/iclock/cdata');
        if ($request->isMethod('get')) {
            return $this->handleCdataGet($request);
        }
        return $this->handleCdataPost($request);
    }

    private function handleCdataGet(Request $request)
    {
        $sn = $request->query('SN', 'UNKNOWN');
        $registryCode = md5('zkteco-adms-' . $sn);
        $sessionId = strtoupper(md5($sn . '-session-' . date('Ymd')));

        $lines = [
            'registry=ok',
            "RegistryCode={$registryCode}",
            'ServerVersion=3.0.1',
            'PushProtVer=3.1.2',
            'ErrorDelay=60',
            'RequestDelay=5',
            'TransTimes=00:00;14:00',
            'TransInterval=1',
            'TransTables=User Transaction',
            'Realtime=1',
            "SessionID={$sessionId}",
        ];

        return response(implode("\r\n", $lines) . "\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // POST /iclock/cdata — Data Upload
    private function handleCdataPost(Request $request)
    {
        $table = strtolower($request->query('table', ''));
        $tablename = strtolower($request->query('tablename', ''));
        $count = $request->query('count', '');

        // tabledata → respond with "tablename=count"
        if ($table === 'tabledata' && $tablename !== '') {
            return response("{$tablename}={$count}\r\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // rtlog, rtstate, options → respond with "OK"
        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // POST /iclock/registry — Registration
    public function registry(Request $request)
    {
        $this->storeRaw($request, '/iclock/registry');
        $sn = $request->query('SN', 'UNKNOWN');
        $registryCode = md5('zkteco-adms-' . $sn);
        $sessionId = strtoupper(md5($sn . '-session-' . date('Ymd')));

        return response("RegistryCode={$registryCode}\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Set-Cookie', "PHPSESSID={$sessionId}; Path=/; HttpOnly");
    }

    // POST /iclock/push — Configuration Download
    public function push(Request $request)
    {
        $this->storeRaw($request, '/iclock/push');
        // Same config params as cdata init response
        $lines = [
            'ServerVersion=3.0.1',
            'PushVersion=3.1.2',
            'Realtime=1',
            'TransInterval=1',
            // ... etc
        ];
        return response(implode("\r\n", $lines) . "\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // GET /iclock/ping — Heartbeat
    public function ping(Request $request)
    {
        $this->storeRaw($request, '/iclock/ping');
        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // GET /iclock/getrequest — Command Polling
    public function getRequest(Request $request)
    {
        $this->storeRaw($request, '/iclock/getrequest');
        $commands = []; // fetch from DB or config

        if (!empty($commands)) {
            return response(implode("\n", $commands) . "\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }
        return response("OK\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // POST /iclock/devicecmd — Command Result
    public function deviceCmd(Request $request)
    {
        $this->storeRaw($request, '/iclock/devicecmd');
        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
```

### 7.5 Configuration (`config/zkteco.php`)

```php
return [
    'iclock' => [
        // Commands for Security PUSH devices (via /iclock/service/control)
        'security_push_commands' => [],

        // Commands for Attendance PUSH devices (via /iclock/getrequest)
        'getrequest_commands' => [],
    ],
];
```

---

## 8. Database Schema

### 8.1 Raw Logs Table (Minimal — Capture Everything)

```php
Schema::create('zkteco_raw_logs', function (Blueprint $table) {
    $table->id();
    $table->string('device_sn')->nullable()->index();
    $table->string('endpoint', 100);
    $table->string('method', 10);
    $table->string('ip', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->string('content_type')->nullable();
    $table->json('query_params')->nullable();
    $table->json('form_params')->nullable();
    $table->json('headers')->nullable();
    $table->longText('raw_body')->nullable();
    $table->timestamps();
});
```

### 8.2 Recommended: Parsed Access Events Table

For production use, parse the raw rtlog data into a structured table:

```php
Schema::create('zkteco_access_events', function (Blueprint $table) {
    $table->id();
    $table->string('device_sn')->index();
    $table->dateTime('event_time')->index();
    $table->unsignedInteger('pin')->default(0)->index();
    $table->string('card_no', 50)->nullable();
    $table->unsignedSmallInteger('event_code');
    $table->string('event_label', 100);
    $table->string('event_category', 20)->index();  // access, alarm, door, system
    $table->unsignedSmallInteger('door_number')->default(1);
    $table->unsignedSmallInteger('in_out_status')->default(2);  // 0=entry, 1=exit, 2=n/a
    $table->unsignedSmallInteger('verify_type')->default(0);
    $table->unsignedInteger('event_index')->default(0);
    $table->boolean('mask_detected')->default(false);
    $table->decimal('temperature', 5, 2)->default(0);
    $table->unsignedBigInteger('raw_log_id')->nullable();
    $table->timestamps();

    $table->index(['device_sn', 'event_time']);
    $table->index(['pin', 'event_time']);
});
```

### 8.3 Recommended: Parsed Users Table

```php
Schema::create('zkteco_users', function (Blueprint $table) {
    $table->id();
    $table->string('device_sn')->index();
    $table->unsignedInteger('pin')->index();
    $table->string('name', 100)->nullable();
    $table->unsignedSmallInteger('privilege')->default(0);
    $table->string('card_no', 50)->nullable();
    $table->unsignedSmallInteger('group_id')->default(1);
    $table->boolean('disabled')->default(false);
    $table->timestamps();

    $table->unique(['device_sn', 'pin']);
});
```

### 8.4 Device User Sync Table (Implemented)

```php
Schema::create('zkteco_device_users', function (Blueprint $table) {
    $table->id();
    $table->string('device_sn')->index();
    $table->unsignedInteger('pin');
    $table->string('name', 100);
    $table->unsignedSmallInteger('privilege')->default(0);
    $table->string('card_no', 50)->nullable();
    $table->unsignedSmallInteger('group_id')->default(1);
    $table->boolean('disabled')->default(false);
    $table->longText('face_template')->nullable();
    $table->longText('fingerprint_template')->nullable();
    $table->timestamps();

    $table->unique(['device_sn', 'pin']);
});
```

### 8.5 Device Command Queue Table (Implemented)

```php
Schema::create('zkteco_device_commands', function (Blueprint $table) {
    $table->id();
    $table->string('device_sn')->index();
    $table->unsignedInteger('pin')->nullable();
    $table->string('channel', 40)->default('getrequest'); // getrequest | service_control
    $table->text('command_template');
    $table->unsignedInteger('cmd_id')->nullable()->index();
    $table->string('status', 20)->default('pending')->index(); // pending|sent|acked|failed
    $table->text('ack_payload')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('acknowledged_at')->nullable();
    $table->timestamps();

    $table->index(['device_sn', 'pin']);
});
```

---

## 9. Dashboard & API

### 9.1 Dashboard Route

```
GET /dashboard
```

A self-contained Blade page using Tailwind CSS (CDN) and Chart.js showing:
- Device status (online/offline, last heartbeat)
- Access event statistics
- Upload distribution chart
- Activity timeline
- Tabbed interface: Access Events, Door Status, Users, Raw Logs

### 9.1.1 User Registration Page

```
GET /register-user
```

A self-contained Blade page using Tailwind CSS that provides:
- 3-step registration wizard (User Info → Device Sync → Complete)
- device selector (from known device SNs seen in raw logs)
- real-time command queue timeline (`pending|sent|acked|failed`)
- merged logical command rows across `getrequest` and `service_control` channels
- user edit and delete actions in the registered users table
- paginated table of app-registered users and sync status

### 9.2 Dashboard API Endpoints

All JSON APIs are at `/api/zkteco/*`:

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/zkteco/stats` | Summary statistics (devices, counts, endpoints) |
| GET | `/api/zkteco/access-events?limit=50` | Parsed rtlog access events |
| GET | `/api/zkteco/device-status?limit=20` | Parsed rtstate door/sensor status |
| GET | `/api/zkteco/users` | Users synced from device |
| GET | `/api/zkteco/raw-logs?page=1&per_page=25` | Paginated raw logs browser |
| GET | `/api/zkteco/timeline?hours=24` | Activity data for timeline chart |
| POST | `/api/zkteco/register-user` | Register app-side user + queue sync commands |
| PUT | `/api/zkteco/device-users/{id}` | Update an app-side registered user and queue sync commands |
| DELETE | `/api/zkteco/device-users/{id}` | Queue user delete on device; local record is removed after query reconciliation confirms deletion |
| POST | `/api/zkteco/sync-device-users` | Queue full user query command (`DATA QUERY tablename=user`) for device→app reconciliation |
| GET | `/api/zkteco/device-users-list` | Paginated app-side registered users with sync summary |
| GET | `/api/zkteco/command-status/{device_sn}/{pin}` | Command timeline for a specific user/device |
| GET | `/api/zkteco/known-devices` | Known device serial numbers from raw logs |
| GET | `/api/zkteco/registration-stats` | Registration page stats cards |

Users tab behavior on `/dashboard`:
- source of truth is `zkteco_device_users` (canonical synced table)
- displayed users are filtered against the latest full `querydata` user snapshot per device, so dashboard users match machine-reported users
- supports **Edit** and **Delete** actions
- edit uses a single SweetAlert2 popup form (name, privilege, card, group)
- delete and sync actions use SweetAlert2 confirm + toast dialogs (no browser `alert/confirm/prompt`)
- supports **Sync From Device** action to queue `DATA QUERY tablename=user`
- upserts users from both `POST /iclock/cdata` (`tabledata/user`) and `POST /iclock/querydata`
- users are not hidden early on delete intent; they remain visible until reconciliation confirms removal
- delete acknowledgments trigger a follow-up `DATA QUERY tablename=user` reconciliation cycle
- user sync/delete callbacks (including failures) can queue reconciliation queries to keep app state aligned with device state
- sync badges are computed from active profile-sync command families only (`user_sync`, `face_push`, `fingerprint_push`)

### 9.3 Stats API Response Example

```json
{
    "total_logs": 1253,
    "devices": [
        {
            "sn": "VGU6251500098",
            "ip": "192.168.10.97",
            "last_seen": "2026-02-23 10:49:42",
            "is_online": true
        }
    ],
    "endpoint_stats": [
        { "endpoint": "/iclock/cdata", "method": "GET", "cnt": 522, "last_hit": "..." },
        { "endpoint": "/iclock/cdata", "method": "POST", "cnt": 282, "last_hit": "..." }
    ],
    "upload_stats": {
        "rtlog": 136,
        "rtstate": 137,
        "tabledata": 8,
        "options": 1
    },
    "today_events": 282
}
```

---

## 10. Sending Commands to the Device

### 10.1 How Commands Work

1. You add a command to a queue (database, config, cache)
2. Device polls `GET /iclock/getrequest` (every ~30s)
3. Server returns the queued command(s) in the response
4. Device executes the command
5. Device reports result via `POST /iclock/devicecmd`

### 10.2 Command Format

```
C:<CommandID>:<CommandDescription> <CommandDetail>
```

- **CommandID**: Unique integer (auto-increment or timestamp-based)
- **CommandDescription**: Command type keyword
- **CommandDetail**: Parameters

### 10.3 Common Commands

#### Query Transaction Records (Access Events)

```
C:12345:DATA QUERY tablename=transaction,fielddesc=*,filter=*
```

#### Query All Users

```
C:12346:DATA QUERY tablename=user,fielddesc=*,filter=*
```

#### Add/Update a User

```
C:12347:DATA UPDATE user	Pin=999	Name=New User	Privilege=0	CardNo=12345
```

(Note: fields are tab-separated after the command description)

#### Delete a User

```
C:12348:DATA DELETE tablename=user,filter=pin=999
```

#### Reboot Device

```
C:12349:CONTROL DEVICE 03000000
```

#### Sync Time

```
C:12350:SET OPTION DateTime=2026-02-23 16:30:00
```

### 10.4 Implementation Example

```php
// In a service class or controller action:
use Illuminate\Support\Facades\Cache;

// Queue a command for a device
function queueCommand(string $sn, string $command): int
{
    $cmdId = (int)(microtime(true) * 10) % 100000;
    $key = "zkteco_commands_{$sn}";
    $commands = Cache::get($key, []);
    $commands[] = str_replace('{CMDID}', $cmdId, $command);
    Cache::put($key, $commands, 3600);
    return $cmdId;
}

// In getRequest handler:
public function getRequest(Request $request)
{
    $sn = $request->query('SN');
    $key = "zkteco_commands_{$sn}";
    $commands = Cache::pull($key, []); // get and clear

    if (!empty($commands)) {
        return response(implode("\n", $commands) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    return response("OK\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
}
```

### 10.5 App-side Registration Queue (Implemented in this project)

This project includes a persistent command queue for app-side user sync.

Flow:
1. App calls `POST /api/zkteco/register-user`
2. Server upserts user metadata in `zkteco_device_users`
3. Server queues commands in `zkteco_device_commands` for:
    - `DATA UPDATE user`
    - `DATA UPDATE biophoto` (if `face_template` is present)
    - `DATA UPDATE biodata` (if `fingerprint_template` is present)
4. User updates use `PUT /api/zkteco/device-users/{id}` and queue fresh sync commands
5. User deletes use `DELETE /api/zkteco/device-users/{id}` and queue `DATA DELETE tablename=user,filter=pin=<pin>`
6. Full user reconciliation can be triggered via `POST /api/zkteco/sync-device-users`
7. Device receives queued lines on polling endpoints:
    - `GET /iclock/getrequest`
    - `GET /iclock/service/control`
8. Device execution result updates command status from callbacks:
    - `POST /iclock/devicecmd`
    - `POST /iclock/service/control`
9. Device user uploads and query callbacks are ingested from:
    - `POST /iclock/cdata` (`tabledata/user`)
    - `POST /iclock/querydata`
10. Registration UI polls:
    - `GET /api/zkteco/command-status/{device_sn}/{pin}` for command lifecycle

Behavior details (current implementation):
- commands are queued for both channels (`getrequest` and `service_control`) to support firmware differences
- each polling hit returns at most 3 pending commands for that channel
- command lifecycle: `pending` → `sent` → `acked` (when `Return=0`) or `failed`
- when one channel acknowledges a command, duplicate rows for the same logical command are auto-marked as acked
- delete actions queue compatibility command variants for broader firmware support (`DATA DELETE ...`, `DELETE USER`, `DeleteUser`)
- local app-side deletion is finalized after post-delete `DATA QUERY tablename=user` reconciliation confirms the PIN is absent
- reconciliation also supports explicit user-table `querydata` packets by `type=tabledata&tablename=user`, and handles `count=0` as clear-all for that device
- deleting an already-absent local user returns a non-error info response to keep delete actions idempotent in UI
- command IDs are generated from time + queue row suffix to avoid collisions during batch delivery
- each queued command is tagged with nullable `pin` so status can be queried per user

Example request:

```json
{
  "device_sn": "VGU6251500098",
  "pin": 123,
  "name": "John Doe",
  "privilege": 0,
  "face_template": "<template>",
    "fingerprint_template": "<template>"
}
```

---

## 11. Integrating with an Existing Laravel HR System

### 11.1 Step-by-Step Integration

This section describes how to add ZKTeco SenseFace 3A support to an existing Laravel 10+ HR/ERP system.

#### Step 1: Install the Database Migration

Copy the migration file and run it:

```bash
php artisan make:migration create_zkteco_raw_logs_table
```

Use the schema from [Section 8.1](#81-raw-logs-table-minimal--capture-everything). Also create the parsed tables from Sections 8.2 and 8.3.

For app-side registration sync, also include implemented tables from Sections 8.4 and 8.5.

```bash
php artisan migrate
```

#### Step 2: Create the Model

```php
// app/Models/ZktecoRawLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZktecoRawLog extends Model
{
    protected $table = 'zkteco_raw_logs';

    protected $fillable = [
        'device_sn', 'endpoint', 'method', 'ip',
        'user_agent', 'content_type', 'query_params',
        'form_params', 'headers', 'raw_body',
    ];

    protected $casts = [
        'query_params' => 'array',
        'form_params' => 'array',
        'headers' => 'array',
    ];
}
```

#### Step 3: Create the Controller

Copy `AdmsController.php` from this project. The entire protocol handler is a single file (~450 lines).

Place it at: `app/Http/Controllers/ZKTeco/AdmsController.php`

#### Step 4: Add Routes

Add to your `routes/web.php` or a separate route file:

```php
Route::prefix('iclock')->group(function () {
    Route::match(['GET', 'POST'], 'cdata', [AdmsController::class, 'cdata']);
    Route::match(['GET', 'POST'], 'registry', [AdmsController::class, 'registry']);
    Route::match(['GET', 'POST'], 'push', [AdmsController::class, 'push']);
    Route::match(['GET', 'POST'], 'ping', [AdmsController::class, 'ping']);
    Route::match(['GET', 'POST'], 'exchange', [AdmsController::class, 'exchange']);
    Route::match(['GET', 'POST'], 'getrequest', [AdmsController::class, 'getRequest']);
    Route::match(['GET', 'POST'], 'getreq', [AdmsController::class, 'getRequest']);
    Route::match(['GET', 'POST'], 'devicecmd', [AdmsController::class, 'deviceCmd']);
    Route::match(['GET', 'POST'], 'service/control', [AdmsController::class, 'serviceControl']);
    Route::match(['GET', 'POST'], 'querydata', [AdmsController::class, 'queryData']);
    Route::match(['GET', 'POST'], 'fdata', [AdmsController::class, 'fdata']);
    Route::match(['GET', 'POST'], '{any}', [AdmsController::class, 'fallback'])->where('any', '.*');
});
```

#### Step 5: Exempt from CSRF

In `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'iclock/*',
];
```

#### Step 6: Add Configuration

Create `config/zkteco.php`:

```php
return [
    'iclock' => [
        'security_push_commands' => [],
        'getrequest_commands' => [],
    ],
];
```

#### Step 7: Configure the Device

Set the device ADMS URL to point to your server:

```
http://YOUR_SERVER_IP:PORT/iclock
```

#### Step 8: Start the Server

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Make sure the device can reach this IP:port on the network.

### 11.2 Linking to HR Employee Records

The key link between the ZKTeco device and your HR system is the **PIN (User ID)**. When enrolling users on the device, assign PINs that match your HR system's employee IDs.

```php
// In your HR system
$employee = Employee::find(123);

// The device user PIN should match
// Device PIN = 123 → links to Employee ID 123

$accessEvent = ZktecoAccessEvent::where('pin', $employee->id)
    ->where('event_category', 'access')
    ->whereDate('event_time', today())
    ->get();
```

### 11.3 Processing Access Events for Attendance

Create an event listener or scheduled job that processes raw rtlog data into attendance records:

```php
// app/Services/ZktecoAttendanceService.php
class ZktecoAttendanceService
{
    public function processNewEvents(): int
    {
        $unprocessed = ZktecoRawLog::where('endpoint', '/iclock/cdata')
            ->where('method', 'POST')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(query_params, '$.table')) = 'rtlog'")
            ->where('processed', false)
            ->get();

        $count = 0;
        foreach ($unprocessed as $log) {
            $event = $this->parseRtlog($log->raw_body);
            if (!$event || $event['pin'] == '0') continue;

            // Find employee by PIN
            $employee = Employee::where('device_pin', $event['pin'])->first();
            if (!$employee) continue;

            // Only process face/fingerprint verification events
            if (!in_array($event['event'], [0, 3, 23, 26])) continue;

            // Create attendance record
            Attendance::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'date' => Carbon::parse($event['time'])->toDateString(),
                ],
                [
                    'check_in' => Carbon::parse($event['time']),
                    'device_sn' => $log->device_sn,
                    'verify_type' => $event['verifytype'],
                ]
            );

            $log->update(['processed' => true]);
            $count++;
        }

        return $count;
    }

    private function parseRtlog(string $body): ?array
    {
        $parts = preg_split('/\t+/', trim($body));
        $fields = [];
        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$key, $val] = explode('=', $part, 2);
                $fields[strtolower(trim($key))] = trim($val);
            }
        }
        return empty($fields) ? null : $fields;
    }
}
```

Schedule it to run regularly:

```php
// app/Console/Kernel.php
$schedule->call(function () {
    (new ZktecoAttendanceService)->processNewEvents();
})->everyMinute();
```

### 11.4 Syncing Users from HR to Device

To push new employees to the device:

```php
use Illuminate\Support\Facades\Cache;

public function syncEmployeeToDevice(Employee $employee, string $deviceSn)
{
    $pin = $employee->id;
    $name = $employee->full_name;
    $cmdId = (int)(microtime(true) * 10) % 100000;

    $command = "C:{$cmdId}:DATA UPDATE user\tPin={$pin}\tName={$name}\tPrivilege=0\tCardNo=";

    // Queue command for delivery on next getrequest poll
    $key = "zkteco_commands_{$deviceSn}";
    $commands = Cache::get($key, []);
    $commands[] = $command;
    Cache::put($key, $commands, 3600);
}
```

---

## 12. Troubleshooting

### Device Not Connecting

| Symptom | Cause | Fix |
|---|---|---|
| No logs at all | Device can't reach server | Check network, firewall, port 8000 open |
| Only GET /iclock/cdata | Server not returning `registry=ok` | Verify init response format |
| Repeated registry without uploads | Missing RegistryCode | Check registry response has `RegistryCode=...` |
| No rtlog events | `Realtime=0` in config | Set `Realtime=1` in init response |
| 419 errors in Laravel log | CSRF blocking requests | Add `iclock/*` to CSRF exceptions |
| Double-path URLs | Device firmware bug | Fallback handler catches these automatically |

### Device Connects But No Events

The device only sends `rtlog` events when something actually happens:
- Face scan (event 0 or 3)
- Door open/close (events 100-102, 206)
- Alarm triggered (events 50-57)

If the device is idle, you'll only see `GET /iclock/getrequest` polling every ~30 seconds. This is **normal behavior**.

### Verifying Communication

Run this SQL query to check communication health:

```sql
SELECT endpoint, method, COUNT(*) as cnt, MAX(created_at) as last_hit
FROM zkteco_raw_logs
GROUP BY endpoint, method
ORDER BY cnt DESC;
```

Expected output for a healthy connection:

| endpoint | method | cnt | last_hit |
|---|---|---|---|
| /iclock/cdata | GET | 500+ | recent |
| /iclock/registry | POST | 300+ | recent |
| /iclock/cdata | POST | 200+ | recent |
| /iclock/getrequest | GET | 50+ | within last minute |
| /iclock/ping | GET | 1+ | recent |
| /iclock/push | POST | 1+ | recent |

### Common Error: Tabledata Re-uploads

If the device keeps re-uploading the same `tabledata` (e.g., user records over and over), check your acknowledgment response. For `tabledata` uploads, you MUST respond with `<tablename>=<count>` NOT just `OK`.

---

## 13. Event Code Reference

### Access Events (0-49)

| Code | Label | Description |
|---|---|---|
| 0 | Normal Punch Open | Standard credential verification opens door |
| 1 | Punch During Normal Open | Verification during scheduled open period |
| 2 | First Card Normal Open | First card triggers scheduled open mode |
| 3 | Multi-Card Open | Multiple credentials required to open |
| 4 | Emergency Password Open | Emergency password used |
| 5 | Door Open During Normal Open | Door opened during scheduled period |
| 8 | Remote Open | Door opened via server command |
| 9 | Remote Close | Door closed via server command |
| 23 | Press Fingerprint Open | Fingerprint verification opens door |
| 26 | Card + Fingerprint Open | Multi-factor: card + fingerprint |

### Alarm Events (50-99)

| Code | Label | Description |
|---|---|---|
| 50 | Threat Alarm | Duress code triggered |
| 51 | Entry Keypad Tamper | Unauthorized keypad access attempt |
| 52 | Host Unit Tamper | Device casing opened |
| 53 | Verify Failed Alarm | Too many failed verifications |
| 54 | Forced Lock Alarm | Door forced open |
| 55 | Anti-Passback Alarm | Anti-passback violation |

### Door Events (100-199)

| Code | Label | Description |
|---|---|---|
| 100 | Door Open | Door physically opened |
| 101 | Door Close | Door physically closed |
| 102 | Door Close (Timeout) | Door auto-closed after timeout |
| 103 | Door Forced Open | Door forced open without authorization |
| 104 | Door Held Too Long | Door left open too long |

### System Events (200-255)

| Code | Label | Description |
|---|---|---|
| 200 | Normal Open Time Start | Scheduled open period begins |
| 201 | Normal Open Time End | Scheduled open period ends |
| 206 | Door Opened Correctly | Authorized door open confirmed |
| 220 | Auxiliary Output Open | Auxiliary relay activated |
| 221 | Auxiliary Output Close | Auxiliary relay deactivated |

### Verification Types

| Code | Method |
|---|---|
| 0 | Password |
| 1 | Fingerprint |
| 2 | Card |
| 15 | Face |
| 200 | System/Auto |

---

## 14. Security Considerations

### Network Security

- Run the server behind a reverse proxy (Nginx/Apache) in production
- Use HTTPS if the device supports it (check firmware)
- Restrict the `/iclock/*` endpoints to the device's IP using middleware or firewall rules
- The ADMS protocol transmits data in **plain text** — use a private network or VPN

### Data Security

- Raw logs contain biometric data (face photos, fingerprint templates) — encrypt at rest
- Implement proper access control for the dashboard
- Consider purging old raw logs after processing
- The `RegistryCode` is not a strong security mechanism — it's primarily for device identification

### Production Recommendations

1. **Use a real database** (MySQL/PostgreSQL) — not SQLite
2. **Add authentication** to the dashboard (Laravel Breeze/Sanctum)
3. **Process raw logs asynchronously** (Laravel Queues) to avoid blocking the HTTP response
4. **Set up monitoring** — alert if a device stops polling for > 5 minutes
5. **Implement log rotation** — the `zkteco_raw_logs` table grows quickly; archive or prune after parsing
6. **Run on a dedicated port** — keep ADMS traffic separate from your main web app

### IP Whitelisting Middleware Example

```php
// app/Http/Middleware/ZktecoIpWhitelist.php
class ZktecoIpWhitelist
{
    public function handle($request, Closure $next)
    {
        $allowed = ['192.168.10.97']; // device IPs
        if (!in_array($request->ip(), $allowed)) {
            abort(403, 'Unauthorized device');
        }
        return $next($request);
    }
}
```

---

## Appendix A: Files in This Project

| File | Purpose |
|---|---|
| `app/Http/Controllers/ZKTeco/AdmsController.php` | Core ADMS protocol handler |
| `app/Http/Controllers/ZKTeco/DashboardController.php` | Dashboard page & JSON API |
| `app/Http/Controllers/ZKTeco/UserRegistrationController.php` | Registration page + app-side registration/sync APIs |
| `app/Services/ZKTeco/DeviceCommandQueue.php` | Persistent queue service for user/face/fingerprint device sync |
| `app/Models/ZktecoRawLog.php` | Raw log Eloquent model |
| `app/Models/ZktecoDeviceUser.php` | Device user upsert model |
| `app/Models/ZktecoDeviceCommand.php` | Device command queue model |
| `config/zkteco.php` | Command queue configuration |
| `database/migrations/2026_02_22_000000_create_zkteco_raw_logs_table.php` | Database schema |
| `database/migrations/2026_03_02_000001_create_zkteco_device_users_table.php` | Device user sync table |
| `database/migrations/2026_03_02_000002_create_zkteco_device_commands_table.php` | Device command queue table |
| `database/migrations/2026_03_02_000003_add_pin_to_zkteco_device_commands_table.php` | Add nullable `pin` to command queue for per-user status tracking |
| `resources/views/zkteco/dashboard.blade.php` | Dashboard UI (Tailwind + Chart.js) |
| `resources/views/zkteco/register.blade.php` | Registration + sync wizard UI (Tailwind) |
| `routes/web.php` | Route definitions |
| `routes/api.php` | Dashboard + registration status APIs |

## Appendix B: Quick Reference Card

```
┌─────────────────────────────────────────────────────────────────┐
│  ZKTeco SenseFace 3A ↔ Laravel ADMS Quick Reference             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Device Config:  Server URL = http://SERVER_IP:8000/iclock      │
│  Protocol:       Security PUSH v3.1.2 (A&C PUSH, NOT T&A)      │
│  Data Format:    Tab-separated key=value, text/plain            │
│  Line Ending:    \r\n (CRLF)                                    │
│                                                                 │
│  ENDPOINTS:                                                     │
│  GET  /iclock/cdata         → Init (return registry=ok + cfg)   │
│  POST /iclock/cdata         → Data upload (return OK or name=n) │
│  POST /iclock/registry      → Registration (return RegistryCode)│
│  POST /iclock/push          → Config download                   │
│  GET  /iclock/getrequest    → Command poll (return OK or C:...) │
│  GET  /iclock/ping          → Heartbeat (return OK)             │
│  POST /iclock/devicecmd     → Command result (return OK)        │
│                                                                 │
│  CRITICAL RULES:                                                │
│  ✓ CSRF exempt iclock/* routes                                  │
│  ✓ Always return text/plain; charset=UTF-8                      │
│  ✓ tabledata ACK must be "tablename=count" NOT "OK"             │
│  ✓ registry response must include Set-Cookie header             │
│  ✓ Fallback route catches double-path firmware bugs             │
│                                                                 │
│  DASHBOARD:  http://SERVER:8000/dashboard                       │
│  REG PAGE:   http://SERVER:8000/register-user                   │
│  API:        http://SERVER:8000/api/zkteco/stats                │
│  API:        POST /api/zkteco/register-user (queue sync)        │
│  API:        PUT  /api/zkteco/device-users/{id}                 │
│  API:        DELETE /api/zkteco/device-users/{id}               │
│  API:        POST /api/zkteco/sync-device-users                 │
│  API:        GET  /api/zkteco/command-status/{sn}/{pin}         │
│  API:        GET  /api/zkteco/known-devices                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```
