# Design

## The world

TrustCart is designed as a **decision record**, not a chat. The reference object is a
quality-inspection dossier or a signed authorization instrument: cool paper, black ink, hairline
rules, tabular evidence, stamps for verdicts, and one reserved colour for the moment a human
takes responsibility.

The scene: a buyer at a desk in daylight, comparing electronics with a second tab open. They are
not looking for entertainment, and they have been burned by a plausible deal before. The surface
should feel like something a careful institution would hand them — not a storefront, not a
dashboard, and definitely not an assistant's chat bubble.

## Why this world

The product's differentiator is that it can refuse and that it keeps a record. A reassuring
fintech dashboard would hide exactly those behaviours inside cards and metrics. A document
makes them the composition:

- evidence is a table, not a value proposition
- a refusal is a stamped verdict, not an error toast
- authorization is an instrument with a total, an expiry and a signature-like checkbox
- history is a file, not a transcript

## Palette

Strategy: **restrained**, with one committed signal plane. Ink and paper carry everything; orange
is reserved for the human authorization step. Semantic colours appear only as small stamps and
status bands.

| Token         | Value                    | Use                                                |
| ------------- | ------------------------ | -------------------------------------------------- |
| `paper`       | `oklch(0.968 0.004 250)` | Page ground, record surfaces                       |
| `paper-deep`  | `oklch(0.945 0.005 250)` | Secondary bands, quiet panels                      |
| `ink`         | `oklch(0.19 0.012 255)`  | Primary text, dark verdict panels, primary buttons |
| `ink-soft`    | `oklch(0.36 0.012 255)`  | Body copy                                          |
| `muted`       | `oklch(0.48 0.01 255)`   | Metadata, labels, helper copy                      |
| `rule`        | `oklch(0.862 0.006 255)` | Hairlines, table borders                           |
| `rule-strong` | `oklch(0.72 0.008 255)`  | Input borders, chips                               |
| `signal`      | `oklch(0.635 0.185 45)`  | Human authorization, primary CTA, one band         |
| `signal-deep` | `oklch(0.53 0.17 42)`    | Hover, emphasis on paper                           |
| `pass`        | `oklch(0.44 0.1 155)`    | Pass stamps, confirmed states                      |
| `warn`        | `oklch(0.47 0.11 70)`    | Unverified, pending                                |
| `fail`        | `oklch(0.5 0.18 27)`     | Fail, refusal, void, cancelled                     |
| `panel`       | `oklch(0.215 0.012 255)` | Specimen and pipeline panels                       |

Rules of use:

- Orange is not a decoration. It marks the buyer's authority (primary action, authorization band,
  the "not" in the headline). Status changes never use orange.
- Green, amber and red are **semantic**, never decorative.
- No gradient text, no glass, no coloured side borders, no soft pill cards.
- Body text is `ink-soft` on `paper`; muted text is never used for essential copy.

## Type

**Archivo** (self-hosted via Laravel's Bunny font pipeline) for everything. It is an institutional
grotesk with sturdy caps and excellent tabular figures, which suits a record without drifting into
the generic fintech sans stack.

- Display: 700, tight tracking (`-0.045em`), line-height 0.92–0.94, clamp up to 5.2rem.
- Section headings: 600, 20–22px, tracking `-0.03em`.
- Body: 13–17px, line-height 1.5–1.7, measure capped around 65–70ch.
- Labels: 9–10px uppercase, tracking 0.12–0.2em, muted.
- Numeric data, IDs and timestamps: tabular numerals; monospace only for real identifiers
  (PayPal order/capture IDs, UUIDs). Mono is never used as a "technical" costume.

## Layout

- 1180px content container, 20–32px gutters; full-width bands only for colour commitment.
- 12-column grid on large screens; records use 7/5 or 8/4 splits.
- Record header: 2px top rule, kicker + record ID, large title, optional description, a metadata
  row of small labelled values, actions right-aligned.
- Tables are the primary data component: 10px uppercase headers, 13px cells, 1px rules, no zebra
  stripes, no rounded containers.
- Cards are not the page structure. Borders define documents, not floating tiles. No nested cards.
- Mobile: tables become ruled stacked lists; the catalog row keeps a thumbnail beside the name and
  moves price to a full-width footer row.

## Components

| Component             | Character                                                                        |
| --------------------- | -------------------------------------------------------------------------------- |
| `RecordHeader`        | Document masthead: rule, kicker, ID, title, metadata, actions                    |
| `StatusStamp`         | Small bordered uppercase verdict chip; semantic colour only                      |
| `CheckTable`          | Requirement / evidence / result; explanatory detail under the check name         |
| `ProductIllustration` | Authored 1.4px line drawing per category, one orange detail                      |
| Verdict band          | Ink panel for qualified; red-bordered refusal panel with a full-width red header |
| Approval instrument   | Priced total, expiry countdown, authorization checkbox, PayPal handoff           |
| Flash banners         | Full-width ruled bands, labelled with the event type                             |
| Empty states          | A record-shaped panel with a next action, never an illustration                  |

## Motion

One authored moment: a 900ms orange rule sweeps across the qualified verdict band on load.
Nothing else hides content until animation completes. All motion respects
`prefers-reduced-motion`.

## States

Every state is designed, not defaulted:

- **Qualified** — ink band, pass stamps, score, authorize CTA.
- **Unverified** — amber stamp, explicit "missing from the catalog" copy.
- **Rejected / refused** — red band, closest option, unlock suggestion, no payment path.
- **Pending approval** — countdown, disabled-until-checked authorization.
- **Price changed** — `VOID` stamp, red panel, old vs new price, return-to-record action.
- **Expired** — closed instrument, no form.
- **PayPal not configured** — setup instructions instead of a fake success.
- **Payment pending / failed / cancelled** — verbatim provider status, no paid styling.
- **Empty audit** — a clear next action.
- **Processing** — button spinner and disabled state; content remains visible.

## Browser surfaces

Selection is signal orange on ink; caret is signal; focus rings are 2px signal with offset;
scrollbars are thin and ruled; link underlines use a 1px rule with generous offset; table numerals
are tabular. Print styles strip navigation and buttons so an audit record prints as a document.

## Accessibility

- Semantic headings, `table`/`th`/`td`, `fieldset`/`legend`, `label`, `button`.
- Visible focus, 44px-ish targets on primary actions, keyboard-operable controls.
- Text contrast targets ≥ 4.5:1; semantic colours are paired with words ("Pass", "Fail"), never
  colour alone.
- `aria-pressed` on category chips, `aria-expanded` on the trace disclosure, `aria-current` on nav.
- Reduced-motion support.
- Known gap: no formal screen-reader or axe audit has been run.

## What this design refuses

- A chat transcript as the primary surface.
- A generic "AI assistant" hero with a glowing orb.
- Dashboard metric cards as the product story.
- Decorative gradients, glass, soft shadows, rounded pills.
- Colour as the only carrier of meaning.
- Animations that hide evidence until they finish.
- A success screen that PayPal did not earn.
