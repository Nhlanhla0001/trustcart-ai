# Devpost submission draft

Status: **draft — not submitted.** Replace every `[PLACEHOLDER]` with the real value and delete
this line before submitting.

## Project title

TrustCart AI

## Tagline

AI that knows when not to buy.

## Short description

A controlled shopping assistant that tests products against hard requirements, explains every
refusal, and requires explicit human authorization before a PayPal sandbox checkout.

## Inspiration

Agentic commerce demos tend to collapse recommendation, permission and payment into one chat.
That is convenient until the assistant buys the wrong thing, or pays a price the buyer never saw.
We wanted to build the opposite: an assistant that can be useful precisely because it is allowed
to say no.

## What it does

A buyer describes a purchase in plain language. TrustCart extracts a structured requirement —
category, budget, must-have features, minimum specifications — and validates it against a closed
vocabulary. It then tests every catalog product with deterministic rules:

- budget and currency
- availability
- required features
- numeric minimums

Products that fail are rejected with the exact evidence. Products with missing evidence are
marked `unverified`, never treated as a pass. If nothing qualifies, TrustCart refuses to recommend
a purchase and shows what would unlock a match.

When something does qualify, the buyer opens a priced authorization with a 15-minute expiry. If
the catalog price changes, the authorization is voided. Only after explicit authorization does the
server create a PayPal sandbox order, and only after PayPal confirms the capture does the app
record a payment.

## How we built it

- **Laravel 13 / PHP 8.5** for the rules engine, approvals, PayPal client and audit trail
- **Inertia v3 + React 19 + TypeScript** for a record-based interface
- **Tailwind CSS v4** with a custom design system
- **SQLite** for the demo database
- **Gemini** for requirement parsing and narrative, with **DeepSeek** as an automatic fallback and
  a deterministic parser when no provider is available
- **PayPal Orders v2** REST API, sandbox only, server-side OAuth and capture

The UI is deliberately not a chat. Each decision is a document: requirement, verdict, check
matrix, advisory narrative, alternatives, approval and audit events.

## How AI is used

The model has two narrow jobs:

1. **Interpret** the buyer's sentence into a validated requirement object.
2. **Explain** a verdict that the rules engine has already produced.

It never receives product descriptions, cannot set a price or stock level, and cannot authorize
or capture a payment. Missing or invalid model output degrades to a deterministic parser, and the
decision record states which path was used.

## How PayPal is integrated

Server-side **PayPal Orders v2**:

- OAuth client credentials with a cached token
- Create order from the stored approval amount
- Redirect the buyer to PayPal's hosted approval
- On return: fetch the order, verify the amount, capture once with a stable request ID
- Verify the capture amount and currency, then persist `COMPLETED`, capture ID and payer ID
- Handle cancel, pending, declined, failed, duplicate return and amount mismatch honestly

No client secret reaches the browser. No payment is recorded without a provider response. Live
credentials are refused by the prototype.

**Current verification status:** the HTTP contract is covered by automated tests with fakes, but
as of `[DATE]` no sandbox credentials were available, so a real sandbox capture has not been
executed. `[UPDATE THIS BEFORE SUBMITTING]`

## Key innovations

- **Refusal as a first-class outcome**, with the failing evidence attached.
- **Tri-state evidence**: pass, fail, unverified. Missing data is never a pass.
- **A price-change guard** that voids a human authorization instead of charging a new total.
- **Three-authority architecture** that keeps the model away from money entirely.
- **An auditable decision record** rather than a chat transcript.
- **Prompt-injection quarantine**: catalog prose is excluded from prompts and flagged in the UI.

## Challenges

- Keeping the model useful without letting it become an authority.
- Designing a payment flow that is honest about pending, failed and cancelled states.
- Making duplicate capture impossible under repeated returns and retries.
- Building an interface where "why not?" is as clear as "why this one?".

## Accomplishments

- A complete rule-to-authorization-to-sandbox flow with explicit failure states.
- `[N]` automated tests covering the eight demo scenarios, including amount tampering and
  duplicate capture.
- A design that reads as a decision record, not a chatbot.
- Graceful degradation when an AI provider fails.

## What we learned

- The valuable AI contribution is interpretation and explanation, not authority.
- Payment states deserve to be represented verbatim; optimism is a bug.
- Missing evidence is the most dangerous input in a shopping agent, because it looks like
  compliance.

## What's next

- Execute and record a real PayPal sandbox capture.
- Add webhook signature verification for capture/refund/dispute reconciliation.
- Add buyer accounts, merchant tenancy and role separation.
- Replace the seeded catalog with a merchant feed and show freshness at the field level.
- Move from a single approval to procurement-style multi-item approvals with per-line evidence.

## Built with

`laravel` · `php` · `react` · `typescript` · `inertiajs` · `tailwindcss` · `sqlite` · `gemini` ·
`deepseek` · `paypal-orders-v2` · `phpunit` · `playwright`

## Links

- Repository: `[PUBLIC GITHUB URL]`
- Demo: `[PUBLIC URL — must stay available through 15 Dec 2026]`
- Video: `[YOUTUBE URL, ≤ 3 minutes]`

## AI tools statement

We used an AI coding assistant to help implement and review the application. The product itself
calls Gemini (primary) and DeepSeek (fallback) at runtime for requirement parsing and narrative
generation. No AI tool was used to fabricate test results or payment outcomes; all stated test
results come from the commands in `docs/TEST-RESULTS.md`.

## Data notice

All products, brands, merchants, prices and specifications are fictional. Payments run against
the PayPal sandbox only. No real money moves and no affiliate relationship is implied.
