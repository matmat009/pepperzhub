# Design QA — Right-side Logo Placement

- Source visual truth: `C:\Users\mathe\Downloads\peptize\ChatGPT Image Sep 10, 2026, 02_06_33 AM.png`
- Source pixels: 1536 × 1024
- Implementation route: `/login`
- Implementation screenshot: unavailable — the in-app browser reported no available browser surface
- Requested CSS viewports at device scale factor 1: 1366 × 768, 1440 × 900, and 1920 × 1080
- State: unauthenticated, light theme, empty form

## Full-view comparison evidence

The source image was opened at its original resolution. A browser-rendered implementation capture was unavailable, so the required combined visual comparison could not be performed.

Source inspection confirms the lockup is a separate proportional `<img>` layered above the artwork rather than embedded in the background image. The background asset and crop were not moved.

## Focused region comparison evidence

Focused browser captures were unavailable. Source-level checks confirm:

- The right panel now uses two grid rows: a flexible upper logo area and an unchanged lower copy area.
- The logo is centered in both axes inside the available space above the copy.
- The logo retains proportional auto height, 68% responsive width, a 304px maximum, and horizontal centering.
- The text row retains its existing bottom position and responsive top padding.
- The shorter-than-800px panel padding remains in place, allowing the upper logo area and its gap to contract naturally.

## Required fidelity surfaces

- **Fonts and typography:** Existing Geist admin typography is preserved. The welcome heading is 28px below 640px and 32px from 640px upward; right-panel display copy is 28–32px; labels and supporting copy remain 14–16px.
- **Spacing and layout rhythm:** Oversized 688–768px minimum heights were removed. Desktop panel padding is 36–40px, tablet padding is 28px, and mobile padding is 20px. Short desktop viewports reduce outer and panel vertical padding and decorative gaps.
- **Colors and visual tokens:** Existing Serenity Blue and Rose Quartz tokens, slate supporting text, blue links, white primary-button text, dark-mode colors, and focus-ring tokens are retained.
- **Image quality and asset fidelity:** The original emblem, wordmark, and lockup assets remain in use. The existing generated artwork remains a raster image and uses a non-distorting cover crop.
- **Copy and content:** All approved headings, labels, tagline, supporting copy, and footer text are unchanged.

## Findings

- **P1 — Browser-rendered logo-placement verification unavailable**
  - Location: `/login`, right artwork panel at the three requested desktop viewports.
  - Evidence: browser discovery returned no available browser surfaces.
  - Impact: the precise 40–60px visual shift, final logo-to-copy gap, and overlap or clipping cannot be confirmed from a rendered page.
  - Fix: capture and inspect the three requested desktop viewports in an available browser and resolve any rendered P0/P1/P2 placement differences.

## Preservation checks

- Left-side form markup and authentication behavior were not changed.
- Artwork source, image positioning, panel dimensions, colors, copy, and responsive visibility were not changed.
- No absolute logo positioning, translation, fixed vertical offset, or large margin was introduced.
- Browser console errors: not checked because no browser surface was available.

## Comparison history

- Previous logo layout: top-biased flex item followed by an auto margin before the text.
- Current logo layout: vertically centered inside a flexible upper grid row, with the lower text row remaining anchored at the panel bottom.
- Post-fix browser evidence: unavailable.

## Implementation checklist

- Capture the default light state at 1366 × 768, 1440 × 900, and 1920 × 1080.
- Confirm the logo sits approximately 40–60px lower, remains horizontally centered, and keeps its original size and proportions.
- Confirm the headline and supporting copy did not move, and that the logo, copy, and panel do not overlap or clip.
- Re-run the combined source/implementation comparison and update this report.

final result: blocked
