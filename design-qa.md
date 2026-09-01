# GamoryID Design QA

final result: passed

## Settings contact-field icon spacing

- Reference: `C:\Users\User\AppData\Local\Temp\codex-clipboard-248b60ff-634d-4f34-962c-ac44625e8e20.png`
- Implementation capture: `docs/design/settings-phone-icon-fixed.jpg`
- Focused implementation crop: `docs/design/settings-phone-icon-fixed-crop.jpg`
- Side-by-side comparison: `docs/design/settings-phone-icon-comparison.jpg`
- Runtime route: authenticated Merchant settings at `http://localhost:5173/`

### Findings resolved

- Removed the settings-only icon-input padding rules that were being overridden by the later shared form recipe.
- Moved icon-input geometry into the canonical Tailwind form layer so Facebook, LINE, and phone fields share the same stable layout across settings and sale forms.
- Reserved 40px of leading input space; the 16px icon now has a measured 12px gap before placeholder or entered text.
- Verified the same 40px padding and 12px gap at the 390px mobile breakpoint.

### Verification evidence

- `npm run test:unit`: 11 tests passed.
- Merchant, Public Web, and Super Admin production builds passed.
- `npm run lint`: completed with no errors; existing React Compiler warnings remain outside this CSS change.
- Frontend Design Premium strict audit: 0 findings.
- Browser computed styles confirmed the repaired geometry and browser console contained no warnings or errors.
- Focused side-by-side comparison showed no unresolved P0, P1, or P2 issue.

## Inventory search placement

- Reference: `C:\Users\User\AppData\Local\Temp\codex-clipboard-7057f445-6241-4314-870b-1c42e347a611.png`
- Implementation capture: `docs/design/inventory-search-relocated-desktop.jpg`
- Mobile capture: `docs/design/inventory-search-relocated-mobile.jpg`
- Full comparison: `docs/design/inventory-search-relocation-comparison.jpg`
- Focused comparison: `docs/design/inventory-search-relocation-focused-comparison.jpg`
- Runtime route: authenticated Merchant inventory at `http://localhost:5173/`
- Desktop evidence: reference supplied at 2194 × 1163 and implementation checked with the same viewport override.
- Mobile evidence: checked with a temporary 390 × 844 viewport override, then reset.

### Findings resolved

- Removed the inventory search field from the shared top bar so Dashboard and all non-inventory pages no longer show it.
- Placed search directly between the inventory KPI summary and the latest-ID work surface, matching the requested information hierarchy.
- Kept the account and notification controls right-aligned in the desktop top bar and removed the now-empty top bar at mobile width.
- Added an accessible clear control that restores focus, a polite result count, and IME-safe debounced search behavior for Thai input.

### Verification evidence

- `npm run test -- --run`: 11 tests passed.
- `npm run build`: TypeScript and Vite production build passed.
- `npm run lint`: completed with no errors; existing React Compiler warnings remain outside this change.
- Frontend Design Premium strict audit: 0 findings.
- Browser DOM checks confirmed the order KPI → search → latest IDs, search absence on Dashboard, filtering and clearing behavior, focus restoration, and mobile visibility.
- Focused side-by-side comparison showed no unresolved P0, P1, or P2 visual issue; a second crop was included because the placement change is most legible in the upper inventory work area.

## Inventory status and close-sale flow

- Reference: `C:\Users\User\AppData\Local\Temp\codex-clipboard-5008cec8-048e-4616-a976-11f3173b4be6.png`
- Implementation capture: `docs/design/inventory-status-table-qa.jpg`
- Close-sale modal capture: `docs/design/inventory-sale-modal-qa.jpg`
- Mobile modal capture: `docs/design/inventory-sale-modal-mobile-qa.jpg`
- Side-by-side comparison: `docs/design/inventory-source-comparison.jpg`
- Runtime route: authenticated Merchant inventory at `http://localhost:5173/`
- Desktop evidence: source and implementation inspected together at 1916 × 1065.
- Mobile evidence: inspected with a temporary 390 × 844 viewport override, then reset.

### Findings resolved

- Converted each inventory status badge into a directly editable, labeled status control while keeping the established badge colors.
- Selecting `ขายแล้ว` now opens the close-sale workflow without navigating away from the inventory table.
- Added a structured item preview, required customer name and sold price, optional Facebook/LINE/phone, warranty toggle with conditional native date picker, notes, and a clear save consequence.
- Kept sold and archived records locked to protect transaction history; available and reserved records remain actionable.
- Added inline validation, first-invalid focus, initial dialog focus, backdrop/Escape close, focus trapping, and duplicate-submit protection.
- Confirmed the modal uses an internal scroll region with persistent actions and remains usable at mobile width.

### Verification evidence

- `npm run test -- --run`: 8 tests passed.
- `npm run build`: TypeScript and Vite production build passed.
- Laravel feature suite: 32 tests passed with 193 assertions.
- Laravel Pint check: passed.
- Frontend Design Premium strict audit: 0 findings.
- Browser DOM and visual checks confirmed direct status editing, modal launch, conditional warranty date field, inline validation, correct initial focus, desktop layout, and mobile layout.

## Settings page comparison

- Reference: `C:\Users\User\AppData\Local\Temp\codex-clipboard-43847ecc-b702-4dcf-b398-7f05cc9940cf.png`
- Implementation capture: `docs/design/settings-desktop-qa.jpg`
- Side-by-side comparison: `docs/design/settings-source-comparison.jpg`
- Runtime route: authenticated Merchant settings at `http://localhost:5173/`
- Desktop evidence: inspected at the supplied Chrome viewport (2556 × 1249).
- Mobile evidence: inspected with a temporary 390 × 844 viewport override, then reset.

## Findings resolved

- Replaced the edge-to-edge form layout with a padded task surface and three clearly separated sections.
- Increased field, label, helper-text, and section spacing while retaining the existing GamoryID navy, blue, cyan, and orange theme.
- Kept related desktop fields in two columns and converted them to one column on narrow screens.
- Added a dedicated, stable save footer with a clear consequence hint and full-width mobile action.
- Preserved every field name, existing value, validation rule, and submit behavior.
- Verified that Thai labels wrap without clipping, inputs remain at least 44 px high, and the page keeps natural document scrolling.

## Verification evidence

- `npm run lint`: completed with no errors; existing React Compiler warnings remain outside this visual change.
- `npm run test -- --run`: 7 tests passed.
- `npm run build`: TypeScript and Vite production build passed.
- Frontend Design Premium strict audit: 0 findings.
- Browser DOM check confirmed semantic headings, labeled controls, helper text, and the save action.

## React architecture refactor

- Merchant entry: `merchant/src/App.tsx`
- Router: `merchant/src/app/router.tsx`
- Feature modules: `merchant/src/features/`
- Shared hooks, utilities, and UI: `merchant/src/shared/`
- Domain models: `merchant/src/types/models.ts`
- Landing modules: `public-web/src/features/landing/`
- Desktop comparisons: `docs/design/react-refactor/dashboard-comparison.jpg`, `inventory-comparison.jpg`, and `settings-comparison.jpg`
- Mobile evidence: `docs/design/react-refactor/inventory-mobile-after.jpg`

### Findings resolved

- Reduced the Merchant `App.tsx` entry to a stable public boundary and moved the authenticated route tree into a dedicated application module.
- Replaced local page switching with React Router paths while preserving navigation labels, active states, browser history, authentication, and test behavior.
- Split Merchant UI by domain: authentication, dashboard, inventory, import, sales/customer history, team, billing, transactions, and settings.
- Centralized API/domain types, formatting, clipboard behavior, modal behavior, form primitives, async error UI, navigation definitions, and inventory seed data.
- Split the Landing React app into header, hero, feature/workflow, pricing/CTA, footer, and content modules without changing the established theme or layout classes.
- Documented ownership rules so new features do not accumulate in application entry files.

### Verification evidence

- Merchant TypeScript compilation and Vite production build passed.
- Public Web TypeScript compilation and Vite production build passed.
- Merchant unit/integration suite: 11 tests passed.
- Oxlint completed with no errors; existing React Compiler advisory warnings remain.
- Desktop before/after comparisons found no unresolved P0, P1, or P2 visual regression.
- Browser route checks confirmed `/`, `/inventory`, and `/settings`, including active navigation and document titles.
- Mobile inventory remained usable at 390 × 844 with card layout, search, direct status controls, actions, and bottom navigation.

## Merchant authentication card redesign

- Visual source: `C:\Users\User\AppData\Local\Temp\codex-clipboard-c5a7d54f-06b2-4116-aa73-0ced92bc9801.png`
- Normalized source: `docs/design/merchant-admin-reference-cropped.jpg`
- Login implementation: `docs/design/merchant-login-reference-viewport.jpg`
- Registration implementation: `docs/design/merchant-register-admin-style.jpg`
- Mobile evidence: `docs/design/merchant-login-mobile.jpg` and `docs/design/merchant-register-mobile.jpg`
- Combined same-viewport comparison: `docs/design/merchant-auth-admin-comparison.jpg`
- Runtime routes: `http://localhost:5173/login` and `http://localhost:5173/register`
- Desktop comparison viewport: 2559 × 1273 CSS pixels at density 1.0; source browser chrome was cropped so both states use the same pixel dimensions.
- Mobile verification viewport: 390 × 844 CSS pixels at density 1.0.
- State: unauthenticated, empty login and registration forms; desktop art panel visible and mobile art panel intentionally collapsed.

### Findings resolved

- Replaced the prior viewport-wide split layout with the same 920px centered shell used by Super Admin: white task panel, navy context panel, cyan divider, rounded border, and controlled shadow.
- Kept merchant identity through the Gammy mark and `MERCHANT` role label without changing the established navy, cyan, blue, and orange theme.
- Gave registration a wider 1040px shell so owner and shop fields remain comfortable without compressing labels or controls.
- Standardized controls to a 44px minimum target, removed inherited decorative rails and oversized field spacing, and retained visible focus, inline errors, first-invalid focus, and password reveal behavior.
- Collapsed the contextual panel below 760px and verified both forms have no horizontal overflow at 390px.
- First comparison exposed inherited full-screen grid columns and large-screen form spacing; the auth layer now explicitly resets those rules before applying the centered-card geometry.

### Verification evidence

- Production build passed with Vite and TypeScript.
- React test suite: 25 tests passed across 6 files.
- Oxlint completed with no errors; existing React Compiler advisory warnings remain outside this visual change.
- Browser checks confirmed empty-submit validation, first-invalid focus, password reveal state, correct desktop panel visibility, correct mobile panel collapse, and zero mobile horizontal overflow.
- Same-viewport comparison found no unresolved P0, P1, or P2 visual issue.

final result: passed
