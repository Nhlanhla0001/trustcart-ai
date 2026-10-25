# Hackathon compliance

Verified on **25 October 2026**. Always re-check the official pages before submitting; they are
authoritative and can change.

## Sources

- Official rules: <https://paypalaihackathon.devpost.com/rules>
- Resources: <https://paypalaihackathon.devpost.com/resources>
- FAQ: <https://paypalaihackathon.devpost.com/details/faqs>
- Dates: <https://paypalaihackathon.devpost.com/details/dates>
- PayPal announcement:
  <https://developer.paypal.com/community/blog/PayPal_AI_Hackathon/>

The Devpost rules page returned HTTP 403 to automated fetching during this build. The dates and
criteria below were cross-checked against Devpost's public listing/search snippets and PayPal's
own announcement, not scraped from the rules body. **A human must open the rules page and confirm
the details before submitting.**

## Dates

| Item                     | Value                                                                                      |
| ------------------------ | ------------------------------------------------------------------------------------------ |
| Submission period opens  | 1 October 2026, 9:00/9:15 am Pacific                                                       |
| Submission deadline      | **12 November 2026, 12:00 pm Pacific (8:00 pm UTC)**                                       |
| Judging                  | 1–15 December 2026 (public listings also show 13 Nov–15 Dec)                               |
| Winners announced        | on or around 21 December 2026                                                              |
| Availability requirement | Project must remain free and accessible to judges through the end of judging (15 Dec 2026) |

With a build date of 25 October 2026, there are **18 days** to the deadline.

## Hard requirements

| Requirement                                                          | TrustCart status                                                                                              |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Integrate at least one PayPal technology/API/SDK in the free sandbox | Orders v2 server integration implemented; **sandbox credentials and one live sandbox capture still required** |
| Meaningfully incorporate AI                                          | Requirement extraction + grounded narrative; deterministic fallback; documented                               |
| Build or update an application                                       | Built from scratch                                                                                            |
| Submit a working project URL and public repo                         | Repo is local; deployment and push pending                                                                    |
| Demo video, ≤ 3 minutes, showing the project                         | Script ready, not recorded                                                                                    |
| Text description and statement of AI tools used                      | Draft ready, not submitted                                                                                    |
| No purchase or payment necessary to enter                            | N/A                                                                                                           |

## Judging criteria mapping

Judges score five equally weighted criteria (technology, design, impact, idea, presentation).
Where the entry is strong and where it is not yet proven:

| Criterion                    | Evidence in this build                                                                                                                                     | Gap                                                 |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| Technological implementation | Deterministic engine separated from AI; three re-validation points; atomic capture claim; amount verification; 82 automated tests; fallback provider chain | No live PayPal transaction yet                      |
| Design                       | "Decision record" design system; evidence tables instead of chat; mobile layouts; honest empty/error/void states                                           | Screenshots reviewed; no formal accessibility audit |
| Impact                       | Refusal and price-change protection address real agentic-commerce risk; audit trail is a plausible procurement/compliance feature                          | No user research; no merchant validation            |
| Idea                         | "AI that knows when not to buy" is a clear, differentiated position against autonomous-buyer demos                                                         | Category is crowded; execution must carry it        |
| Presentation                 | Live-computed specimen, 3-minute shot list, honest limitations                                                                                             | Video and Devpost copy not produced                 |

## Eligibility notes (South Africa)

- The hackathon is global and open to individuals and teams. The rules exclude employees of
  PayPal/Devpost and their households, and residents of comprehensively sanctioned jurisdictions.
- South Africa is not a comprehensively sanctioned jurisdiction. **This is not a legal
  determination** — confirm against Section 3 of the Official Rules and PayPal's own account
  eligibility rules for South Africa.
- A PayPal developer account and sandbox app are required for the integration. Sandbox access is
  free. Verify that you can create a South African developer account before recording the demo.
- If eligibility is unclear, contact the hackathon organisers through Devpost **before** investing
  in the video.

## Sponsor tools

Sponsor tools (Postman, Zapier, Elastic, APIMatic, AG Grid, Bryntum, Channel3, Render, Astropods,
Kernel) are **optional**. TrustCart uses none of them for the core flow. Deploying to Render could
be useful for the public demo URL, but it is not required by the product.

## Submission checklist

- [x] Public GitHub repository with README, licence, `.env.example`, setup instructions
- [ ] No secrets committed (verify `git log` and `.env` exclusion)
- [ ] Deployed public demo URL that stays up through 15 December 2026
- [ ] PayPal sandbox credentials configured on the deployed instance
- [ ] One real sandbox capture executed and recorded
- [ ] 3-minute video uploaded (public/unlisted YouTube) following `docs/DEMO-SCRIPT.md`
- [ ] Devpost text submission completed from `docs/DEVPOST-SUBMISSION.md`
- [ ] AI tools statement included
- [ ] Repository and demo links verified in an incognito window
- [ ] Submission confirmation saved

## Known compliance risks

1. **PayPal integration is untested against the real sandbox.** This is the most consequential
   gap. Resolve it first.
2. **No public deployment.** Judges cannot run a local repository without a demo URL.
3. **Video not recorded.** A three-minute walkthrough is mandatory in practice for presentation
   scoring.
4. **Rules page not verified by a human.** Automated access was blocked; confirm dates, judging
   criteria and eligibility manually.
