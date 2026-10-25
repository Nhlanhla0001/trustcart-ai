# Architecture

TrustCart AI is a single Laravel application with an Inertia/React front end. The architecture is
organised around one rule: **authority is explicit**. Language interpretation, constraint
validation, human authorization and payment execution are separate layers with separate inputs
and separate failure modes.

## 1. Authority map

| Layer                                               | Can do                                                                | Cannot do                                                                                  |
| --------------------------------------------------- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Model (`AiClient`)                                  | Parse prose into a validated requirement; write an advisory narrative | Set a price, stock level, spec, check result, verdict, total or payment status             |
| Rules engine (`EvaluationEngine`)                   | Decide `qualified` / `unverified` / `rejected` from catalog rows      | Interpret free text, invent evidence, charge money                                         |
| Buyer                                               | Authorize a specific amount, product and merchant                     | Change the price, skip server checks                                                       |
| Payment server (`CheckoutService` + `PayPalClient`) | Create and capture a PayPal sandbox order after authorization         | Capture before authorization, capture twice, capture an amount different from the approval |

The boundaries are enforced in code, not by prompt instructions:

- `EvaluationEngine` never calls the model.
- `DecisionService` calls the model only for extraction (before evaluation) and narrative (after
  evaluation). The narrative is stored as text and cannot mutate checks.
- `CheckoutService` reads the amount from `purchase_approvals`, never from the request or the model.
- `PayPalClient` is the only class with credentials and network access to PayPal.

## 2. Decision lifecycle

```
POST /assistant
  │
  ├─ StoreIntentRequest (intent ≤ 1200 chars, category enum, budget numeric)
  │
  ├─ RequirementExtractor
  │    ├─ AiClient.extractRequirements()  → JSON
  │    │     └─ normalise(): category enum, feature vocabulary, numeric minimums,
  │    │        budget bounds, currency enum. Anything unknown is dropped.
  │    └─ deterministic parser on missing key / invalid JSON / HTTP failure
  │
  ├─ Product::active()->where(category)         ← deterministic retrieval
  │
  ├─ EvaluationEngine.evaluate(product, requirements)
  │    ├─ budget       (currency-aware)
  │    ├─ availability (stock > 0)
  │    ├─ currency     (must match)
  │    ├─ each required feature (tri-state evidence)
  │    └─ each numeric minimum
  │
  ├─ rank: qualified → unverified → rejected, then score, then price
  │
  ├─ AiClient.narrate(facts)  → advisory text (or deterministic template)
  │
  └─ persist: shopping_sessions, recommendations (checks + candidate snapshots), audit_events
```

The recommendation stores **snapshots** of every candidate and check. Later catalog edits do not
rewrite history.

## 3. Rules engine

### Verdict precedence

```
any check = fail        → rejected
else any check = unverified → unverified
else                    → qualified
```

A recommendation is only produced when at least one candidate is `qualified`. Otherwise the
decision is `refused` and the closest candidate is presented for inspection.

### Missing evidence

Feature evidence is deliberately tri-state (`Product::hasFeature`):

- feature present in `specs.features` → `pass`
- explicit `specs.feature_flags[feature] = false` → `fail`
- feature key absent → `unverified` (not a pass)

Numeric minimums behave the same way: a missing key is `unverified`. This is the core defence
against a catalog that looks compliant because it is incomplete.

### Scoring

The score is a presentation aid, never a gate. It blends merchant verification, stock depth,
budget headroom and spec margin, and is penalised for unverified checks. Ranking always groups by
verdict first, so a high-scoring rejected product can never outrank a qualified one.

## 4. AI design

### Two calls, no autonomy

| Call                   | Input                                                                         | Output                  | Validation                                                                    |
| ---------------------- | ----------------------------------------------------------------------------- | ----------------------- | ----------------------------------------------------------------------------- |
| Requirement extraction | The buyer's sentence, wrapped in `<request>` delimiters                       | JSON object             | Normalised against closed category/feature/spec vocabularies; numbers bounded |
| Narrative              | Facts: requirements, selected product, checks, rejected alternatives, closest | Plain text ≤ 1200 chars | Stored as advisory text; no parsing back into state                           |

### Prompt injection

- Product descriptions are **never** part of a prompt.
- The system prompt states that text inside data delimiters is untrusted.
- Injection-like text in the demo listing is detected in `CatalogController`, flagged in the UI,
  and excluded from the model entirely.
- Even a hostile narrative (tested) cannot change a verdict, amount or product because those are
  already persisted before narration.

### Provider resilience

`AiClient` iterates the configured provider chain (`TRUSTCART_AI_PROVIDER` then
`TRUSTCART_AI_FALLBACK`). It records which provider answered and why a fallback occurred. With no
key, `RequirementExtractor` uses its deterministic parser and the record stores
`extraction_source = fallback`.

## 5. Approval lifecycle

```
pending ──authorize──► authorized ──capture──► consumed
   │                        │
   ├──expire──────────────► expired
   ├──merchant price sync─► invalidated
   └──buyer cancels───────► cancelled
```

`purchase_approvals` stores `unit_price_cents`, `total_cents`, `currency`, `quantity`,
`expires_at`, `authorized_at`, `authorized_ip`, `authorized_user_agent` and an invalidation reason.

Validation is repeated at three points:

1. **Creation** — price copied from the catalog row, not the recommendation snapshot.
2. **Authorization** — price/currency/stock re-read; mismatch invalidates and refuses.
3. **Checkout start** — price/currency/stock re-read again; mismatch invalidates and refuses.

A new review supersedes any live approval for the session.

## 6. Checkout lifecycle and failure paths

```
created ──claim──► capturing ──verified COMPLETED──► completed
   │                    │
   │                    ├── PENDING  → pending   (not paid)
   │                    ├── DECLINED → failed
   │                    └── mismatch → failed
   └── buyer cancel ──► cancelled
```

Key properties:

- **Atomic claim.** `UPDATE orders SET status='capturing' WHERE id=? AND status IN ('created','pending')`
  is the gate. Only one request can capture; a duplicate sees `capturing` or `completed`.
- **Provider reconciliation.** Before capturing, the server fetches the order. If PayPal already
  reports `COMPLETED`, the existing capture is recorded without a second capture call.
- **Amount verification.** Both the order and the capture response must match the stored amount
  and currency exactly. A mismatch marks the order failed and refuses to record a payment.
- **Idempotent request IDs.** Create and capture use stable `PayPal-Request-Id` values derived
  from the approval UUID.
- **No optimistic success.** Any status other than `COMPLETED` is surfaced as pending/failed.

## 7. Persistence

| Table                | Purpose                                                                   |
| -------------------- | ------------------------------------------------------------------------- |
| `products`           | Catalog truth: price, stock, merchant, structured specs, data gaps        |
| `shopping_sessions`  | Raw intent, parsed requirements, extraction metadata                      |
| `recommendations`    | Verdict, checks, candidate snapshots, advisory narrative, engine metadata |
| `purchase_approvals` | Priced human authorization with expiry and invalidation                   |
| `orders`             | PayPal order, amount, capture, payer, failure reason                      |
| `payment_events`     | Provider-facing lifecycle events per order                                |
| `audit_events`       | Application-level decision and payment trail                              |

## 8. Extension points

- **Merchant feed:** replace `ProductSeeder` with a sync job. The rest of the system only needs
  `products` rows and `last_synced_at`.
- **Authentication / tenancy:** add a `user_id` to `shopping_sessions` and scope `AuditController`.
- **Webhooks:** add `/api/paypal/webhook` with `PAYPAL_WEBHOOK_ID`, verify signatures via
  `/v1/notifications/verify-webhook-signature`, and reconcile `orders.status`.
- **A second payment provider:** implement the same `begin/complete/cancel` contract and keep the
  approval layer unchanged.

## 9. Deliberate non-goals

No autonomous spending, no card storage, no live mode, no merchant payouts, no affiliate
assumptions, no claims that the human-approval step can be skipped for "convenience". Those are
product decisions, and they are the pitch.
