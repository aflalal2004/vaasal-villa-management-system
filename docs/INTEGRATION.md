# Hotel PMS ↔ Restaurant POS — system boundary

Vaasal Villa runs two systems in one Laravel application and one database. They share only **identity and
reference data** (users/roles/permissions, property, settings, villas and bookings as read-only lookups) and meet
at four defined integration points. Everything else is separate: routes, controllers, layouts, navigation and permissions.

| | Hotel PMS | Restaurant POS |
|---|---|---|
| URL prefix | `/admin` | `/pos` |
| Layout / navigation | `layouts/admin` · `App\Modules\Core\Navigation` ("Admin console · Hotel PMS") | `layouts/pos` · `App\Modules\POS\Navigation` ("Restaurant POS") |
| Modules | Booking, FrontDesk, Billing (folio, invoices, payments), Villa, Housekeeping, KeyCards, Staff, Channel, Operators, Reports, Website CMS | POS (dashboard, terminal, orders, reservations, KDS, cashier shifts, menu, tables, sales), Inventory |
| Permission prefix | `bookings.*`, `frontdesk.*`, `folio.*`, `payments.*`, `invoices.*`, `villas.*`, `housekeeping.*`, `keycards.*`, `staff.*`, … | `pos.*`, `inventory.*` |
| Entry | `/admin` (role home) | `/pos-access` → `/pos/dashboard` or terminal / KDS by role |

## Roles

| Role (username) | System | Can do |
|---|---|---|
| Administrator (`admin`) | Both | Everything; "Restaurant POS" / "Hotel PMS" switch in the top bar |
| Owner (`owner`) | PMS (read) | Dashboards, reports incl. POS sales reports; no POS operations |
| Hotel Manager (`manager`) | PMS | Bookings, front office, billing, villas, rates, housekeeping, key cards, staff, reports, website. **No POS** |
| Reception (`reception`) | PMS | Bookings, check-in/out, folio posting, payments, key cards. **No POS** |
| Accountant (`accounts`) | PMS | Billing, payments, financial reports (incl. POS sales reports) |
| Housekeeping supervisor / housekeeper | PMS | Housekeeping board / own tasks |
| Restaurant Manager (`posmanager`) | POS | Dashboard, terminal, orders, reservations, refunds, voids, discounts, KDS, menu, tables, cashier review, stock, restaurant sales. **No PMS** (no bookings, folio posting, invoices) |
| Cashier (`cashier`) | POS | Terminal, payments incl. charge to villa, own shift, reservations |
| Waiter (`waiter`) | POS | Terminal orders, reservations |
| Kitchen (`kitchen`) | POS | Kitchen display |
| Stores (`stores`) | POS | Inventory |

Enforced server-side by the `perm:` route middleware (403 otherwise) and mirrored in both sidebars. Covered by
`tests/Feature/SystemSeparationTest.php`.

## Integration points (the only cross-system flows)

1. **Room charge — POS → folio.** At payment the cashier chooses *Charge to villa*; `PosBillingService::chargeToVilla()`
   posts one line (net, tax, service split) to the guest's open hotel folio (`folio_lines.source_type = pos_order`).
   Only checked-in, non-blacklisted guests can be charged. No duplicate billing tables: the hotel invoice is built from the folio.
2. **In-house guest lookup — PMS → POS (read-only).** `GET /pos/api/in-house` lists checked-in stays (villa, guest, booking ref)
   for room-service orders, room charges and reservations linked to a stay.
3. **Checkout ↔ open restaurant checks.** `CheckOutService` refuses checkout while a check linked to the stay is open or billed.
   The checkout screen lists those checks and offers **Request restaurant settlement**
   (`POST /admin/front-desk/{booking}/restaurant-settlement`), which notifies POS cashiers (`pos.bill`) with a link to each check.
   Once paid or charged to the villa, checkout proceeds and the final invoice combines room and restaurant charges.
4. **Checkout → housekeeping.** Checkout revokes key cards, sets the villa Dirty, creates the departure task and alerts
   housekeeping (`HousekeepingService::onCheckout`). This stays inside the PMS; the POS is not involved.

Anything not listed here must not call across the boundary.
