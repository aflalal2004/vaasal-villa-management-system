# Requirements Traceability Matrix

Each requirement ID from the specification (https://claude.ai/artifact/PZ8Yneit7DiiRRG5zJUgDq) is mapped to the module and code that implement it and to the test that covers it.

**Status**

| Mark | Meaning |
|---|---|
| ✅ | Implemented, with UI, validation and database |
| ◐ | Partially implemented (the gap is noted) |
| ○ | Deferred (not built in this release) |

**Tests**

All tests are in `tests/Feature`. Run them with `php artisan test`; the last run gave **36 passed, 1165 assertions**.

| Code | Test file | Tests |
|---|---|---|
| BI | `BookingIntegrityTest` | 1 overlap rejected · 2 no partial rows · 3 back-to-back allowed · 4 cancel releases inventory · 5 expired hold releases villa · 6 out-of-order villa not bookable · 7 date change reprices · 8 late payment reinstates · 9 late payment on resold villa alerts |
| CS | `ChannelAndSecurityTest` | 1 OTA booking + duplicate webhook ignored · 2 overbooking → conflict queue + alert · 3 OTA cancellation · 4 unsigned webhook rejected · 5 lockout · 6 role home · 7 no user enumeration · 8 permissions enforced · 9 no admin escalation · 10 security headers / no-store |
| OP | `OperationsTest` | 1 HK ready only after inspection · 2 POS KOT / split / merge / pay / stock · 3 attendance late & OT · 4 new key invalidates old / lost card · 5 Lock Bridge jobs · 6 website hold → hosted payment → confirmed · 7 device punch API |
| SL | `StayLifecycleTest` | 1 full stay → one combined invoice · 2 checkout blocked by open POS check · 3 unpaid checkout rolls back · 4 check-in needs ready villa · 5 operator balance → city ledger |
| SS | `ScreensSmokeTest` | 1 every back-office/POS screen · 2 every record screen · 3 every role page-or-403 · 4 public website pages · 5 operator portal scoped |

Code paths below are relative to `app/Modules/<Module>/` unless shown in full.

## Website (WEB)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| WEB-01 | Home: hero, search, featured villas, offers, counters, testimonials, weather, map | ✅ | `Website/SiteController@home`, `views/website/home`, `WeatherService` | SS4 |
| WEB-02 | About | ✅ | `views/website/about` (CMS sections) | SS4 |
| WEB-03 | Villas grid + filters | ✅ | `SiteController@villas`, `@villa` | SS4 |
| WEB-04 | Services | ✅ | `views/website/services`, charge-item catalogue | SS4 |
| WEB-05 | Gallery: categories, lightbox, video, lazy load | ✅ | `views/website/gallery`, `site.js` lightbox | SS4 |
| WEB-06 | Offers with deep link | ✅ | `Offer` model, `?promo=` into `/book` | SS4 |
| WEB-07 | Contact / enquiry form | ◐ | `SiteController@enquiry`: rate-limited, with a honeypot. **No CAPTCHA service** is wired. | SS4 |
| WEB-08 | Booking flow: search → select → details → pay → confirm | ✅ | `Website/BookingFlowController`, `PublicPaymentController` | OP6 |
| WEB-09 | Manage booking (reference + email) | ✅ | `ManageBookingController`, voucher, balance payment | SS4 |
| WEB-10 | Real-time availability; re-validated at payment | ✅ | `AvailabilityService`; the hold reserves `inventory_nights` | OP6, BI1 |
| WEB-11 | Price transparency | ✅ | `Villa/PricingService::quote` (nightly, service, tax, discount) | OP6 |
| WEB-12 | Payment modes per rate plan | ◐ | Deposit % per rate plan (100 % = full prepayment). **Pay-at-property** is not offered online. | OP6 |
| WEB-13 | Email confirmation, receipt, voucher; WhatsApp | ◐ | `BookingConfirmationMail`, WhatsApp click-to-chat. The WhatsApp Cloud API message is only a configuration placeholder. | OP6 |
| WEB-14 | Multi-currency display | ✅ | `CurrencyService` (LKR base; manual > API > static rates), header selector + booking bar, `<x-price>`; Settings → Display currencies | HU |
| WEB-15 | Social sharing, OG/Twitter cards, embeds | ✅ | `layouts/site` meta, share links, YouTube facade | SS4 |
| WEB-16 | SEO: server-rendered pages, schema.org | ◐ | SSR, schema.org `Resort` JSON-LD, canonical, OG. **No sitemap.xml** or hreflang. | SS4 |
| WEB-17 | Language readiness | ◐ | Laravel localisation available, but Blade strings are **not yet externalised** | — |
| WEB-18 | Website animation & logo creation (new) | ✅ | `docs/BRAND.md`, `public/assets/brand/*`, `components/logo-mark`, `site.css` (logo intro, reveal, Ken Burns, loader), reduced-motion support | SS4 |

## Bookings & reservations (BK)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| BK-01 | Availability calendar (villas × dates) | ◐ | `admin/bookings/calendar`: colour-coded; block and unblock. **No drag-to-move**; use Change villa/dates on the booking instead. | SS1 |
| BK-02 | DB-level double-booking prevention | ✅ | `inventory_nights` UNIQUE(`villa_id`,`stay_date`), `AvailabilityService::reserve` → `InventoryConflictException` | BI1, BI2, BI3 |
| BK-03 | Holds with TTL, auto-release | ✅ | `BookingService::expireHolds`, `hms:expire-holds` (every minute); a late payment triggers `reinstate()` | BI5, BI8, BI9 |
| BK-04 | Rate plans, seasons, restrictions | ✅ | `Villa/PricingService`, `admin/rates` | OP6 |
| BK-05 | Cancellation policy and fee | ✅ | `BookingService::cancel`, `cancellationFee` | BI4 |
| BK-06 | Modifications with re-pricing and audit | ✅ | `changeDates`, `changeVilla`, `booking_events` | BI7 |
| BK-07 | Group bookings | ✅ | Multiple `booking_villas` per booking; operator group bookings | SL5 |
| BK-08 | Guest profiles, dedupe, VIP/blacklist, consent | ✅ | `Guest`, dedupe by email, blacklist check at booking | SS2 |
| BK-09 | Channel sync (ARI outbox) | ✅ | `Channel/ChannelSyncService`, `channel_sync_logs`, `hms:channel-retry` | CS1, BI4 |
| BK-10 | OTA overbooking → conflict queue + alerts | ✅ | `OtaReservationService`, `booking_conflicts`, `admin/conflicts` | CS2 |
| BK-11 | Payment links | ✅ | `OnlinePaymentService::createIntent` for a balance, sent by email/WhatsApp link | OP6 |
| BK-12 | Notifications | ✅ | `Notifications/NotificationService` (new booking, cancellation, payment, late payment, conflict) | CS2, BI9 |

## Tour operators (TO)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| TO-01 | Registration + approval | ✅ | `Operators/RegistrationController`, `OperatorService::register/approve` | SS5 |
| TO-02 | Multiple users per company | ✅ | `OperatorService::createLogin` | SS5 |
| TO-03 | Partner dashboard | ✅ | `PortalController@dashboard` | SS5 |
| TO-04 | Contracts, net rates, commission | ✅ | `operator_contracts`, contract pricing in `BookingService` | SL5 |
| TO-05 | Group booking creation | ✅ | `PortalController@storeBooking` | SS5 |
| TO-06 | Rooming list (CSV upload / entry) | ✅ | `OperatorService::importRoomingList` (CSV) | SS5 |
| TO-07 | Deposits & payments with proof | ✅ | `OperatorService::recordPayment/recordDeposit` (proof on the private disk) | SS2 |
| TO-08 | Commission | ✅ | `commissions` accrued per booking | SL5 |
| TO-09 | Outstanding & ageing | ◐ | Ageing report and statement. **Automated reminder emails are not scheduled.** | SS1 |
| TO-10 | Documents: confirmation, voucher, invoice, statement | ✅ | `views/print/*` | SS2, SS5 |

## Villas (VL)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| VL-01 | Villa CRUD with archive | ✅ | `Villa/VillaController` (soft delete) | SS1, SS2 |
| VL-02 | Villa types | ✅ | `VillaTypeController` | SS1 |
| VL-03 | Facilities, filterable | ✅ | `facilities` pivot, website filter | SS4 |
| VL-04 | Pricing | ✅ | `admin/rates`, `PricingService` | OP6 |
| VL-05 | Media | ◐ | Ordered photos, video URLs, validated uploads. **No automatic WebP/AVIF variants.** | SS1 |
| VL-06 | Separate status dimensions | ✅ | `status`, `hk_status`, maintenance blocks | OP1, BI6 |
| VL-07 | Occupancy tracking | ✅ | `ReportService` occupancy | SS1 |
| VL-08 | Lock mapping | ✅ | `villas.lock_ref` | OP5 |

## Key cards (KC)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| KC-01 | Card inventory | ✅ | `KeyCards/KeyCardController`, `key_cards` | OP4 |
| KC-02 | Card ↔ guest ↔ booking ↔ villa assignment with validity | ✅ | `KeyCardService`, `key_card_assignments` | OP4 |
| KC-03 | New key vs duplicate | ✅ | `encode_new` / `encode_duplicate` jobs | OP4 |
| KC-04 | Extend | ✅ | `extend` job on stay extension | OP4 |
| KC-05 | Lost card | ✅ | Marks the card lost, then issues a new key | OP4 |
| KC-06 | Revoke / block | ✅ | Revoke at checkout, block on demand | SL1 |
| KC-07 | Staff cards (master/zone/HK) | ✅ | `access_level`, `zone` on the assignment | OP4 |
| KC-08 | Access history import | ✅ | `POST /api/v1/lock-bridge/events` → `access_logs` | OP5 |
| KC-09 | Audit of card actions | ✅ | `lock_jobs` (who, when, encoder), `audit_logs` | OP4 |
| KC-10 | Hardware abstraction | ◐ | `lock-bridge/src/LockAdapter.php` with a working `SimulatorAdapter`. The **vendor adapter is a stub** until the lock brand and model are confirmed. | OP5 |

## Front desk (FD)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| FD-01 | Post charges to the folio | ✅ | `Billing/FolioService::post`, charge items | SL1 |
| FD-02 | Room move | ✅ | `BookingService::changeVilla` (inventory moved; key re-issue from Key cards) | SS2 |
| FD-03 | Extension / early departure | ✅ | `changeDates` with re-pricing | BI7 |
| FD-04 | No checkout with a balance unless moved to the city ledger | ✅ | `FrontDesk/CheckOutService` (transactional) | SL2, SL3, SL5 |
| FD-05 | Express checkout (pre-authorised card) | ○ | Not built. Checkout is always staff-assisted. | — |
| FD-06 | Arrivals / in-house / departures | ✅ | `admin/frontdesk` | SS1 |
| FD-07 | Night audit | ✅ | `NightAuditService`, `hms:night-audit` 02:00 | SS1 |

## Service charges & folio (SC)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| SC-01 | Charge catalogue by department | ✅ | `charge_items`, `config/vaasal.php` departments | SS1 |
| SC-02 | Post charges to a booking, villa or guest | ✅ | `RoomChargeService` (POS charge-to-villa), `FolioService` | SL1 |
| SC-03 | Routing rules | ○ | Not built. Folios can be split manually (guest/company folio). | — |
| SC-04 | Adjustments only by reversal; lines immutable | ✅ | `FolioService::reverse`, insert-only lines | SL1 |
| SC-05 | Pre-arrival packages | ○ | Not built | — |

## Housekeeping (HK)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| HK-01 | Status board | ✅ | `admin/housekeeping` | SS1 |
| HK-02 | Automatic task generation | ✅ | `HousekeepingService` (departure cleans on checkout, daily stay-over tasks) | OP1, SL1 |
| HK-03 | Assignment | ◐ | Supervisor assigns from the board. **No drag-and-drop or auto-distribution.** | OP1 |
| HK-04 | Start/end timestamps | ✅ | Task start/finish in *My tasks* | OP1 |
| HK-05 | Checklists | ✅ | `housekeeping/checklists` templates, ticked per task | OP1 |
| HK-06 | Linen | ✅ | `linen_items` (par per villa), `linen_movements` | SS1 |
| HK-07 | Lost & found | ✅ | `LostFoundController` (with photo) | SS1 |
| HK-08 | Maintenance reporting → ticket; blocking severity | ✅ | `MaintenanceService` (a blocking ticket takes the villa out of order) | BI6 |
| HK-09 | Inspection approve/reject | ✅ | `HousekeepingService::requestInspection/approve/reject` | OP1 |
| HK-10 | Villa is sellable automatically when Ready | ✅ | Ready state + no block → available | OP1, SL4 |
| HK-11 | Offline tolerance | ○ | Not built. Needs a network connection. | — |

## Staff (ST)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| ST-01 | Employee profile | ✅ | `Staff/EmployeeController` (photo, ID documents on the private disk) | SS2 |
| ST-02 | Departments and roles | ✅ | `departments`, `admin/roles` | SS1 |
| ST-03 | Login provisioning, reset, deactivation | ✅ | `admin/users`; deactivation ends sessions | CS8 |
| ST-04 | Shifts and rosters | ✅ | `admin/roster` | OP3 |
| ST-05 | Clock in/out (kiosk / app / PIN) | ◐ | Kiosk, PIN, RFID, QR and biometric reference through the device API and *My time card*. **No photo capture at punch.** | OP3, OP7 |
| ST-06 | Corrections with reason | ✅ | `AttendanceService::correct` (reason required, original values kept) | SS1 |
| ST-07 | Leave | ◐ | Types, balances (days per year), request/approve. **No monthly accrual.** | SS1 |
| ST-08 | Timesheet export | ✅ | `/attendance/export` (CSV) | SS1 |
| ST-09 | Device-ready punch sources | ✅ | `POST /api/v1/attendance/punch`, `attendance_devices` | OP7 |

## Restaurant POS (POS)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| POS-01 | Outlets | ✅ | `pos/outlets` | SS1 |
| POS-02 | Tables and status | ◐ | Tables with live status and covers. **No visual floor-plan editor.** | OP2 |
| POS-03 | Menu, variants, modifiers | ✅ | `pos/menu`, modifiers | OP2 |
| POS-04 | Orders, rounds, void with reason | ✅ | `PosOrderService` | OP2 |
| POS-05 | Transfer, split, merge | ✅ | `PosOrderService::split/merge/transfer` | OP2 |
| POS-06 | KOT per station | ✅ | KOT print + KDS | OP2 |
| POS-07 | KDS | ✅ | `pos/kds` | SS1 |
| POS-08 | Order status flow | ✅ | `PosOrderService` | OP2 |
| POS-09 | Billing: discounts, taxes, service charge | ✅ | `PosBillingService` | OP2 |
| POS-10 | Payments: cash, card ref, online, charge to villa | ✅ | `PosBillingService`, `RoomChargeService` | OP2, SL1 |
| POS-11 | Refunds / credit notes | ✅ | `PosBillingService::refund` (permission-gated; original invoice kept) | SS1 |
| POS-12 | Shift open/close, blind close | ✅ | `PosShiftService` | OP2 |
| POS-13 | Receipts, gap-free numbering, thermal | ◐ | Gap-free `DocumentNumberService`; receipt/KOT views for browser printing. **No direct ESC/POS driver.** | OP2 |
| POS-14 | Reports | ✅ | `pos/reports`, `ReportService` | SS1 |
| POS-15 | Inventory and recipes | ✅ | `InventoryService` (recipe depletion, POs, suppliers) | OP2 |
| POS-16 | Activity log | ✅ | `audit_logs`, `login_logs` | CS5 |
| POS-17 | Offline POS | ○ | Not built | — |

## Documents (DOC)

| ID | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| DOC-01 | Templates → PDF | ◐ | HTML/CSS print templates (`views/print/*`), saved as PDF from the browser. **No server-side PDF engine.** | SS2 |
| DOC-02 | Branding from settings | ✅ | `print/partials/letterhead` (logo mark, property details, tax ID) | SS2 |
| DOC-03 | Immutable PDF archive + hash | ○ | Not built. Invoices are immutable database records that are re-rendered on demand. | — |
| DOC-04 | Output: screen, print, email | ◐ | Screen, browser print and email. **No PDF attachment.** | SS2 |
| DOC-05 | Sequential numbering per type, property and year | ✅ | `DocumentNumberService` (`document_sequences`, row lock) | SL1 |

## Cross-cutting (spec §6–§11)

| Area | Status | Implementation | Test |
|---|---|---|---|
| RBAC (module.action permissions) | ✅ | `perm:` middleware, `Gate::before`, `@perm` | CS8, CS9, SS3 |
| Authentication security (lockout, logs, session timeout, password policy) | ✅ | `Auth/*`, `config/vaasal.php` security | CS5, CS6, CS7 |
| Security headers, CSRF, private uploads, encrypted IDs | ✅ | `SecurityHeaders` middleware, encrypted casts, `UploadService` | CS10 |
| Card data never stored | ✅ | Hosted checkout only (`PaymentGateway`) | OP6 |
| Transactional checkout | ✅ | `CheckOutService` inside `DB::transaction` | SL3 |
| Light/dark mode saved per user | ✅ | `users.theme`, `preferences.theme`, `localStorage` on the website | SS1 |
| Responsive layouts | ✅ | `app.css` / `site.css` breakpoints | manual (browser, 375 px) |
| Branded error pages | ✅ | `views/errors/*` | manual |
| Logo usage & animation guidelines (§9) | ✅ | `docs/BRAND.md` | SS4 |


## Release 29 additions

| Ref | Requirement | Status | Implementation | Test |
|---|---|---|---|---|
| R29-01 | Official logo (L1.png) everywhere, never stretched | ✅ | `public/assets/brand/vaasal-logo-*`, `vaasal-emblem-*`; `<x-brand-logo>`, `<x-logo-mark>` | HU |
| R29-02 | Jaffna, Sri Lanka · 0764413420 · +94764413420 · aflalal2004@gmail.com, editable in CMS | ✅ | migration `000001`, `contact()` helper, CMS content tab | HU |
| R29-03 | Social media management page | ✅ | `social_links`, `SocialLinkController` (admin + POS restaurant scope), footer/header/receipts | HU |
| R29-04 | Real SVG icons instead of emoji | ✅ | `<x-icon>` extended (brand + UI icons), glyphs replaced | — |
| R29-05 | Global animation system with reduced-motion support | ✅ | `site.css` / `app.css` motion blocks, `site.js` stagger & reveal | — |
| R29-06 | Photo/video API with caching and fallback | ✅ | `MediaLibraryService`, Admin → Website content → Stock media, lazy hero video | HU |
| R29-07 | Login: username/email, show/hide, remember me, local-only role cards, loading state | ✅ | `LoginController`, `DemoLogin`, `auth/login` | HU |
| R29-08 | Cashier: bank/digital tenders, deposits, adjustments, day-end wizard, variance, A4 + thermal report, manager review, audited reopen | ✅ | `PosShiftService`, `ShiftController`, `pos/day-end`, `pos/print/shift*` | HU |
| R29-09 | Today cash & bank dashboard; cash movement ledger | ✅ | `PosShiftService::today()`, `pos/today`, `pos/cash-movements` | HU |
| R29-10 | RFID reader API, access decisions, logs, simulator, device registry | ✅ | `RfidAccessService`, `RfidApiController`, `AccessControlController`, docs/RFID.md | HU |
| R29-11 | Checkout → dirty villa → HK task → immediate housekeeping alert; accept / pause / resume | ✅ | `HousekeepingService::onCheckout/accept/pause`, notification polling | HU |
| R29-12 | Unified room status (8 states); same-day website availability only for ready villas | ✅ | `Villa::boardStatus()`, `AvailabilityService` `readyNowForToday` | HU |
| R29-13 | Quick access, villa status board, cashier status on dashboards | ✅ | `admin/partials/quick-actions`, `villa-board`, `DashboardService` | HU |
| R29-14 | POS access entry point by role | ✅ | `PosAccessController`, `/pos-access` | HU |
| R29-15 | Error pages 403/404/419/422/429/500/503 | ✅ | `resources/views/errors` | — |

HU = `tests/Feature/HospitalityUpgradeTest.php`.
