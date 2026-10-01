# Vaasal Villa Hospitality Management System (HMS 28)

An integrated management system for Vaasal Villa (Jaffna, Sri Lanka). It includes:

- a customer website with online booking;
- reservations, front desk, folio and billing;
- channel manager and OTA integration;
- tour-operator portal;
- RFID key cards through the Lock Bridge, RFID reader API, access logs and a simulator;
- housekeeping and maintenance;
- staff, rosters, attendance and leave;
- restaurant POS, KDS and inventory, cashier shifts with day-end closing and a Today cash & bank view;
- display currencies (LKR base), CMS-managed social links and stock-media search;
- reports.

**Vaasal Villa · Jaffna, Sri Lanka · 0764413420 · WhatsApp +94764413420 · aflalal2004@gmail.com** — edit these in Admin → Website content.

Everything runs on **one central MySQL database** and a shared service layer.

This is a standalone project. It has its own database (`vaasal_villa_hms28`), configuration, authentication, uploads and logs. It does **not** use or modify `vaasal_villa_integrated_management_system3`.

| | |
|---|---|
| Stack | Laravel 12 · PHP 8.2 · MySQL/MariaDB (XAMPP) · Blade + vanilla CSS/JS (no Node build) |
| Local URL | http://localhost/vaasal_villa_hospitality_management_system28/public |
| Staff sign-in | …/public/login |
| Tests | `php artisan test`: 51 feature tests against a separate test database |

## Quick start

```bash
cd C:\xampp\htdocs\vaasal_villa_hospitality_management_system28
php -d extension=zip composer.phar install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Upgrading an existing HMS 28 database (keeps all data):

```bash
php artisan migrate
php artisan db:seed --class=AccessAndStaffDemoSeeder
php artisan db:seed --class=RestaurantDemoSeeder
php artisan optimize:clear
```

Create the empty `vaasal_villa_hms28` database in phpMyAdmin (utf8mb4) before running `migrate`. Full instructions are in [docs/SETUP.md](docs/SETUP.md).

## Demo accounts (local only)

Sign in with the **username** or the email. All accounts use the password **`Vaasal@2026`**. Change it before any non-local use.
With `APP_ENV=local` (and `DEMO_LOGIN=true`) the sign-in page shows **Quick role sign-in** cards that fill the username and password;
the panel and the password are never rendered in any other environment.

| Role | Username | Email | Lands on |
|---|---|---|---|
| Administrator (all permissions) | `admin` | admin@vaasalvilla.test | Dashboard |
| Owner | `owner` | owner@vaasalvilla.test | Dashboard / reports |
| Hotel manager (Hotel PMS only) | `manager` | manager@vaasalvilla.test | Admin console dashboard |
| Reception | `reception` | reception@vaasalvilla.test | Front desk |
| Accountant | `accounts` | accounts@vaasalvilla.test | Payments |
| Restaurant manager (Restaurant POS only) | `posmanager` | posmanager@vaasalvilla.test | POS dashboard |
| Cashier | `cashier` | cashier@vaasalvilla.test | POS terminal |
| Waiter | `waiter` | waiter@vaasalvilla.test | POS terminal |
| Kitchen | `kitchen` | kitchen@vaasalvilla.test | Kitchen display |
| Stores / inventory | `stores` | stores@vaasalvilla.test | POS inventory |
| Housekeeping supervisor | `hksupervisor` | hksupervisor@vaasalvilla.test | Housekeeping board |
| Housekeeper | `housekeeping`, `housekeeping2` | housekeeping@vaasalvilla.test | My tasks |
| Maintenance | `maintenance` | maintenance@vaasalvilla.test | Maintenance |
| General staff | `staff` | staff@vaasalvilla.test | My time card |
| Tour operator (Sunrise Tours Lanka) | `operator` | operator@sunrise-tours.test | Partner portal |
| Tour operator (EuroAsia Journeys GmbH) | `katrin` | katrin@euroasia.test | Partner portal |

Local-only secrets:

- Attendance kiosk device token: `device-kiosk-token`
- RFID reader "Staff entrance clock" token: `rfid-demo-token` (see [docs/RFID.md](docs/RFID.md))
- Lock Bridge token: `LOCK_BRIDGE_TOKEN` in `.env`

The sandbox payment page approves or declines test payments without any card data.

## Environment variables added in this release

| Variable | Purpose | Default |
|---|---|---|
| `VV_CONTACT_PHONE`, `VV_CONTACT_EMAIL` | Contact fallbacks until set in Admin → Website content | 0764413420, aflalal2004@gmail.com |
| `WHATSAPP_NUMBER` | International WhatsApp number (digits) | 94764413420 |
| `EXCHANGE_RATE_API_KEY` | exchangerate-api.com v6 key for live display rates (optional) | empty → manual/static rates |
| `EXCHANGE_RATE_CACHE_MINUTES` | Rate cache lifetime | 360 |
| `UNSPLASH_ACCESS_KEY`, `PEXELS_API_KEY` | Stock photo/video search in Admin → Website content → Stock media (optional, server-side only) | empty → own gallery |
| `MEDIA_API_CACHE_MINUTES` | Stock-media search cache | 1440 |
| `DEMO_LOGIN` | Quick role sign-in cards (only ever shown when `APP_ENV=local`) | true |
| `RFID_SIMULATOR` | In-app RFID simulator and simulator-mode readers — set `false` in production | true |

## Documentation

| Doc | Contents |
|---|---|
| [docs/SETUP.md](docs/SETUP.md) | Installation, database, `.env`, scheduler, Lock Bridge, going live with Stripe, Channex and WhatsApp |
| [docs/MODULES.md](docs/MODULES.md) | Architecture and how to use each module |
| [docs/API.md](docs/API.md) | REST API v1, Lock Bridge protocol, attendance devices, webhooks |
| [docs/REQUIREMENTS_TRACEABILITY.md](docs/REQUIREMENTS_TRACEABILITY.md) | Every spec requirement mapped to its module, code and test, with status |
| [docs/BRAND.md](docs/BRAND.md) | Logo usage, animation principles, website animation catalogue |
| [docs/INTEGRATION.md](docs/INTEGRATION.md) | Hotel PMS / Restaurant POS boundary, roles, the four integration points |
| [docs/RFID.md](docs/RFID.md) | RFID access rules, reader API, simulator, going-live checklist |
| [lock-bridge/README.md](lock-bridge/README.md) | On-premise RFID Lock Bridge agent |

## Known limitations

- **RFID locks.** The vendor adapter (`lock-bridge/src/Adapters/VendorSdkAdapter.php`) is a documented stub until the lock brand and model are confirmed. The simulator adapter runs the full encode, revoke and audit flow end to end.
- **Stripe and Channex** drivers are implemented against their HTTP APIs but have not been exercised with live keys. Local development uses the sandbox payment page and the null channel driver.
- **PDFs** (invoice, folio, voucher, confirmation, statement) are print-optimised HTML, saved as PDF through the browser's print dialog. No server-side PDF engine is installed.
- **Website photos** are Unsplash stand-ins with a local fallback. Replace them with property photography, or pick licensed stock through Admin → Website content → Stock media (needs `UNSPLASH_ACCESS_KEY` / `PEXELS_API_KEY`).
- **Exchange rates** are static reference rates until `EXCHANGE_RATE_API_KEY` is set or rates are entered in Settings → Display currencies. Money is always stored and charged in LKR.
- **RFID readers**: no physical reader has been connected. The API, decision engine, logs and simulator are complete; see docs/RFID.md.
- Features the spec lists that are not built yet are marked **Deferred** in the traceability matrix: offline POS and housekeeping, and immutable PDF archiving.
