# Design QA — Payment Method Dialog

- Source visual truth: `C:\Users\mathe\Downloads\ChatGPT Image Sep 11, 2026, 04_03_40 AM.png`
- Source pixels: 1777 × 885 at 96 DPI
- Implementation route: `/admin/payment-methods`
- Implementation screenshot: unavailable — neither the in-app browser nor Chrome was available
- Target CSS viewport: 1777 × 885 at device scale factor 1 (inferred from the source pixels and 96 DPI)
- States: authenticated admin, light theme, Add New and Edit dialogs; empty, stored, uploaded, replaced, and removed QR states

## Full-view comparison evidence

The source mockup was opened at original resolution and inspected before implementation. A browser-rendered implementation capture could not be obtained, so the required combined same-viewport comparison was not possible.

Source inspection established these target treatments:

- A wider centered dialog with a subtle blue-to-pink header wash.
- A two-column desktop body with form fields on the left and QR content on the right.
- A full-width Active panel and footer below both columns.
- A large QR preview inside a softly tinted rounded panel.
- Upload/Replace and Remove controls below the preview.

The implementation maps those treatments to existing Serenity Blue and Rose Quartz tokens and preserves the shared component's Add/Edit behavior.

## Focused region comparison evidence

Focused implementation captures were unavailable. Code-level inspection confirms:

- The dialog switches to two columns at `lg` and retains the original Name → Payment details → QR code → Sort order → Active order below that breakpoint.
- The Active section remains full width beneath the scrollable form grid.
- The empty QR state uses the existing Lucide `QrCode` icon and the caption “Sample preview.”
- Existing and object-URL image previews still render from the unchanged `shownUrl` computed value.
- Upload/Replace and Remove retain their original conditions, handlers, labels, accepted formats, and hidden file input.

## Required fidelity surfaces

- **Fonts and typography:** Existing admin typography is preserved. The title is enlarged to establish the hierarchy shown in the mockup; all form labels, help text, and button copy remain unchanged. Browser rendering remains unverified.
- **Spacing and layout rhythm:** The dialog uses a wider `sm:max-w-4xl` shell, an asymmetric two-column desktop grid, a divider before the QR column, larger section padding, and a full-width Active row. Mobile stacking and vertical scrolling are retained. Browser measurements remain unverified.
- **Colors and visual tokens:** Header and QR-panel gradients use only `sf-serenity-blue`, `sf-rose-quartz`, `sf-primary-soft`, and existing background/border tokens at low opacity. No new color values were introduced.
- **Image quality and asset fidelity:** Real QR image sources, object-fit behavior, and stored/uploaded image selection are unchanged. The empty illustration uses the project's existing icon library rather than a new or fabricated asset.
- **Copy and content:** Dialog titles, descriptions, field labels, validation output, help text, Active copy, action text, and conditional Upload/Replace wording are preserved; only “Sample preview” was added for the illustrative empty state.
- **Responsiveness and interaction:** Desktop column placement and mobile DOM order are encoded in responsive classes. Add/Edit submission, file selection, replacement, removal, cancellation, and toggle behavior are unchanged at code level but could not be exercised in a browser.

## Findings

- **P1 — Browser-rendered fidelity and interaction verification unavailable**
  - Location: `/admin/payment-methods`, Add New and Edit modes at desktop and mobile widths.
  - Evidence: browser discovery returned no available in-app or Chrome surface, so no implementation screenshot, combined comparison, keyboard pass, QR-state interaction pass, or console check could be produced.
  - Impact: exact visual fidelity, responsive overflow, focus behavior, and real stored/uploaded QR presentation cannot be confirmed from rendered evidence.
  - Fix: capture both dialog modes at 1777 × 885 and a narrow mobile viewport, compare the desktop capture alongside the source, then exercise upload, replace, remove, cancel, submit, toggle, focus, and keyboard-close behavior.

## Comparison history

- Initial source review: catalogued dialog proportions, header wash, two-column structure, QR panel, action placement, and full-width Active row.
- Implementation pass: applied responsive layout and token-based surface styling without changing validation, submission, or file-preview logic.
- Post-fix visual evidence: unavailable because no browser surface was connected.

## Implementation checklist

- Capture Add New and Edit at 1777 × 885 in light mode.
- Compare full-dialog and focused header/QR/action regions alongside the source image.
- Exercise stored, uploaded, replaced, removed, and empty QR states.
- Verify focus order, keyboard operation, close behavior, validation, and browser console output.
- Capture a narrow viewport and confirm the original top-to-bottom field order and usable scrolling.

final result: blocked
