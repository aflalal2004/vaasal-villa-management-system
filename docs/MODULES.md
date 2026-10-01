# Architecture & Module Guide

## Architecture

The system is a **modular monolith**: one Laravel application with one database and one service layer.

```
app/
  Models/                      Eloquent models (≈80), all extend BaseModel
  Modules/<Module>/
    Services/                  business rules, the only place that writes to the database
    Http/Controllers/          thin controllers: validate → call service → redirect with a flash message
    Http/Requests/             form requests (validation)
    (Contracts|Drivers|Gateways|Exceptions)/
  Support/helpers.php          money(), fmt_date(), setting(), property(), whatsapp_link() …
routes/  web.php (website) · admin.php (/admin) · pos.php (/pos) · operator.php (/partners) · api.php (/api/v1) · console.php (jobs)
resources/views/  layouts · components · admin · pos · operator · website · print · emails · errors
public/assets/    css/app.css (back office + POS) · css/site.css (website) · js/*.js · brand/*.svg
lock-bridge/      on-premise RFID agent (a separate PHP CLI)
```

Modules call each other's **services**, never each other's controllers. For example:

- the POS calls `RoomChargeService` → `FolioService`;
- checkout calls `KeyCardService`, `HousekeepingService` and `InvoiceService`;
- a booking change calls `AvailabilityService` and `ChannelSyncService`.

Integrity rules are enforced in the database:

- **Inventory:** UNIQUE(`villa_id`, `stay_date`) prevents double booking, whatever the channel.
- **Folio:** lines are insert-only, and corrections are made by reversal.
- **Documents:** numbers come from a row-locked sequence, which keeps them gap-free.
- **Webhooks:** UNIQUE(`provider`, `event_id`) ensures each event is processed once (idempotency).

**Errors:** a `BusinessRuleException` becomes a red flash message on the web, or a 422 JSON response from the API. Unexpected errors are logged to `storage/logs` and shown on the branded error pages.

## Using the modules

| Module | Where | Main tasks |
|---|---|---|
| **Dashboard** | `/admin` | Today's arrivals, departures and in-house guests; occupancy; revenue; open conflicts; housekeeping and maintenance status; weather |
| **Bookings** | Admin → Bookings / Calendar | Create a booking (phone, walk-in, email) → confirm → change dates or villa → cancel with the policy fee. Calendar: block and unblock villas. The booking page shows the timeline, folio, payments and documents (confirmation, voucher, registration card). |
| **Guests** | Admin → Guests | Profiles; VIP and blacklist flags; ID/passport upload (private, encrypted number); stay history |
| **Front desk** | Admin → Front desk | Arrivals, in-house, departures. **Check-in** requires a Ready villa, records guest IDs and issues key cards. **Check-out** is one transaction: posts final charges, requires a zero balance (or city-ledger transfer for operators), issues one combined invoice, revokes keys and creates the departure clean. |
| **Billing** | Admin → Invoices / Payments | Folio posting and reversal, receiving payments (cash, card reference, bank, online link), invoices and receipts, operator payments with proof, refunds |
| **Channels** | Admin → Channels / Conflicts | Map villa types and rate plans to the channel manager; view the ARI outbox and retry; resolve OTA conflicts (relocate or reject) |
| **Villas & rates** | Admin → Villas / Villa types / Rates / Offers | Villa CRUD with archive, lock reference, facilities, media; seasons and rate plans (min stay, deposit %, cancellation policy); offers and promo codes |
| **Tour operators** | Admin → Operators; partner portal `/partners` | Approve registrations, set contracts and credit limits. Partners check availability and create group bookings, upload rooming lists (CSV), view invoices, pay with proof and download statements. |
| **Key cards** | Admin → Key cards | Card inventory; issue a new key or duplicate; extend; lost card → new key; revoke; staff cards (master/zone/HK); access history; Lock Bridge status and token rotation |
| **Housekeeping** | Admin → Housekeeping | Status board, task generation, assignment, *My tasks* (start → checklist → request inspection), supervisor approve or reject, linen, lost & found |
| **Maintenance** | Admin → Maintenance | Tickets with severity. A blocking ticket takes the villa out of order until it is resolved. |
| **Staff** | Admin → Staff | Employees, departments, roster, attendance (kiosk/device API, corrections with reason, CSV export), leave requests and approvals, *My time card* |
| **POS** | `/pos` | Open a shift with a float → table → add items and modifiers → fire (KOT/KDS) → split, merge or transfer → bill (discounts need permission) → pay by cash, card or **charge to villa** → receipt. Also KDS, check history and refunds, shift close (blind count), menu and tables, inventory (stock, recipes, suppliers, POs), reports. |
| **Reports** | Admin → Reports | 15 reports: bookings; occupancy, ADR & RevPAR; revenue by department; payments; outstanding balances; guest statistics; OTA & channel production; tour operator bookings & commission; POS sales; POS item mix; inventory valuation & wastage; housekeeping; attendance; maintenance; key card activity. Each can be printed or exported to CSV. |
| **Website CMS** | Admin → Website | Pages and sections, gallery, testimonials, offers, enquiries, social links, contact details |
| **Users & roles** | Admin → Users / Roles | Role-based permissions (`module.action`); only admins can grant the admin role; deactivating a user ends their sessions |
| **Audit** | Admin → Audit | Every create, update and delete on key records, and every login attempt |
| **Settings** | Admin → Settings | Property details, taxes and service charge, integration status (payments, channel, locks, WhatsApp) |

Theme: every screen has a light/dark/system toggle. Staff preferences are saved to their profile; website visitors' preferences are saved in their browser.
