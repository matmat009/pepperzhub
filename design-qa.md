# Protocols page design QA

- Source visual truth path: user-attached Protocols reference screenshots in the current request (conversation attachments; no local filesystem path was exposed).
- Source pixels: 1451 × 1158 for the hero/full-page reference and 1295 × 1135 for the lower-list reference.
- Implementation screenshot path: unavailable — the in-app browser reported that no browser session was available.
- Viewport: not captured.
- CSS size and density normalization: not available because the implementation could not be captured.
- State requested: desktop and mobile; default first accordion expanded, category/search filtering, empty state, keyboard toggle, and product link navigation.

## Full-view comparison evidence

The source screenshots were available in the request and the implementation was reviewed in source, but a browser-rendered implementation image could not be produced. A valid combined visual comparison was therefore not possible.

## Focused-region comparison evidence

Blocked with the full-view comparison. The hero/navigation overlap, reference sidebar, protocol header/filter row, collapsed and expanded accordion states, and mobile stacking still require browser-rendered inspection.

## Findings

- [P1] Browser-rendered visual evidence is missing.
  - Location: `/protocols`, desktop and mobile.
  - Evidence: the local Laravel and Vite servers respond successfully, but the in-app browser runtime returned `No browser is available` and exposed no selectable browser session.
  - Impact: typography, spacing, gradient continuity behind the navigation, wrapping, tap targets, interactive states, and console errors cannot be signed off visually.
  - Fix: capture the running page in an approved browser at desktop and mobile sizes, test its primary interactions and console, combine each capture with its matching source reference, and repeat QA until no P0/P1/P2 issues remain.

## Required fidelity surfaces

- Fonts and typography: implemented with the storefront's existing Lora `font-display`/`font-body` tokens; rendered verification is blocked.
- Spacing and layout rhythm: implemented as a 1180px container with a 270px desktop sidebar and 40px gap; rendered verification is blocked.
- Colors and visual tokens: the hero reuses the homepage gradient declaration exactly and page surfaces use existing `sf-*` tokens; rendered verification is blocked.
- Image quality and asset fidelity: the reference contains no page-specific raster assets; existing Lucide icons are used for UI symbols. Rendered icon alignment is not verified.
- Copy and content: existing hero, disclaimer, guidance, storage, product protocol, and footer copy is preserved; product/category/count content remains data-driven.

## Comparison history

- Initial pass: blocked before visual comparison because no browser session was available. No browser-rendered implementation image exists yet, so there is no post-fix visual evidence.

## Primary interaction coverage

- Source inspection confirms native button semantics, `aria-expanded`, `aria-controls`, visible focus styles, a separate product link, reactive search/category filtering, a clear-filter empty state, wrapping safeguards, and responsive grid classes.
- Browser interaction testing: blocked.
- Browser console errors checked: blocked.

## Implementation checklist

- Capture desktop default and expanded states.
- Test search, category filters, empty state, accordion pointer/keyboard toggles, and product links.
- Capture mobile layout and verify wrapping/overflow.
- Check the browser console.
- Run a combined source/implementation comparison and fix any P0/P1/P2 findings.

final result: blocked
