# Lock Bridge

The Lock Bridge connects the Vaasal Villa HMS (web) to the RFID card encoder and door-lock system inside the property network.

```
Front desk browser ──► HMS (cloud / XAMPP) ──► lock_jobs queue
                                   ▲
            HTTPS, outbound only   │  poll jobs · post results · upload door events
                                   │
                         Lock Bridge (this folder, on the front-office PC)
                                   │  vendor SDK / TCP PMS interface
                                   ▼
                      Card encoder · lock server · door locks
```

* Card numbers, grants and validity are decided by the HMS; the bridge only executes jobs.
* The bridge **never accepts inbound connections** — nothing on the property network is exposed.
* A job is marked done only when the adapter reports success; failures are shown to the receptionist with a retry button.

## Run

```bash
cp .env.example .env        # set HMS_API_URL and LOCK_BRIDGE_TOKEN
php bridge.php --once       # one cycle (test)
php bridge.php              # run continuously
```

In the HMS `.env` set `LOCK_DRIVER=bridge` so jobs wait for this bridge (with `LOCK_DRIVER=simulator` the web app completes jobs itself for development).

Install as a Windows service with [NSSM](https://nssm.cc/): `nssm install VaasalLockBridge "C:\xampp\php\php.exe" "C:\path\to\lock-bridge\bridge.php"`.

## Adding the real lock vendor

1. Confirm brand/model and obtain the PMS interface documentation/SDK (e.g. ASSA ABLOY VingCard **Visionline/Vostio**, dormakaba **Ambiance**, **Salto Space**, **Onity**).
2. Copy `src/Adapters/VendorSdkAdapter.php` to `src/Adapters/<Vendor>Adapter.php` and implement the six methods of `LockAdapter`.
3. Select it in `bridge.php` (`$adapter = …`) and set `LOCK_VENDOR` in `.env`.
4. Map each villa's door **Lock ID** in HMS → Villas → Edit (sent to the bridge as `lock_refs`).

## API (served by the HMS)

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/v1/lock-bridge/heartbeat` | Liveness, version, vendor |
| GET | `/api/v1/lock-bridge/jobs?limit=5` | Claim pending jobs (atomic) |
| POST | `/api/v1/lock-bridge/jobs/{uuid}/result` | `{success, message, card_uid, encoder}` |
| POST | `/api/v1/lock-bridge/events` | `{events: [{lock_ref, card_uid, event, occurred_at, source}]}` |

All calls use `Authorization: Bearer <token>`; the server stores only a SHA-256 hash of the token.
