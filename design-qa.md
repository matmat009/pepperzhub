# Design QA — Payments Table and View Dialog

- Source visual truths:
  - `C:\Users\mathe\Downloads\ChatGPT Image Sep 11, 2026, 05_24_32 AM.png` — table, 1672 × 941 at 96 DPI
  - `C:\Users\mathe\Downloads\ChatGPT Image Sep 11, 2026, 05_24_22 AM.png` — View dialog, 1779 × 884 at 96 DPI
- Implementation route: `/admin/payment-methods`
- Implementation screenshot: unavailable — no in-app or connected browser surface was available
- Target CSS viewports: 1672 × 941 and 1779 × 884 at device scale factor 1
- States: authenticated admin, light theme; table with and without QR images; View dialog with QR image and empty QR state; desktop and narrow responsive layouts

## Full-view comparison evidence

Both source images were opened at original resolution before implementation. A browser-rendered implementation capture could not be obtained, so the required same-state combined comparison was not possible.

Source inspection established these target treatments:

- Always-visible Edit and Delete actions with blue and red outlined surfaces, icons, and compact horizontal alignment.
- A larger table QR thumbnail that preserves the full image.
- A View dialog with a pastel Serenity Blue-to-Rose Quartz header wash.
- A two-column desktop body: payment details, sort order, and status on the left; QR content on the right.
- A friendly QR-icon empty state inside a softly tinted panel.

## Focused region comparison evidence

Focused implementation captures were unavailable. Code-level inspection confirms:

- Table action handlers and emitted records are unchanged; only variants, icons, labels, and classes changed.
- Table QR images use intrinsic `width: auto` and `height: auto`, with only maximum width/height bounds. The thumbnail wrapper has no aspect-ratio or overflow-clipping class.
- View QR images use the same intrinsic sizing approach. The image wrapper and QR panel have no fixed aspect ratio and no overflow-clipping class.
- The View dialog switches to two columns at `lg` and stacks in source order below that breakpoint.
- Close and Edit handlers are unchanged.

## Required fidelity surfaces

- **Fonts and typography:** Existing admin font, hierarchy, labels, values, and table copy are preserved. The View title uses the same enlarged treatment as the Create/Edit dialog. Browser rendering remains unverified.
- **Spacing and layout rhythm:** Table actions use compact small buttons; the QR thumbnail has a 64px minimum neutral frame and scales up to 64px wide or 80px high. The View dialog uses a wider 3xl shell, responsive asymmetric columns, and a divided QR column. Browser measurements remain unverified.
- **Colors and visual tokens:** Edit, header, QR panel, and empty-state icon use existing Serenity Blue and Rose Quartz tokens. Delete uses the existing destructive token. No new color values were introduced.
- **Image quality and asset fidelity:** Real QR sources are unchanged. Both table and modal images retain their natural proportions, use `max-width`/`max-height` caps, and have no fixed-square or hidden-overflow image wrapper. No image asset was generated or substituted.
- **Copy and content:** Existing table data, modal title/description, payment details, sort order, status, and Close/Edit copy are unchanged. “No QR code uploaded.” remains and is now integrated with the empty-state icon.
- **Responsiveness and interaction:** The View content stacks on smaller screens. Existing row click, Edit, Delete, Close, and View-to-Edit behavior is unchanged at code level, but could not be exercised in a browser.

## Findings

- **P1 — Browser-rendered fidelity and interaction verification unavailable**
  - Location: `/admin/payment-methods`, table and View dialog at the source desktop sizes and a narrow viewport.
  - Evidence: browser discovery returned no available surface, so no implementation screenshot, combined comparison, hover/focus check, QR-state check, or console inspection could be produced.
  - Impact: exact visual fidelity, responsive wrapping, row density, and the real portrait/landscape/square QR rendering cannot be certified from rendered evidence.
  - Fix: capture the authenticated table and both View QR states at the matching desktop sizes, compare each beside its source, then repeat at a narrow viewport and with portrait, landscape, and square images.

## Comparison history

- Initial source review: catalogued action-button treatment, QR thumbnail scale, modal gradient, two-column grouping, and empty state.
- Implementation pass: applied scoped table and View-dialog styling while preserving data and event behavior.
- Post-fix visual evidence: unavailable because no browser surface was connected.

## Implementation checklist

- Capture the Payments table at 1672 × 941 with a QR-bearing row.
- Open a method with a QR code at 1779 × 884 and verify the complete image is visible.
- Open a method without a QR code and compare the empty state to the modal source.
- Exercise Edit, Delete, Close, row View, and View-to-Edit behavior with keyboard focus and hover states.
- Repeat QR checks with portrait, landscape, and square files and at a narrow viewport.
- Check browser console output and update this report with the final comparison.

final result: blocked
