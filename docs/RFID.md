# RFID access control & reader integration

Vaasal Villa HMS decides **who may open which door** and records every decision. It is hardware-ready but
vendor-neutral: any RFID/NFC reader or door controller that can make an HTTPS request can use it.

> **Status.** The decision engine, API, logs, attendance punching and the in-app simulator are complete and tested.
> No physical reader has been connected yet. Nothing in this system claims a real door opened until a real device
> calls the API below and actuates its own relay.

## Building blocks (existing tables, extended — no parallel RFID tables)

| Concept | Table / model | Notes |
|---|---|---|
| Card stock | `key_cards` / `KeyCard` | `uid`, `type` (guest, staff, housekeeping, zone, master, maintenance), `status` (available, active, lost, blocked, damaged, retired) |
| Who holds a card, for which doors, when | `key_card_assignments` / `KeyCardAssignment` | guest (booking + villa) or employee; `access_level`, `zone`, `lock_refs`, `valid_from`/`valid_to`, status active/expired/revoked/lost |
| Staff badge | `employees.rfid_uid` | Time clocks and staff doors; never guest villas |
| Readers | `attendance_devices` / `AttendanceDevice` | `purpose` = attendance, access or both · `villa_id` and/or `zone` = the door it guards · `mode` = hardware or simulator · bearer token (SHA-256 hash stored) |
| Every decision | `access_logs` / `AccessLog` | `granted`, `reason`, `card_uid`, `key_card_id`, `employee_id`, `device_id`, `villa_id`, `zone`, `source` (device, simulator, online, system) |
| Card encoding | `lock_jobs` + Lock Bridge | Unchanged — see `lock-bridge/README.md` |

## Decision rules (`App\Modules\KeyCards\Services\RfidAccessService`)

1. Unknown UID → **deny** "Unknown card".
2. Key card that is lost / blocked / damaged / retired → **deny** (lost or blocked also raises a notification to `keycards.manage`).
3. No active assignment → **deny** "Card expired", "Card revoked (…)" or "Card not assigned".
4. Outside `valid_from` … `valid_to` → **deny**.
5. Master card → **allow** everywhere. Cards with `ALL` in `lock_refs` (e.g. housekeeping) → **allow** every door.
6. Villa door: allowed when the villa's `lock_ref` (or code) is in the card's `lock_refs`.
7. Zone door: guest cards also open the shared guest zones (`config/vaasal.php → locks.guest_zones`); zone cards open their zone.
8. Employee badge (no key card): staff zones by department (`locks.staff_zones`), never guest villas; the employee must be active.
9. Reader with `purpose` attendance/both: an allowed employee scan also **clocks in / out** through `AttendanceService` (late, overtime, overnight shifts handled there).

## Device API

```
POST /api/v1/rfid/scan
Authorization: Bearer <device token>
Content-Type: application/json

{ "identifier_type": "rfid", "identifier": "04AABBCC1122", "occurred_at": "2026-09-29T10:30:00+05:30" }
```

Optional for a floating reader with no fixed door: `"villa_code": "G1"` and/or `"zone": "Villa Area"`.
A reader registered for a villa or zone always reports that door; the payload cannot override it.

Response `200`:

```json
{ "decision": "allow", "granted": true, "reason": "All doors · Housekeeping card", "holder": "Malini Ratnam",
  "holder_type": "staff", "target": "Villa Area", "attendance": { "action": "clock_in", "time": "10:31", "late_minutes": 0, "worked": "0h 00m" },
  "event_id": 812, "device": "Villa Area gate" }
```

Other responses: `401` unknown/inactive token · `403` simulator-mode device while `RFID_SIMULATOR=false` · `422` invalid payload or
`occurred_at` more than 5 minutes ahead / 2 days behind server time. Rate limit: 240 requests/minute per client.

**Reader behaviour:** open the relay only when `decision` is `allow`; treat any network error or non-200 as **deny**
(fail-secure). Readers should buffer scans while offline and replay them with the original `occurred_at`.

## Registering a reader

Admin → **Access control → RFID devices → Register device**. Choose the purpose and the villa and/or zone it guards.
The API token is shown **once**; paste it into the reader's configuration. "Rotate token" issues a new one and
disables the old one immediately.

## Simulator (development)

Admin → **Access control → Simulator** (only when `RFID_SIMULATOR=true`). Enter a UID, pick a villa door, zone or
device, and see **Access Granted / Access Denied** with the holder and reason. Sample UIDs are listed on the page.
The same engine as the API is used and the result is written to the access log with `source = simulator`.

Local demo data (`AccessAndStaffDemoSeeder`):

| UID | What it is |
|---|---|
| `04AABBCC1122` | Housekeeping key card (Malini Ratnam), all doors, valid one year |
| `04EE00000001` | Guest card whose validity has ended → denied "Card expired" |
| `04B1C2D3E401` … `E406` | Employee badges (housekeeping, maintenance, kitchen, reception, general staff) |
| token `rfid-demo-token` | "Staff entrance clock" reader (zone Staff Entrance, access + attendance, simulator mode) — **local only** |

```bash
curl -X POST http://localhost/vaasal_villa_hospitality_management_system28/public/api/v1/rfid/scan \
  -H "Authorization: Bearer rfid-demo-token" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"identifier_type":"rfid","identifier":"04AABBCC1122"}'
```

## Going live checklist

1. Confirm the reader/lock vendor and model; implement its adapter in the Lock Bridge for card **encoding**
   (`lock-bridge/src/Adapters/VendorSdkAdapter.php`), and have readers call `/api/v1/rfid/scan` for **decisions**.
2. Register every reader with its door/zone; store tokens only on the device.
3. Set `RFID_SIMULATOR=false` and delete simulator-mode devices.
4. Serve the API over HTTPS only; restrict it to the property network or a VPN at the firewall.
5. Test fail-secure behaviour (network down → door stays locked; mechanical override key available to management).
