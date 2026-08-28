# Design reference — source of truth

This folder holds the Claude Design export that the public storefront was built
from. It is **reference material, not source code**: nothing in the app imports
it, it is excluded from ESLint (`design/**` in `eslint.config.js`), and it is
never built. It is committed so the provenance of every storefront token and
layout decision stays recoverable.

The original project lives at `claude.ai/design` under
`pepperzhub-peptide-storefront`. Keep this copy — re-fetching it requires
Claude Design access that is not currently authorized on this account.

## What is here

`pepperzhub-peptide-storefront/project/` — seven `.dc.html` artboards plus the
prototype runtime (`support.js`, `image-slot.js`) and `uploads/`.

The artboards are self-contained: they style inline with OKLCH values and do
not import a shared design system.

## Two things to know before using this as a spec

**1. The `_ds/` folder was deleted, deliberately.**

The export shipped one design-system folder, `modernist-df23f691-…`, carrying a
red `#ec3013` accent, Archivo, `0px` radius everywhere and grayscale-only
imagery. That is not PepperzHub's brand. It was also **orphaned** — no artboard
referenced it — so removing it broke nothing. Do not restore it, and do not
treat it as authoritative if it reappears in a future export.

The real tokens come from the artboards themselves and are mirrored into the
`@theme` block in `resources/css/app.css`: a blue at hue 264 (Serenity Blue)
and a rose at hue 14 (Rose Quartz), both deepened from the pastel brand
swatches so they clear contrast as text.

**2. Typography intentionally diverges from the artboards.**

The artboards set everything in Lora (serif). The implementation uses
**Space Grotesk** for display and **DM Sans** for body, per the brand spec,
loaded via `bunny()` in `vite.config.ts`. Colour, spacing and radius follow the
artboards; type does not. This was a deliberate call, not drift.

## Implementation confidence — read this before refining a page

Not every page was translated with the same rigour.

| Page | Artboard | Confidence |
|---|---|---|
| Home | `PepperzHub Home.dc.html` | Close read |
| Catalog | `PepperzHub Catalog.dc.html` | Close read |
| Product Detail | `PepperzHub Product.dc.html` | Close read |
| Cart | `PepperzHub Cart.dc.html` | **Structural read only** |
| Checkout | `PepperzHub Checkout.dc.html` | **Structural read only** |
| Order Confirmation | `PepperzHub Order Confirmation.dc.html` | **Structural read only** |
| Track Order | `PepperzHub Track Order.dc.html` | **Structural read only** |

The four marked **structural read only** were built from layout structure plus
the data embedded in each artboard's `<script type="text/x-dc">` block — the
courier rates, payment account details and the demo order record are all
faithful. What was *not* done is a line-by-line pass over their markup, so
spacing, intermediate states and smaller responsive details may differ from the
design.

If any of those four needs to look exactly right, diff the page against its
artboard before adjusting by eye.

## Prototype DSL, for reading the artboards

| In the artboard | Vue equivalent |
|---|---|
| `<sc-if value="{{ x }}">` | `v-if` |
| `<sc-for list="{{ xs }}" as="x">` | `v-for` |
| `style-hover="…"` | `hover:` utilities |
| `<image-slot>` | product image with `FlaskConical` fallback |
| `<helmet>` | `<Head>` / document head |

`support.js` and `image-slot.js` are the prototype's own runtime. They were
read for behavioural context only and were deliberately **not** ported or
introduced as a dependency.

## Assets

The brand files under `project/uploads/` are extracted, unchanged, into
`public/images/branding/`:

| Upload | Copied to | Used by |
|---|---|---|
| `b35453eb-….png` | `pepperzhub-emblem.png` | navbar, footer, newsletter panel |
| `19d2999d-….png` | `pepperzhub-lockup.png` | Home hero |
| `4e8981a7-….jpg` | `pepperzhub-badge.jpg` | not yet used — circular badge, suits favicon / social avatar / OG image |
| `pasted-1787696164635-0.png` | `pepperzhub-wordmark.png` | not yet used — raster wordmark |

The wordmark and tagline render as live type, not images, so the raster
versions are held only as fallbacks.
