# TrustCart AI

**AI that knows when not to buy.**

TrustCart AI is a controlled purchasing assistant. A buyer states a requirement in plain
language; the model turns it into structured constraints; deterministic rules test every
catalog product against recorded evidence; and the buyer must explicitly authorize a priced
total before PayPal is even opened.

Built for the **PayPal AI Hackathon 2026** (Devpost). Every product, merchant and price in the
demo is fictional, and payments run against PayPal **sandbox only**.

![TrustCart AI home page](docs/images/home.png)

---

## The problem this is aimed at

Agentic commerce is being built on the assumption that an assistant should be able to
"just buy it". That skips the part that matters: proving that the product actually meets the
requirement, showing what the evidence is, and giving the human a last, priced decision before
money moves.

Most demos in this category are a chat box with a checkout button. TrustCart separates three
authorities that should never collapse into one:

1. **The model interprets and explains.** It maps prose to a validated requirement and writes
   the tradeoff narrative. It cannot invent a price, stock level, spec, or verdict.
2. **Rules decide.** Budget, currency, availability, required features and numeric minimums are
   evaluated in PHP against catalog rows. Missing evidence is `unverified`, never a pass.
3. **The human authorizes; the server captures.** Approval is a separate, priced, expiring row.
   A price change voids it. Only the server talks to PayPal.

The product's most important behaviour is the one most assistants avoid: **refusal**. If no
product meets the mandatory requirements, TrustCart recommends nothing and shows exactly why.

---

## What the demo proves

| Scenario                                                    | What happens                                                                                           |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Reference request: wireless headphones, ≤ $100, ANC, ≥ 20 h | `Auralis NC-40` qualifies; the cheaper `PulseWave Lite` is rejected for lacking ANC.                   |
| Impossible budget (`< $40`)                                 | Refusal with the closest option and the exact failing check.                                           |
| Missing specification                                       | `Nimbus Halo` is `unverified`, not a pass.                                                             |
| Price change after approval                                 | A merchant sync changes the price, the approval becomes `VOID`, and a new priced approval is required. |
| Buyer cancels at PayPal                                     | Order becomes `cancelled`; no capture is attempted, no paid status is written.                         |
| Duplicate capture                                           | An atomic order claim plus a stable PayPal request ID prevents a second capture.                       |
| Instruction-like catalog text                               | Text in a product description is quarantined, displayed to the operator, and never sent to the model.  |
| Successful purchase                                         | Explicit approval → PayPal sandbox → server capture → verified `COMPLETED` status and capture ID.      |

### 1. A decision is inspectable, not persuasive

![Decision record](docs/images/decision.png)

Every rule outcome, its requirement, the catalog evidence and the result are stored with the
decision. The model narrative sits beside the matrix and is labelled as advisory.

### 2. Refusal is a first-class outcome

![Refusal with checks](docs/images/refusal.png)

### 3. Authorization is an instrument, not a chat message

![Authorization instrument](docs/images/authorization.png)

The amount, currency, quantity, merchant, expiry and IP are recorded. The approval expires
after 15 minutes. The checkbox is required server-side, not just in the UI.

### 4. A price change voids the authorization

![Price change protection](docs/images/price-change-protection.png)

TrustCart never silently charges a different amount.

### 5. The audit trail is part of the product

![Audit record](docs/images/audit-record.png)

The record contains the request, parsed requirement, every check, the advisory narrative, the
approval, PayPal order and capture IDs, failures and an append-only application event log.

### 6. The catalog is the evidence

![Catalog](docs/images/catalog.png)

Prose is never parsed into a constraint. Specifications are structured JSON. The listing with
an instruction-like payload is flagged and excluded from prompts.

---

## Stack

- **Laravel 13** (PHP 8.3+; developed on PHP 8.5) — server authority, rules engine, PayPal client
- **Inertia v3 + React 19 + TypeScript** — one page component per record
- **Tailwind CSS v4** — custom "decision record" design system, no component-library default look
- **SQLite** for the prototype (MySQL/Postgres work with a connection change)
- **Gemini and DeepSeek** via server-side HTTP; provider is configuration, not architecture
- **PHPUnit** (82 tests) and **Playwright** for browser-flow checks

No payment secrets reach the browser. No card data is stored.

---

## Run it locally

Requirements: PHP 8.3+, Composer 2, Node 20+, npm, and the PHP SQLite extension.

```bash
git clone https://github.com/Nhlanhla0001/trustcart-ai.git trustcart-ai
cd trustcart-ai

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed

npm run build
php artisan serve
```

Open <http://localhost:8000>.

For development with hot reload:

```bash
composer run dev
```

### Reset the demo

```bash
composer run demo:reset
```

Or use **Reset demo catalog** on `/catalog`. The reset restores prices, re-seeds the eight
products, and voids any live approvals so the price-change demo can be repeated.

---

## AI configuration

The model is used for two narrow jobs: parsing a requirement and writing an explanation. With
no key, a deterministic parser takes over and the decision record says so — the demo never dies
because a provider is unavailable.

```dotenv
TRUSTCART_AI_PROVIDER=gemini
TRUSTCART_AI_FALLBACK=deepseek

GEMINI_API_KEY=...
GEMINI_MODEL=gemini-3.5-flash

DEEPSEEK_API_KEY=...
DEEPSEEK_MODEL=deepseek-chat
```

Supported providers: `gemini`, `deepseek`. The fallback is tried automatically on transport
errors, 429s, 5xx responses and retired model names. The record stores which provider and model
actually answered, plus the failure code when the deterministic parser was used instead.

**What the model receives:** your sentence, and the parsed requirement when writing the
narrative. **What it never receives:** product descriptions, the catalog page, a price it can
change, stock, payment details, or the ability to call PayPal.

---

## PayPal sandbox setup

TrustCart refuses to fake a payment. Until credentials exist, the authorization screen explains
what is missing and disables checkout.

1. Create/sign in to a PayPal **developer** account: <https://developer.paypal.com>.
2. Go to **Apps & Credentials → Sandbox → Create App**.
3. Copy the **Client ID** and **Secret** into `.env`:

    ```dotenv
    PAYPAL_CLIENT_ID=...
    PAYPAL_CLIENT_SECRET=...
    PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com
    PAYPAL_CURRENCY=USD
    ```

4. Clear config: `php artisan config:clear`.
5. Run the flow: decision → **Review & authorize** → tick authorization → **Continue to PayPal**
   → approve with a **sandbox personal account** → return to TrustCart.

The integration uses **PayPal Orders v2** (`/v2/checkout/orders`, `.../capture`) with
server-side OAuth. See [docs/PAYPAL-INTEGRATION.md](docs/PAYPAL-INTEGRATION.md).

> Live credentials are refused unless `paypal.allow_live` is explicitly enabled. This prototype
> is sandbox-only by design.

**Current status:** no sandbox credentials were available in the build environment, so no real
sandbox capture has been executed. The PayPal code paths are covered by feature tests with
`Http::fake` (create, approve, capture, duplicate return, amount mismatch, cancel, expiry,
provider failure). This limitation is stated in the submission copy as well.

---

## Tests

```bash
php artisan test          # 82 tests, 328 assertions
npm run types:check       # TypeScript, strict
npm run build             # Vite production build
npm run test:e2e          # Playwright browser flow (requires `php artisan serve`)
```

Actual result at the time of writing:

```
Tests:    82 passed (328 assertions)
Duration: ~5–7s
```

Coverage includes: every rule outcome, missing evidence, currency and stock failures, refusal
flows, approval expiry, price-change invalidation, authorization requirements, checkout
idempotency, duplicate capture, amount tampering, cancel handling, prompt-injection isolation,
hostile narrative output, audit scoping and the full HTTP return route.

See [docs/TEST-RESULTS.md](docs/TEST-RESULTS.md) for the mapping from the eight hackathon
scenarios to named tests, and the browser checks that were run.

---

## Architecture

```
Browser (React/Inertia)
   │  intent, approval, audit
   ▼
Laravel controllers ──► DecisionService ──► RequirementExtractor ──► AiClient
   │                        │                                        (Gemini/DeepSeek)
   │                        ├──► EvaluationEngine (rules, authoritative)
   │                        ├──► Recommendation + candidate snapshot
   │                        └──► AuditLogger
   │
   ├──► ApprovalService (price re-read, expiry, invalidation)
   └──► CheckoutService ──► PayPalClient (Orders v2, sandbox)
                              │
                              └──► Order + PaymentEvent + AuditEvent
```

More detail, including the payment state machine and failure paths:
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

### Data model

`products` · `shopping_sessions` · `recommendations` · `purchase_approvals` · `orders` ·
`payment_events` · `audit_events`.

Candidates and checks are snapshotted into the recommendation so a later catalog change cannot
retroactively rewrite the decision.

---

## Security posture

Implemented:

- Server-side price, currency, stock and amount verification at approval **and** checkout.
- Authorization is explicit, expiring, IP/user-agent stamped, and enforced server-side.
- Amounts and products come from stored rows, never from the client or the model.
- PayPal client secret stays server-side; only the approve URL is returned to the browser.
- Stable `PayPal-Request-Id` per action, plus an atomic order claim, to prevent duplicate capture.
- Provider responses are verified for exact amount and currency before being recorded as paid.
- No fabricated success: `PENDING`, `DECLINED`, `FAILED`, `cancelled` are represented as such.
- Strict validation of AI output; unknown feature keys and spec keys are discarded.
- Prompt isolation: catalog prose is excluded from prompts; injected text is flagged and displayed.
- Rate limits on intent parsing, approval, authorization and demo controls.
- Security headers (`nosniff`, `SAMEORIGIN`, referrer policy, permissions policy, COOP).
- Structured logs omit secrets; audit payloads redact sensitive keys.
- CSRF protection and encrypted cookies from the Laravel web middleware.

Read the threat model and residual risks in [docs/SECURITY.md](docs/SECURITY.md).

---

## Known limitations (truthful list)

- **No real PayPal sandbox capture has been run yet** — credentials were unavailable. Tests cover
  the HTTP contract with fakes, not PayPal's live sandbox.
- **No webhook receiver.** The synchronous return + capture path is implemented. Webhooks would be
  needed for disputes, refunds and out-of-band status changes; signature verification is not built.
- **No authentication.** Demo records are scoped to the browser's Laravel session. There is no
  account model for buyers, no multi-tenant merchant onboarding, no role separation.
- **SQLite and a single demo catalog.** No merchant feed, no affiliate data, no real inventory.
- **The AI narrative can still be stylistically imperfect.** It is labelled advisory and cannot
  alter a verdict; it is not treated as evidence.
- **No tamper-evident audit storage.** Events are append-only by application policy, not by hash
  chaining or external retention.
- **Not deployed publicly yet.** The repo runs locally; a public demo URL and the 3-minute video
  are still required for the Devpost submission.

---

## Hackathon status

| Requirement                                          | Status                                                                                                      |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Meaningful PayPal integration                        | Implemented server-side (Orders v2, sandbox); **not yet exercised with real sandbox credentials**           |
| Meaningful AI integration                            | Implemented (requirement parsing + grounded narrative, with fallback)                                       |
| Public repository                                    | **Public:** [github.com/Nhlanhla0001/trustcart-ai](https://github.com/Nhlanhla0001/trustcart-ai) · CI green |
| Demo video (≤ 3 min)                                 | Script and shot list ready in [docs/DEMO-SCRIPT.md](docs/DEMO-SCRIPT.md); **not recorded**                  |
| Devpost text submission                              | Draft in [docs/DEVPOST-SUBMISSION.md](docs/DEVPOST-SUBMISSION.md); **not submitted**                        |
| Keep project available through judging (15 Dec 2026) | Depends on deployment                                                                                       |

Rules verification and eligibility notes: [docs/HACKATHON-COMPLIANCE.md](docs/HACKATHON-COMPLIANCE.md).

---

## Repository layout

```
app/
  Http/Controllers/        Home, Assistant, Session, Approval, Checkout, Audit, Catalog, Demo
  Models/                  Product, ShoppingSession, Recommendation, PurchaseApproval, Order…
  Services/
    Ai/AiClient.php        Gemini/DeepSeek + safe fallback
    RequirementExtractor.php
    EvaluationEngine.php   Deterministic rules, missing evidence handling
    DecisionService.php    Orchestration + narrative + audit
    ApprovalService.php    Price re-read, expiry, invalidation
    CheckoutService.php    Create/capture, amount verification, duplicate protection
    PayPal/PayPalClient.php
    AuditLogger.php
  Support/Requirements.php Money.php
database/
  migrations/              All seven domain tables
  seeders/ProductSeeder.php  Eight synthetic listings with deliberate edge cases
resources/js/
  pages/                   welcome, assistant, sessions/show, approvals/show, checkout/result…
  components/trust/        Record header, stamps, checks table, product line art
  layouts/trustcart-layout.tsx
tests/
  Unit/                    Rules engine, fallback parser
  Feature/                 Decision, approval, checkout, audit, injection
docs/                      Architecture, security, PayPal, demo script, submission draft
```

---

## Data and licence

All products, brands, merchants, prices and specifications are **fictional** and exist only to
demonstrate the decision engine. No affiliate relationship, real inventory or merchant
endorsement is implied.

MIT licensed — see [LICENSE](LICENSE).

Design system: [DESIGN.md](DESIGN.md). Deployment notes: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
