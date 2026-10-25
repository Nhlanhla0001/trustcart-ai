# TrustCart AI — Product Truth

## What this is

TrustCart AI is a controlled purchasing assistant: a buyer states a shopping requirement in
plain language, the product is evaluated against hard constraints and catalog evidence, and the
buyer must explicitly authorize any PayPal payment. The agent recommends; it never spends.

Tagline: **AI that knows when not to buy.**

Built for the PayPal AI Hackathon 2026 (Devpost). Submission deadline 12 Nov 2026, 12:00 PM
Pacific (8:00 PM UTC). Judged on technological implementation, design, impact, idea and
presentation.

## Audience and scene

A buyer at a laptop, comparing electronics, reasonably informed but short on time. They have
been burned by a plausible-looking deal before. They want a recommendation they can inspect and
a payment they control — not a chat transcript. Secondary audience: the hackathon judge who
opens the demo cold and has three minutes to understand why this is not a chatbot with a PayPal
button.

## The mechanism (unique)

Three authorities, deliberately separated:

1. **AI interprets and explains.** It converts messy natural language into structured
   requirements, ranks candidates, and writes the tradeoff narrative.
2. **Deterministic rules decide.** Budget, required features, minimum specs, stock and currency
   are evaluated in PHP. The model cannot overrule a failed constraint or invent a spec.
3. **The human authorizes; the server executes.** Approval is explicit, priced, expiring, and
   invalidated by any material price change. PayPal capture runs server-side only.

Product truth that must never bend:

- Missing evidence is `unverified`, never a pass.
- A cheaper product that fails a mandatory requirement is refused, and the refusal is shown.
- Price changes after approval invalidate the approval; the amount is never silently changed.
- Payment status shown to the user is PayPal's status, never a simulated success.
- Product descriptions are untrusted data. Prompt-injection text inside a catalog description is
  displayed and ignored, never obeyed.

## Demo world (synthetic, labelled)

A fictional electronics storefront with eight products across headphones, laptops, monitors,
keyboards and accessories. Fictional brands, synthetic specs, demonstration prices in USD,
`DEMO` labelling wherever a judge could mistake it for a real merchant. One product satisfies a
reference headphone request, one is cheaper but lacks ANC, one exceeds budget, one has too little
battery life, one is missing the ANC spec entirely, and one carries an injected instruction.

## Surfaces

- Landing (`/`) — persuade: what it is, the three authorities, a live verdict sample, enter demo.
- Assistant (`/assistant`) — operate: requirement composer, example intents, structured parsing.
- Decision (`/sessions/{uuid}`) — operate: verdict, evidence checks, rejected alternatives,
  AI narrative, authorize CTA.
- Approval (`/sessions/{uuid}/approve`) — operate: priced review, expiry, explicit authorization.
- Checkout (`/checkout/{approval}`) — operate: PayPal sandbox handoff, then verified result.
- Audit (`/audit`) — operate: every session's requirements, checks, approvals and payment events.
- Catalog (`/catalog`) — operate: the eight synthetic products and their evidence gaps.

## Non-goals

No real money. No production merchant onboarding. No affiliate revenue assumptions. No storing
card data. No claim that PayPal capabilities exist beyond the documented Orders v2 sandbox API.

## Assumptions recorded from the brief

- Stack: Laravel 12 + Inertia React + Tailwind v4 + SQLite. The brief's "preferred stack" named
  Next.js; the operator chose Laravel for this build. Product behaviour is unchanged.
- AI provider is configuration, not architecture: Gemini, DeepSeek or OpenAI-compatible via
  environment variables, with a deterministic extractor fallback so the demo never dies on a
  missing key.
- Payments are PayPal Orders v2 in sandbox only. Without sandbox credentials the app reports
  "PayPal not configured" and refuses to fabricate a payment.
- Guest sessions are tracked in the browser's Laravel session; no account is required to demo.
