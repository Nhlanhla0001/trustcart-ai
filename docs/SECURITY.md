# Security

TrustCart handles a payment decision, so the interesting threat is not "can someone read the
catalog" — it is "can any component be persuaded to authorize or capture an amount the buyer did
not approve, or to record a payment that did not happen".

## Assets

| Asset                          | Why it matters                                              |
| ------------------------------ | ----------------------------------------------------------- |
| Buyer's money                  | Sandbox in the demo, but the authority model is the product |
| PayPal client secret           | Full merchant API access                                    |
| Approved amount and currency   | The integrity of the authorization                          |
| Payment status and identifiers | Audit credibility                                           |
| Catalog evidence               | The recommendation is only as honest as its inputs          |
| Decision history               | The audit trail must not be rewritable by the model         |

## Threat model and controls

| Threat                                         | Control                                                                                           |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Buyer's browser alters the amount              | Total is read from `purchase_approvals`; client payload has no amount field                       |
| Model invents a price, spec or verdict         | Model output is validated against closed vocabularies; rules engine is the only verdict authority |
| Model is persuaded to "approve"                | No tool or code path lets the model authorize or call PayPal                                      |
| Malicious catalog prose tries prompt injection | Descriptions never enter prompts; instruction-like text is flagged in the UI                      |
| Coupon/price manipulation                      | Price is re-read from the catalog at approval creation, authorization and checkout start          |
| Charging a changed price silently              | Any change invalidates the approval; checkout refuses until a new approval exists                 |
| Duplicate clicks / duplicate return            | Atomic order claim + stable `PayPal-Request-Id`; completed orders are terminal                    |
| Replayed approval URL                          | Approval is expiring, single-use (`consumed` after capture), and status-checked                   |
| Provider returns a different amount            | Amount and currency are verified on order and capture; mismatch = failed, never paid              |
| Fake success when PayPal is down               | No code path writes `completed` without a provider response; failures are surfaced                |
| Open redirect via provider payload             | Approve URL host must end in `paypal.com`, otherwise checkout refuses                             |
| Secret leakage to the client                   | Only booleans (`paypal_configured`) and the approve URL are exposed; secret stays server-side     |
| Unauthorized audit access                      | Audit records are scoped to the browser's Laravel session; unknown UUIDs return 404               |
| XSS via intent or catalog text                 | React escapes output; no `dangerouslySetInnerHTML`                                                |
| SQL injection                                  | Eloquent/query builder with bound parameters                                                      |
| Mass assignment                                | Explicit `$fillable` on every model                                                               |
| CSRF                                           | Laravel web middleware + Inertia CSRF token                                                       |
| Credential leakage in logs                     | PayPal client logs status/issue/debug ID, never tokens; audit redaction for secret-shaped keys    |
| Abuse / cost exhaustion                        | Throttles on intent parsing, approvals, authorization, price simulation and reset                 |
| Clickjacking / MIME sniffing                   | `X-Frame-Options`, `X-Content-Type-Options`, referrer and permissions policy, COOP                |
| Prompt-injected narrative shown as fact        | Narrative is visually labelled advisory, stored separately from checks                            |
| Stale data presented as current                | `last_synced_at` is shown; missing evidence is `unverified`                                       |

## Authorization model

1. The AI produces a recommendation; it has no payment capability.
2. The buyer opens a priced approval instrument.
3. The server requires a validated `authorize` flag, a live approval, an unexpired window, an
   unchanged price and sufficient stock.
4. The server creates a PayPal order for the stored total and returns an approve URL.
5. Only the server can capture, and only after fetching and verifying the provider order.
6. The approval is consumed on success; the order becomes terminal.

There is no "agent auto-pay" toggle in this codebase, intentionally.

## Sandbox-only guard

`PayPalClient::assertReady()` refuses to run when the configured base URL is not a sandbox host,
unless `paypal.allow_live` is explicitly enabled in configuration. This is a guard against
accidental live charges during a demo, not a substitute for production controls.

## Data minimisation

Stored: order IDs, capture IDs, payer ID, amounts, statuses, timestamps, approval IP/user agent.
Not stored: card numbers, CVV, PayPal passwords, full provider payloads beyond a summary
(`id`, `status`, `payer_id`, `links`, `purchase_units`, `debug_id`).

## Residual risks / gaps

- **No authentication or tenancy.** A shared browser session sees its own records; there is no
  user identity, role model, or merchant isolation. Do not expose this build to untrusted users.
- **No webhook signature verification** because webhooks are not implemented. If added, verify
  with `PAYMENT.CAPTURE.*` signatures using `PAYPAL_WEBHOOK_ID`.
- **No CSP.** A strict Content-Security-Policy would require nonce handling for Inertia/Vite and
  is not configured in this prototype.
- **Demo reset endpoint.** `/demo/reset-catalog` is rate-limited and gated by
  `TRUSTCART_DEMO_MODE`; disable demo mode before any real deployment.
- **SQLite.** Not a concurrency or durability choice for production; the atomic claim works but a
  server database is expected in production.
- **Append-only is policy, not proof.** `audit_events` has no hash chain or external retention.
- **No security review or penetration test** has been performed. Treat this as hackathon-grade.
- **No real sandbox payment has been executed**, so provider-side behaviour remains unverified in
  this environment.

## Reporting

This is a hackathon prototype with no production users. If you find a security issue in the code,
open an issue in the repository rather than testing against a deployed instance.
