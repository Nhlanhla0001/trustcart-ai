# Three-minute demo script

Target length **2:55**. The goal is comprehension in the first 20 seconds, one memorable refusal,
one visible safeguard, and a real PayPal sandbox capture.

## Pre-flight (do this before recording)

1. `composer run demo:reset` — clean DB, eight products at their seeded prices.
2. `npm run build` and `php artisan serve` (or the deployed URL).
3. Confirm `.env` has sandbox credentials and `php artisan config:clear` has been run.
4. Sign in to a **PayPal sandbox personal** account in a second tab and keep it ready.
5. Pre-create two records so you do not wait on model latency on camera:
    - the reference request (qualified)
    - the impossible-budget request (refused)
6. Reset the catalog again after pre-creating them if you showed a price change, so the on-camera
   prices are the seeded ones.
7. Browser: 1440×900, 100% zoom, bookmarks bar hidden, notifications off, one window only.
8. Close DevTools. Do not show `.env`, terminal secrets, or the PayPal developer dashboard.

## Shot list

### 0:00–0:20 — The problem

**Screen:** home page, hero and the live specimen panel.

**Say:** “AI can already recommend a purchase. The hard part is everything after that: proving it
actually meets the requirement, and letting a human keep control of the money. This is TrustCart
AI — it knows when _not_ to buy.”

Point at the specimen: one qualified, three rejected, one unverified.

### 0:20–0:45 — The model is not the decision-maker

**Screen:** scroll to the orange "Three authorities" band, then the refusal proof.

**Say:** “The model interprets language and explains trade-offs. PHP rules decide whether a
product qualifies. And you authorize the amount. Three authorities, deliberately not one.”

### 0:45–1:20 — A cheaper product gets refused

**Screen:** open the prepared decision record. Show the verdict band, then the checks table, then
the alternatives.

**Say:** “This request was wireless headphones under $100, noise cancellation, twenty hours of
battery. The Auralis qualifies. The PulseWave Lite is $35 cheaper — and it is refused, because the
catalog records no noise cancellation. Every check is visible: requirement, evidence, result.”

Point at the `Nimbus Halo` row: “And where the catalog is missing a spec, it says unverified. It
never treats missing data as a pass.”

**Cut** to the prepared refusal record (or run the “Impossible budget” example).

**Say:** “When nothing qualifies, it refuses to recommend anything, and shows what would unlock a
match. That is the product.”

### 1:20–1:45 — The price cannot move silently

**Screen:** approval page → click **Change price +12%**.

**Say:** “Before payment, the total is a priced authorization with an expiry. Now a merchant sync
changes the price. The authorization is void — not quietly adjusted. The buyer has to approve the
new amount.”

Show the `VOID` state and the “Price changed” panel.

### 1:45–2:25 — Real PayPal sandbox checkout

**Screen:** decision record → **Review & authorize** → tick the box → **Continue to PayPal** →
PayPal login → **Pay** → return to TrustCart.

**Say:** “The server re-reads the price, creates a PayPal Orders v2 order, and sends me to PayPal.
The client secret never touches the browser. I approve in the sandbox…”

On the result page: “PayPal returned COMPLETED. The server verified that the captured amount
matches the approval before it wrote a paid status — and it recorded the capture ID.”

If the capture is not `COMPLETED`, **do not narrate it as paid**. Say what PayPal returned and
show it. A pending or failed result is still a valid demonstration of the guardrail.

### 2:25–2:45 — The audit trail

**Screen:** `/audit` → open the record.

**Say:** “Every decision leaves a record: the request, the parsed constraints, each rule outcome,
the advisory narrative, the approval, the PayPal order and capture, and every failure. It is a
file you can audit — not a chat transcript.”

### 2:45–3:00 — Close

**Screen:** home page or the audit record.

**Say:** “TrustCart AI is a controlled purchasing flow for agentic commerce: AI that can recommend,
rules that can refuse, and a human who still holds the money.”

End on the wordmark. No outro music needed.

## Honest fallbacks

- **No sandbox credentials available:** record the flow up to the authorization instrument, show
  the “PayPal sandbox not configured” panel, and say plainly that the integration is implemented
  and tested with fakes but has not been exercised against the live sandbox. Do **not** stage a
  fake success screen.
- **Model provider is down:** the deterministic parser takes over and the record says so. Mention
  it — it is a feature, not a failure.
- **Latency:** pre-create both decision records. Evaluation can take 3–10 seconds with a live
  model; do not record dead air.

## Things to avoid

- Do not call the products real, or imply an affiliate/merchant relationship.
- Do not show a real card number, real PayPal password, or `.env`.
- Do not claim the entry will win, or that the sandbox capture moves real money.
- Do not edit the audit record or re-record over an error; show the error and explain the guard.

## Asset checklist after recording

- [ ] 1080p or better, audible voice, no notifications
- [ ] Under 3:00
- [ ] Shows a refusal and the reason
- [ ] Shows the price-change invalidation
- [ ] Shows the PayPal sandbox approval and the verified status
- [ ] Shows the audit record
- [ ] No secrets on screen
