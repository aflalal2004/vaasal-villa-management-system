# Setup & Configuration

## 1. Requirements

| Component | Version | Notes |
|---|---|---|
| XAMPP | PHP 8.2+, MariaDB 10.4+ or MySQL 8 | Enable `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip` (the `zip` extension is only needed for Composer) |
| Composer | 2.x | `composer.phar` is bundled in the project root |
| Node | not required | CSS and JS are plain files in `public/assets` |

The project folder is `C:\xampp\htdocs\vaasal_villa_hospitality_management_system28`. It is fully independent: it has its own database, `.env`, sessions, uploads (`storage/app`) and logs (`storage/logs`).

## 2. Install

```bash
cd C:\xampp\htdocs\vaasal_villa_hospitality_management_system28
php -d extension=zip composer.phar install
copy .env.example .env
php artisan key:generate
```

## 3. Database

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. In phpMyAdmin, create two databases, both with collation `utf8mb4_unicode_ci`:
   - `vaasal_villa_hms28` for the application;
   - `vaasal_villa_hms28_test`, used only by `php artisan test`.
3. Check the `DB_*` settings in `.env`:
   ```ini
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vaasal_villa_hms28
   DB_USERNAME=root
   DB_PASSWORD=
   ```
4. Run the migrations and load the demo data, then link public storage:
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```
   To reset the database later, run `php artisan migrate:fresh --seed`. This **erases** all data.

### Schema overview (8 migration groups)

| Migration | Tables |
|---|---|
| `000001_core` | properties, settings, users, roles, permissions, login logs, audit logs, notifications, document sequences |
| `000002_villa` | villa types, villas, facilities, media, seasons, rate plans, rates, villa blocks |
| `000003_booking` | guests, bookings, booking villas, **inventory_nights** (UNIQUE `villa_id` + `stay_date`), booking events, conflicts, channels, channel sync logs (outbox), webhook inbox (UNIQUE `provider` + `event_id`), operators, contracts |
| `000004_folio` | folios, folio lines (insert-only), payments, payment intents, invoices, invoice lines, city ledger, commissions |
| `000005_staff` | departments, employees, shifts, rosters, attendance punches and days, leave types and requests, attendance devices |
| `000006_access_hk` | key cards, lock bridges, key card assignments, lock jobs, access logs, housekeeping tasks and checklists, linen, lost & found, maintenance tickets |
| `000007_pos` | outlets, tables, menu categories and items, modifiers, orders, order items, KOTs, POS invoices and payments, shifts, cash movements, stock items, recipes, suppliers, purchase orders, stock movements |
| `000008_website` | pages, sections, offers, gallery, testimonials, enquiries |

## 4. Application settings (`.env`)

| Key | Default | Purpose |
|---|---|---|
| `APP_URL` | `http://localhost/vaasal_villa_hospitality_management_system28/public` | Used for absolute links, emails and webhooks |
| `APP_TIMEZONE` | `Asia/Colombo` | Business dates and night audit |
| `SESSION_LIFETIME` | `30` | Minutes of idle time before sign-out |
| `VV_CURRENCY` | `LKR` | Settlement currency |
| `VV_HOLD_MINUTES` | `15` | How long a website booking holds the villa while the guest pays |
| `VV_LOGIN_MAX_ATTEMPTS` / `VV_LOGIN_LOCK_MINUTES` | `5` / `15` | Account lockout |
| `MAIL_MAILER` | `log` | Emails are written to `storage/logs/laravel.log`. Set SMTP details to send real mail. |

The root `.htaccess` sends every request to `public/` and blocks `.env` and Composer files. On a production server, point the vhost document root at `public/`.

## 5. Scheduler (required)

The following jobs run from `routes/console.php`:

| Command | Schedule | Purpose |
|---|---|---|
| `hms:expire-holds` | every minute | Releases unpaid website holds back to every channel |
| `hms:expire-cards` | every 15 min | Expires key-card grants that are past checkout |
| `hms:channel-retry` | every 5 min | Retries failed availability and rate pushes to the channel manager |
| `hms:night-audit` | 02:00 daily | Posts room charges, flags no-shows and rolls the business date |

- **Windows:** create a Task Scheduler job that runs every minute: `C:\xampp\php\php.exe C:\xampp\htdocs\vaasal_villa_hospitality_management_system28\artisan schedule:run`
- **Linux:** add the cron line `* * * * * php /path/artisan schedule:run >> /dev/null 2>&1`

You can also run any command by hand, for example `php artisan hms:night-audit`.

## 6. Integrations

### Payments: card data never touches this server

The app only creates a hosted checkout session. The guest enters card details on the provider's page. The result arrives through a signed webhook and the browser return URL; both are idempotent.

| Driver | `.env` |
|---|---|
| Sandbox (local) | `PAYMENT_DRIVER=sandbox`. The approve/decline page is at `/pay/{token}/sandbox`. |
| Stripe Checkout | `PAYMENT_DRIVER=stripe`, `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`. Webhook URL: `{APP_URL}/webhooks/payments/stripe`, event `checkout.session.completed`. |

If a payment arrives after the hold has expired, the booking is reinstated automatically when the villa is still free. Otherwise the booking stays expired and managers with `payments.refund` get a **payment.late** alert to arrange a refund or alternative dates.

### Channel manager (OTAs)

| Driver | `.env` |
|---|---|
| Null (local) | `CHANNEL_DRIVER=null`. Pushes are logged in the outbox (`channel_sync_logs`) and marked sent. |
| Channex | `CHANNEL_DRIVER=channex`, `CHANNEX_API_KEY`, `CHANNEX_PROPERTY_ID`, `CHANNEX_WEBHOOK_SECRET`, `CHANNEX_BASE_URL`. Webhook URL: `{APP_URL}/webhooks/channel/channex`. |

To finish the Channex setup:

1. Map villa types and rate plans to Channex room types and rates in **Admin → Channels**.
2. OTA reservations that would double-book go to **Admin → Conflicts**, and managers are alerted.

### RFID locks: Lock Bridge

The HMS never talks to the lock hardware directly. It queues `lock_jobs`, and the on-premise **Lock Bridge** agent (`lock-bridge/`) runs on the front-desk PC that has the encoder attached. The agent polls for jobs, executes them through the vendor adapter and reports the results. See [lock-bridge/README.md](../lock-bridge/README.md).

| Mode | Setting |
|---|---|
| Simulator (no hardware) | `LOCK_DRIVER=simulator` in the app `.env`. Jobs complete instantly inside the app. |
| Bridge | `LOCK_DRIVER=bridge` and `LOCK_BRIDGE_TOKEN=<long random>`, then run `php lock-bridge/bridge.php` on the encoder PC with its own `.env`. |

### WhatsApp, maps, weather, social

| Feature | Setting |
|---|---|
| Click-to-chat | `WHATSAPP_NUMBER` (international format without `+`) |
| WhatsApp Cloud API messages (optional) | `WHATSAPP_CLOUD_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID` |
| Map | `GOOGLE_MAPS_EMBED_QUERY` (a keyless embed); `VV_LATITUDE` / `VV_LONGITUDE` |
| Weather | Open-Meteo, no key needed; `WEATHER_ENABLED=true` |
| Social links | `SOCIAL_FACEBOOK`, `SOCIAL_INSTAGRAM`, `SOCIAL_TIKTOK`, `SOCIAL_YOUTUBE`. These can also be edited in Admin → Settings. |

### Attendance devices

Register a device in **Admin → Staff → Devices**. The kiosk seeded for local use has the token `device-kiosk-token`. The device calls `POST /api/v1/attendance/punch` with that bearer token. See [API.md](API.md).

## 7. Tests

```bash
php artisan test
```

The tests use `vaasal_villa_hms28_test`, the sandbox, simulator and null drivers, and have weather turned off (see `phpunit.xml`). Each test migrates and seeds a fresh database.

## 8. Security checklist before going live

1. Set `APP_ENV=production` and `APP_DEBUG=false`, and serve over HTTPS. Setting `SESSION_SECURE_COOKIE=true` is also recommended.
2. Change every demo password, or run `migrate:fresh` without `--seed` and create an admin with `php artisan tinker`.
3. Rotate `LOCK_BRIDGE_TOKEN` and the device tokens. Set real webhook secrets.
4. Uploaded ID and passport scans are stored on the private disk (`storage/app/private`) and served only to authorised users. Guest ID numbers are encrypted at rest with `APP_KEY`: back up the key, and never change it on a live database.
5. Back up the database daily. Folio lines and invoices are insert-only financial records.
