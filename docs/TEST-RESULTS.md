# Test results

Recorded **25 October 2026** on Windows 11, PHP 8.5.0, Node 24, SQLite, Chrome (Playwright).

This file records what was actually run. It is not a claim that the product is finished.

## Commands and results

### `composer test`

```
pint ......................... passed
phpstan (level 7) ............ passed, 0 errors
phpunit ...................... 82 tests, 328 assertions, passed
```

Duration approximately 26 seconds on the build machine.

### `npm run types:check`

```
tsc --noEmit ................. passed
```

### `npm run check`

```
All 96 files are correctly formatted
No warnings or lint errors in 81 files
```

### `npm run build`

```
✓ built in ~17s
```

### `npm run test:e2e` (Playwright against `php artisan serve`)

A real browser completed this sequence without console errors or page errors:

1. `/assistant` — entered the reference request, submitted
2. decision page — recommendation rendered with checks and alternatives
3. **Review & authorize** — approval instrument rendered
4. **Change price +12%** — approval became `VOID`, price-change panel rendered
5. `/audit` — the decision appeared in the browser's audit list
6. audit record — checks, approval, events and IDs rendered
7. fresh browser context — impossible-budget request produced a refusal with the closest
   option's full check matrix

## Scenario coverage

| #   | Hackathon scenario          | Automated evidence                                                                                                                                                                             |
| --- | --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A   | Successful purchase         | `CheckoutFlowTest::test_completion_records_the_capture_and_consumes_the_approval`, `test_the_return_route_completes_the_payment_and_redirects_to_the_result`                                   |
| B   | Unsuitable cheaper product  | `EvaluationEngineTest::test_a_cheaper_product_fails_a_mandatory_feature`, `DecisionFlowTest::test_the_decision_page_exposes_checks_and_rejected_alternatives`                                  |
| C   | Budget violation            | `EvaluationEngineTest::test_a_product_over_budget_is_rejected_even_when_it_matches_specs`, `DecisionFlowTest::test_an_impossible_budget_produces_a_refusal_with_no_product`                    |
| D   | Missing specification       | `EvaluationEngineTest::test_missing_evidence_is_unverified_and_never_a_pass`, `test_a_missing_requirement_does_not_get_silently_dropped`                                                       |
| E   | Price change after approval | `ApprovalFlowTest::test_a_price_change_voids_the_approval_and_blocks_it`, `CheckoutFlowTest::test_the_price_is_rechecked_at_checkout_and_voids_the_approval`                                   |
| F   | Cancelled checkout          | `CheckoutFlowTest::test_cancel_closes_the_order_without_attempting_capture`                                                                                                                    |
| G   | Duplicate capture           | `CheckoutFlowTest::test_a_duplicate_return_never_captures_twice`, `test_begin_is_idempotent_for_the_same_approval`, `test_an_already_completed_provider_order_is_recorded_without_recapturing` |
| H   | Malicious catalog text      | `DecisionFlowTest::test_catalog_descriptions_never_reach_the_model_prompt`, `test_a_hostile_narrative_cannot_change_the_verdict_or_price`                                                      |

## Additional security and state coverage

| Property                            | Test                                                                                                                                                    |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Approval requires explicit checkbox | `ApprovalFlowTest::test_authorization_requires_explicit_acceptance_over_http`                                                                           |
| Approval expires                    | `ApprovalFlowTest::test_an_expired_approval_cannot_be_authorized`, `CheckoutFlowTest::test_an_expired_authorization_blocks_capture`                     |
| Amount tampering rejected           | `CheckoutFlowTest::test_a_mismatched_capture_amount_is_rejected_and_not_marked_paid`                                                                    |
| No fake success without credentials | `CheckoutFlowTest::test_missing_credentials_never_produce_a_fake_success`                                                                               |
| No open redirect via provider link  | `CheckoutFlowTest::test_a_non_paypal_approval_link_is_rejected`                                                                                         |
| Authorization is idempotent         | `ApprovalFlowTest::test_repeated_authorization_is_idempotent`                                                                                           |
| Refusal cannot reach payment        | `DecisionFlowTest::test_a_refusal_never_creates_an_approval_over_http`, `ApprovalFlowTest::test_a_refused_recommendation_cannot_enter_the_payment_flow` |
| Currency mismatch fails             | `EvaluationEngineTest::test_currency_mismatch_fails_the_budget_check`                                                                                   |
| Out of stock is never recommended   | `EvaluationEngineTest::test_out_of_stock_is_never_recommended`                                                                                          |
| Audit scoping                       | `AuditTest::test_a_browser_only_sees_its_own_records`                                                                                                   |
| Audit events written                | `AuditTest::test_audit_events_are_written_for_the_decision`                                                                                             |
| Fallback parser                     | `RequirementExtractorTest` (4 tests)                                                                                                                    |

## What is **not** tested

| Gap                                | Why it matters                                                                                                                              |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| A real PayPal sandbox capture      | No credentials available. All PayPal tests use `Http::fake`. The live handshake, buyer approval UX, and real capture IDs remain unverified. |
| Webhooks / refunds / disputes      | Not implemented.                                                                                                                            |
| Concurrent capture under real load | The atomic claim is covered logically, not with parallel processes against MySQL/Postgres.                                                  |
| Cross-browser                      | Only Chrome was exercised; Safari/Firefox are unverified.                                                                                   |
| Accessibility audit                | Semantic tables, labels and focus styles are implemented, but no screen-reader or axe audit was run.                                        |
| Performance under load             | No profiling, load test or query analysis.                                                                                                  |
| Deployment                         | The app has not been deployed publicly.                                                                                                     |
| Mobile device hardware             | Responsive layouts were inspected at 390px in Chromium, not on a physical device.                                                           |

## Reproduce

```bash
composer test
npm run types:check
npm run check
npm run build

php artisan serve          # terminal 1
npm run test:e2e           # terminal 2
```

The Playwright script expects the server at `http://127.0.0.1:8000` and uses the installed
Chrome channel. Override with `BASE_URL` if needed.
