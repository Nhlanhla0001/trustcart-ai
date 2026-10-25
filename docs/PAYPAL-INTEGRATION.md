# PayPal integration

## Scope

- PayPal **Orders v2** REST API
- **Sandbox only** (`https://api-m.sandbox.paypal.com`)
- Server-side OAuth 2.0 client credentials
- Buyer approval via PayPal's hosted checkout (`/checkoutnow?token=...`)
- Server-side capture with explicit amount verification
- No SDK, no client secret in the browser, no card data stored

## Configuration

```dotenv
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com
PAYPAL_CURRENCY=USD
PAYPAL_TIMEOUT=25
```

Create credentials at **developer.paypal.com → Apps & Credentials → Sandbox → Create App**.
Use a **sandbox personal** account to approve the payment. After editing `.env`, run
`php artisan config:clear`.

## Flow

```
1. Buyer authorizes a priced total
   POST /approvals/{uuid}/authorize
   └─ ApprovalService: re-read price/stock, mark authorized

2. Server creates a PayPal order
   POST /v2/checkout/orders
   ├─ intent=CAPTURE
   ├─ purchase_units[0].amount = approval total (server-side)
   ├─ custom_id = approval uuid
   ├─ application_context.return_url = /checkout/{order}/return
   ├─ application_context.cancel_url = /checkout/{order}/cancel
   └─ PayPal-Request-Id = tc-<hash(create|approval)>

3. Browser is redirected to PayPal's `approve` link

4. Buyer approves or cancels in PayPal

5. On return: GET /checkout/{order}/return
   ├─ atomic claim: created|pending → capturing
   ├─ GET /v2/checkout/orders/{id}
   ├─ verify status and amount
   ├─ if already COMPLETED: record existing capture, do not capture again
   ├─ POST /v2/checkout/orders/{id}/capture
   │    └─ PayPal-Request-Id = tc-<hash(capture|approval)>
   ├─ verify capture status + amount + currency
   ├─ persist capture_id, payer_id, payload summary
   ├─ mark order completed, approval consumed
   └─ redirect to /checkout/{order}/result
```

## Verification rules

A payment is only recorded as `completed` when **all** of these hold:

1. PayPal returns `status = COMPLETED` for the capture.
2. The captured amount equals `orders.amount_cents` exactly.
3. The captured currency equals `orders.currency`.
4. The order belongs to an approval that is still authorized and unexpired.
5. The order was not already completed or claimed by another request.

Anything else is recorded as pending, failed or cancelled with a safe reason. There is no code path
that writes `completed` without a provider response.

## Idempotency and duplicate capture

- PayPal request IDs are deterministic per approval and action, so retries are safe.
- The order row is claimed with a conditional SQL update before any network call. A second return
  sees `capturing` or `completed` and stops.
- If a previous capture succeeded but the response was lost, the next return fetches the order from
  PayPal, sees `COMPLETED`, and records the existing capture **without** a second capture call.

## Error handling

| Condition                     | Behaviour                                                                  |
| ----------------------------- | -------------------------------------------------------------------------- |
| Missing credentials           | `PayPalNotConfigured`; UI disables checkout and explains setup             |
| Non-sandbox base URL          | Refused unless live is explicitly enabled                                  |
| OAuth failure                 | Ordered abort, safe message, debug ID logged; nothing recorded as paid     |
| 5xx / timeout                 | Order marked failed; user message says no payment was taken; retry allowed |
| Buyer cancels                 | `checkout.cancel` → order `cancelled`, approval `cancelled`, no capture    |
| Capture `PENDING`             | Order `pending`; UI says it is not paid                                    |
| Capture `DECLINED` / `FAILED` | Order `failed`; no paid status                                             |
| Amount mismatch               | Order `failed`; `CheckoutException`; nothing recorded as paid              |
| Authorization expired         | Order `failed`; no capture attempted                                       |
| Duplicate return              | Existing completed status returned; no second capture                      |

## What has and has not been tested

**Tested with `Http::fake` (see `tests/Feature/CheckoutFlowTest.php`):**

- order creation payload and amount
- approve URL extraction
- successful capture and persisted IDs
- duplicate completion (single capture call)
- provider-already-completed reconciliation
- cancel path
- amount mismatch rejection
- missing credentials
- expired authorization
- HTTP return route and redirect
- price re-check at checkout

**Not tested against PayPal's live sandbox:** no sandbox credentials were available in the build
environment. Before recording the demo, run one real sandbox payment and confirm:

- the buyer approval screen appears
- the return URL captures successfully
- the result page shows PayPal's capture ID and `COMPLETED`
- cancelling produces no paid record

## Not implemented

- Webhooks (`PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, refunds, disputes)
- Refunds and partial captures
- Stored payment methods / vaulting
- Multi-currency conversion (currency must match the approval)
- Seller onboarding, marketplace splits, payouts
- Live mode (deliberately)
