# API & Integration Reference

Base URL: `{APP_URL}/api/v1`, for example `http://localhost/vaasal_villa_hospitality_management_system28/public/api/v1`.

All responses are JSON. Validation errors return **422** with Laravel's `{ "message": …, "errors": {…} }` shape. Business-rule violations, such as a double booking, also return **422** with `{ "message": … }`.

## 1. Public website API (read-only, 60 requests/min per IP)

Other front ends, such as a mobile app or a partner widget, use these endpoints to read the **same live inventory and pricing** as the website.

| Method & path | Query | Returns |
|---|---|---|
| `GET /villa-types` | none | Active villa types with capacity, base rate, facilities and photos |
| `GET /villa-types/{slug}` | none | One villa type with full details |
| `GET /offers` | none | Live offers: title, summary, discount label, promo code, validity |
| `GET /availability` | `arrival`, `departure` (required, Y-m-d), `adults`, `children`, `promo` | For each type: `available` count, `fits_party`, `nights`, `total`, `avg_nightly`, `restrictions[]`, `book_url` |
| `GET /quote` | `type` (slug), `arrival`, `departure`, `adults`, `children`, `plan` (rate plan code), `promo` | Nightly breakdown, subtotal, discount, service charge, taxes, `grand_total`, deposit due |

```http
GET /api/v1/availability?arrival=2026-12-20&departure=2026-12-24&adults=2
```
```json
{ "arrival": "2026-12-20", "departure": "2026-12-24", "currency": "LKR",
  "data": [ { "slug": "garden-pool-villa", "name": "Garden Pool Villa", "available": 3, "fits_party": true,
              "nights": 4, "total": 191862.00, "avg_nightly": 47965.50, "restrictions": [],
              "book_url": "…/book?arrival=2026-12-20&departure=2026-12-24&adults=2&children=0&type=1" } ] }
```

Bookings are **created** only through the website booking flow (`/book`), the partner portal or the back office. The public API never takes payment or personal data.

## 2. Lock Bridge API (on-premise RFID agent)

Auth header: `Authorization: Bearer <LOCK_BRIDGE_TOKEN>`. Only a SHA-256 hash of the token is stored. Rotate it in **Admin → Key cards → Lock Bridge**. Rate limit: 240 requests/min.

| Method & path | Body | Purpose |
|---|---|---|
| `POST /lock-bridge/heartbeat` | `{ "version": "1.0", "vendor": "simulator" }` | Liveness. Returns `server_time` and `pending_jobs`. |
| `GET /lock-bridge/jobs?limit=5` | none | **Atomically claims** up to 10 pending jobs, so two bridges never take the same job. A job left unanswered for more than 2 minutes is offered again. |
| `POST /lock-bridge/jobs/{uuid}/result` | `{ "success": true, "message": "…", "card_uid": "04A1…", "encoder": "FO-ENC-1" }` | Completes the job. This is idempotent: a repeat call returns `duplicate: true`. |
| `POST /lock-bridge/events` | `{ "events": [ { "lock_ref": "VL-P1", "card_uid": "04A1…", "event": "open", "occurred_at": "…", "source": "online" } ] }` | Imports lock audit trails into the access history (up to 500 events per call) |

The job payload looks like this:

```json
{ "uuid": "…", "action": "encode_new",
  "payload": { "card_uid": "04A1B2C3", "lock_refs": ["VL-P1"], "valid_from": "2026-12-20T14:00:00+05:30",
               "valid_to": "2026-12-24T11:00:00+05:30", "access_level": "guest", "zone": null,
               "holder": "Priya Raman", "villa_code": "P1" } }
```

The `action` field is one of:

- `encode_new`: a new key, which invalidates earlier cards;
- `encode_duplicate`: an extra card for the same key;
- `extend`;
- `revoke`;
- `read`.

## 3. Attendance devices

`POST /attendance/punch` with the header `Authorization: Bearer <device token>`. Each device gets its own token in **Admin → Staff → Devices**. Rate limit: 120 requests/min.

```json
{ "identifier_type": "rfid", "identifier": "04FF12AA", "occurred_at": "2026-09-28T07:58:00+05:30" }
```

- `identifier_type` is one of `rfid`, `qr`, `biometric`, `pin`. PIN punches also need `employee_no`.
- A punch is accepted only if it falls between 2 days in the past and 5 minutes in the future.
- The first punch of the day is a clock-in. The next is a clock-out.
- Late minutes and overtime are computed against the roster.

Success response:

```json
{ "ok": true, "employee": "Kavin Raj", "action": "clock_in", "time": "07:58", "late_minutes": 0, "worked": "0h 00m" }
```

Error responses:

| Status | Meaning |
|---|---|
| 401 | Unknown or inactive device |
| 404 | Employee not recognised |
| 422 | Wrong PIN, or the punch time is outside the accepted window |

## 4. Inbound webhooks (CSRF-exempt, signature-verified, idempotent)

| URL | Provider | Verification | Effect |
|---|---|---|---|
| `POST /webhooks/payments/stripe` | Stripe Checkout | `Stripe-Signature` header checked with `STRIPE_WEBHOOK_SECRET` | `checkout.session.completed` with status paid completes the payment intent: the payment is posted to the folio, a receipt is issued and the booking is confirmed |
| `POST /webhooks/channel/channex` | Channex | Shared secret in the `X-Channex-Secret` header, compared in constant time with `CHANNEX_WEBHOOK_SECRET` | New, modified or cancelled OTA reservations are applied to the central ledger. A reservation that conflicts goes to **Conflicts** and managers are alerted. |
| `POST /webhooks/channel/simulator` | Local OTA simulator (only with `CHANNEL_DRIVER=null`) | `X-Simulator-Token` header must equal `LOCK_BRIDGE_TOKEN` | Same as above. Used to test OTA bookings and conflicts without a channel manager. |

Every webhook is first stored in `webhook_inbox`, which has a unique `(provider, event_id)`. A replayed event is acknowledged with 200 and ignored.

## 5. Outbound: channel manager (ARI push)

- Every change to inventory, rates or restrictions writes a row to the `channel_sync_logs` outbox inside the same database transaction. The change can come from a booking, cancellation, block, hold expiry or rate edit.
- The outbox is then pushed through the configured `ChannelManager` driver. Failed pushes are retried by `hms:channel-retry` every 5 minutes.
- You can watch sync status in **Admin → Channels**.

## 6. Payment gateway contract

`App\Modules\Billing\Gateways\PaymentGateway` has two implementations: `SandboxGateway` and `StripeGateway`. To add a provider, implement these methods and bind the driver in `AppServiceProvider`:

- `createCheckout(PaymentIntent, returnUrl, cancelUrl): string`, which returns the hosted page URL;
- `parseWebhook(Request): ?array`, which returns `{event_id, token, paid, reference}`;
- `name()`.

The server stores only the provider's reference, the amount and the status. It never sees a card number.
