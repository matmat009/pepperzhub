# Checkout payment-proof preview — design QA

- Source visual truth: `C:\Users\mathe\Downloads\ChatGPT Image Sep 6, 2026, 03_18_50 AM.png`
- Implementation: `resources/js/pages/storefront/Checkout.vue`
- Implementation screenshot: unavailable because no in-app or connected browser was exposed to this session
- Intended viewport: desktop, matching the 1368 × 1149 source capture; mobile is also in scope
- Source pixels: 1368 × 1149
- Source CSS size and density: not embedded in the PNG; comparison would use 1368 × 1149 CSS pixels at device scale factor 1
- Implementation pixels, CSS size, and density normalization: unavailable without a browser-rendered capture
- State: a valid JPG or PNG payment proof selected and its preview fully loaded

**Findings**

- [P1] Browser-rendered visual comparison is blocked.
  Location: checkout payment-proof uploaded state.
  Evidence: the source visual was opened and inspected, but the implementation could not be opened in the required browser because both the in-app browser and connected Chrome were unavailable.
  Impact: exact rendered proportions, responsive wrapping, focus treatment, and lightbox behavior cannot be signed off from source code alone.
  Fix: capture the uploaded state at 1368 × 1149 and a representative mobile viewport in the integrated browser, then compare those captures with the source.

**Required fidelity surfaces**

- Fonts and typography: implementation reuses the storefront's Lora `font-display` and inherited `font-body` conventions. Rendered weight, line height, wrapping, and antialiasing remain unverified.
- Spacing and layout rhythm: the implementation uses a 43/57 desktop split, 330 px minimum panel height, 12 px radii, a stacked mobile layout, and balanced internal padding. Rendered dimensions remain unverified.
- Colors and visual tokens: Blue Serenity tokens drive interaction and focus treatments; Rose Quartz is limited to a subtle panel border; green is limited to the success indicator. Rendered contrast remains unverified.
- Image quality and asset fidelity: both inline and enlarged previews use `object-contain`; no replacement raster assets, custom SVGs, CSS art, or gradients were introduced. Portrait, landscape, and square rendering remains unverified in-browser.
- Copy and content: the real filename, “Image attached successfully,” “Click to enlarge,” Replace, Remove, and the server-aligned `JPG, PNG or PDF · Max 5MB` guidance are present.

**Full-view comparison evidence**

- Source visual: opened at its native 1368 × 1149 resolution.
- Implementation capture: unavailable, so no same-viewport combined comparison could be made.

**Focused region comparison evidence**

- The payment-proof panel is the required focused region. A rendered crop is unavailable, so its comparison is blocked.

**Interaction verification**

- Static inspection confirms object URLs are revoked on replacement, removal, failed preview generation, and unmount.
- TypeScript, ESLint, Prettier, Pint, the PHP test suite, and the production build pass.
- Upload, keyboard activation, Escape/close behavior, focus restoration, replace/remove behavior, responsive behavior, and browser console errors could not be exercised without a browser surface.

**Comparison history**

- Pass 1: source opened; implementation capture blocked because no browser surface was available. No visual fixes were made from a rendered comparison.

**Implementation checklist**

- Capture the loaded image state at the matching desktop viewport.
- Exercise click and keyboard opening, visible close, Escape, and focus restoration.
- Test replacement, removal, invalid file handling, JPG/PNG/PDF selection, and repeated selection of the same file.
- Capture portrait, landscape, square, and mobile states.
- Check the console and repeat the combined visual comparison.

**Follow-up polish**

- None identified without rendered evidence.

final result: blocked
