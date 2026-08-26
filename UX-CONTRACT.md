# GamoryID UX Contract

## Product context

- Audience: Thai digital-inventory merchants and their staff.
- Primary jobs: import, exact-tag search, filter, reserve, sell, export, manage access, and renew a plan.
- Active locale/timezone: `th-TH`, `Asia/Bangkok`, Gregorian calendar, THB.
- Accessibility target: WCAG 2.2 AA.

## Business-context sources

| Domain | Authoritative source | Type | Reviewed |
|---|---|---|---|
| Product scope and lifecycle | `plan.md` | Approved product plan | 2026-08-26 |
| Permission/security behavior | `plan.md` and server Policies | Product plan + enforced domain rules | 2026-08-26 |
| API contract | `backend/openapi.yaml` | OpenAPI contract | implementation-owned |
| Legal/product risk | `plan.md` | Product-plan legal gate | 2026-08-26 |

## Visual contract

- Source: `DESIGN.md`.
- Ownership: `DESIGN.md` is canonical; CSS token files mirror it.
- Themes: light only for MVP; forced-colors and reduced-motion remain supported.
- Visual references: `docs/design/dashboard-desktop-concept.png`, `docs/design/inventory-mobile-concept.png`.

## Canonical UI Map

| Capability | Canonical owner | Source of truth | Allowed variants | Verification |
|---|---|---|---|---|
| Table Selection | `DataTable` + `useTableSelection` | `UX-CONTRACT.md` | page selection | component + E2E |
| Select/Listbox | `NativeSelect` | `premium-ui.json` ownership | platform-owned popup | keyboard + responsive |
| Date | native date/datetime input | `premium-ui.json` ownership | platform-owned popup | locale + keyboard |
| Form | shared `Field` + validation adapter | `merchant/src/App.tsx` | create/edit | validation tests |
| Scrollbar | global token stylesheet | `merchant/src/index.css` | stable-gutter surfaces | computed style |
| Toast | app toast region | `merchant/src/App.tsx` | success/warning/info/error | live-region test |
| Dialog | app-owned dialog | `merchant/src/App.tsx` | confirm/form/detail | keyboard + focus tests |
| CRUD | route + API service conventions | `backend/openapi.yaml` | return-to-list/stay-on-detail | full-flow E2E |

## Dataset navigation

- Admin tables use server pagination with 25 rows by default and 25/50/100 options.
- Committed query, status, region, sort, page, and page size live in URL search params.
- Remote search is 300ms debounced, IME-safe, cancelable, and immediately clearable.
- Empty, no-result, loading, partial-error, and total/range states keep stable table geometry.
- Mobile lists load the same paginated dataset and preserve the selected record until the dataset changes.

## Flow ledger

| Operation | Pending | Success | Failure recovery |
|---|---|---|---|
| Create inventory | Disable stable-width submit | Return to inventory and toast | Preserve values, map server errors inline |
| Edit inventory | Pessimistic save | Return to owning list state | Keep form open and retry |
| Search | Inline spinner without layout shift | Replace rows and range | Keep prior rows with retry banner |
| Reserve | Pessimistic transaction | Status/timeline update and toast | Restore action and explain conflict |
| Sell | Pessimistic locked transaction | Sold status, sale record, dashboard invalidation | Preserve dialog data; show already-sold conflict |
| CSV upload | Validating/uploading/queued progress | Summary with imported/errors and rollback action | Keep file and mapping for retry |
| Slip upload | Upload then queued verification | Active subscription notification | `pending_review` with support guidance |
| Archive | Confirmation dialog | Remove from active list, offer filtered recovery | Keep row and announce failure |

## Navigation and responsive behavior

- Document title: `{Page} — GamoryID`; never include secrets or customer PII.
- Direct forbidden routes render a Thai 403 page; unknown routes render 404; server failures provide retry.
- Desktop sidebar becomes bottom navigation below 768px; secondary routes move to `เพิ่มเติม`.
- Tables become compact semantic record rows on mobile; details open in a right drawer/full-width sheet.
- Sticky controls must not obscure focus; use safe-area padding and scroll-margin.

## Overlays and feedback

- Global layers: dropdown 200, popover 300, sticky 400, backdrop 500, dialog 600, sheet 700, command 800, toast 900.
- Destructive/privacy-sensitive/permission-changing operations require an app-owned confirmation.
- Modal overlays trap focus, close with Escape as cancel, make the background inert, and restore trigger focus.
- Toasts deduplicate for 2 seconds; success/info dismiss in 5 seconds, errors persist until dismissed.
- Unsaved forms warn on in-app navigation and on real page unload only when dirty.

## Async and resilience

- Mutations are pessimistic unless a purely reversible UI preference is changed.
- Every POST that can duplicate money/sale/import work uses an idempotency key.
- Offline mode is read-stale/write-blocked with a persistent banner.
- Session expiry preserves non-secret form state and routes to re-authentication.
- Credentials are never persisted in browser storage; reveal requires server permission, 2FA, and recent re-authentication.
- Superseded requests are aborted; server data is invalidated after mutations.

## Validation and permissions

- Product forms use `noValidate`, Zod client validation, Laravel FormRequest server validation, first-invalid focus, and duplicate-submit prevention.
- Secret inputs are masked with accessible show/hide controls and `autocomplete` metadata.
- Irrelevant routes are hidden; visible-but-read-only actions are disabled with an accessible explanation; direct access receives 403.
- Clipboard copies never echo the copied value in a toast, log, URL, or analytics event.

## Verification

- Static: Oxlint, TypeScript build, Vitest, PHPUnit, Pint, premium audit.
- Browser: 1440px desktop plus 390px mobile; keyboard, empty/no-result, dialog/select open, reduced motion, and failure states.
- Canonical comparison: dashboard and inventory share the same app shell, action labels, feedback, row state, and signal-rail treatment.
