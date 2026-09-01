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
| Form | shared `Field` + Tailwind form recipes | `merchant/src/App.tsx`, `merchant/src/styles/tailwind-system.css`, `backend/resources/css/app.css` | create/edit/admin | validation tests |
| File Upload | `InventoryMediaFields`, `ImportPanel` | inventory media source + `merchant/src/features/imports/ImportPanel.tsx` | inventory images; Excel/CSV inventory import | client + API tests |
| Scrollbar | global Tailwind application stylesheets | `merchant/src/index.css`, `backend/resources/css/app.css` | stable-gutter surfaces | computed style |
| Toast | app toast region | `merchant/src/App.tsx` | success/warning/info/error | live-region test |
| Dialog | Merchant dialog + Admin confirmation dialog | `merchant/src/App.tsx`, `backend/resources/views/admin/console.blade.php` | confirm/form/detail | keyboard + focus tests |
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
| Manage inventory images | Preview one Display image and up to four detail images before upload | Refresh the item detail gallery with private signed media URLs | Keep the saved inventory item, identify partial media failure, and allow retry from edit |
| Copy inventory details | Compose tag, Riot ID, rank, level, public description, price, and shop footer locally | Copy once and show a generic success toast | Keep the record unchanged and offer retry |
| Search | Inline spinner without layout shift | Replace rows and range | Keep prior rows with retry banner |
| Reserve | Pessimistic transaction | Status/timeline update and toast | Restore action and explain conflict |
| Change inventory status | Click the current status control; available↔reserved uses the reservation transaction, selecting sold opens the sale form | Refresh inventory/dashboard and announce the committed status | Keep the prior status selected and explain the conflict |
| Update inventory reminder note | Open from the row or item detail and save pessimistically | Refresh the row/detail note and record a content-free audit event | Keep the dialog text and show the server error for retry |
| Sell | Pessimistic locked transaction with customer, contact, price, optional warranty date, and notes | Sold status, sale/customer record, dashboard invalidation | Preserve dialog data; show validation or already-sold conflict inline |
| Excel/CSV upload | Download canonical template → validate/upload → map/preview → queued progress | Summary with imported/errors; commit only when every row is valid | Keep selected filename and mapping visible; explain the rows to fix before retry |
| Credit top-up | Upload then queued verification | Move to Super Admin review; credit changes only after approval | Preserve `pending_review` status with review guidance |
| Package purchase | Pessimistic, idempotency-key protected credit debit | Refresh balance and active subscription | Preserve credit balance; explain insufficient credit |
| Auto-renew | Explicit confirmation before enabling | Switch reflects the saved preference | Keep current preference and show failure toast |
| Merchant transaction history | Read-only fetch scoped by current shop and billing permission | Show service and credit top-up sections with Thai status labels | Keep prior data when available and provide retry |
| Connect Discord server | Generate a 10-minute one-time setup code and register the canonical Thai `/ร้าน` command | `/ร้าน ตั้งค่า` binds one guild to one shop and returns an ephemeral confirmation | Keep the shop disconnected; allow a new code without exposing configuration secrets |
| Link Discord member | Generate a 10-minute code scoped to the current shop and user | `/ร้าน เชื่อมบัญชี` binds the Discord user to the existing shop member only from the configured command room | Expired, wrong-shop, or wrong-channel attempts leave the code unused so the member can retry correctly |
| Find inventory from Discord | Verify the signed interaction, guild installation, user link, shop membership, and inventory permission | Return exact-tag public details as an ephemeral response | Return a private not-found/permission message; never include credentials, username, cost, or internal notes |
| Summarize/list inventory from Discord | Re-resolve the linked active shop member and current permissions for every interaction | Return an ephemeral shop summary or 1–10 recent items; profit is visible only to Owner or `profit.view` | Omit profit or deny the list privately without leaking tenant data |
| Mutate inventory from Discord | Require the configured `คำสั่งทั่วไป` room, an active linked member, a writable shop, and the current command permission | Reserve, cancel, sell, note, or create through tenant-scoped transactions and append a content-safe audit event | Preserve the committed state on notification failure; return validation/conflict details privately and never accept credentials through Discord |
| Resolve Discord staff permission | Treat the existing ShopMember permission set as the single source of truth and check it on every command | `inventory.manage` controls add/list/note; `inventory.sell` controls reserve/cancel/sell plus list/note; `profit.view` reveals summary profit | A removed member or revoked permission is denied immediately; `discord.manage` never grants stock or sale access |
| Configure Discord rooms | Pessimistic create/save/test against the connected guild | Create one Thai `คำสั่งทั่วไป` room plus four Thai notification rooms, refresh canonical bindings, and acknowledge the tested room | Preserve saved bindings and show an inline retryable sync error |
| Inventory, reservation, or sale event | Commit the tenant-scoped business transaction first, then enqueue a safe Discord notification | Inventory notices include public item details, actor name, and a direct item link; sale notices include customer display name, seller name, warranty/time, public price, and a direct sale link | Retry through the notification queue; never roll back the business transaction or include credentials, cost, profit, customer contact fields, or internal notes |
| View sale detail | Route-backed `/sales/{id}` fetch scoped to the current shop and selling permission | Show item, customer contact, seller, warranty, notes, and direct inventory navigation; show cost/profit only with `profit.view` | A foreign or missing sale returns 404; preserve the list route as the recovery destination |
| Archive | Confirmation dialog | Remove from active list, offer filtered recovery | Keep row and announce failure |
| Admin shop create/edit | Stable submit; server validation | Create returns to detail; edit returns to detail | Preserve values and show summary + field errors |
| Admin shop archive/restore | App-owned confirmation for archive | Keep financial/package history; restored shop remains suspended | No hard delete; keep current page on failure |
| Admin credit review | List filters by shop/date/status → dedicated slip detail; rejection reason required | Credit ledger changes only after confirmed approval | Keep pending/pending-review status and show validation reason |

## Navigation and responsive behavior

- Document title: `{Page} — GamoryID`; never include secrets or customer PII.
- Direct forbidden routes render a Thai 403 page; unknown routes render 404; server failures provide retry.
- The authenticated root renders only a secure access gate until the session check completes; merchant/demo data must never mount before authentication succeeds.
- Desktop sidebar becomes bottom navigation below 768px; secondary routes move to the horizontally scrollable management navigation reached from `เพิ่มเติม`.
- Tables become compact semantic record rows on mobile; inventory details use a dedicated in-page view with a clear return action.
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
- Credentials are never persisted in browser storage; reveal requires server permission and recent re-authentication. The current product decision removes the 2FA gate from both Merchant and Super Admin flows.
- Discord interaction requests require Ed25519 signature and timestamp validation outside local/testing bypass. One-time setup/link values are stored only as hashes and expire after 10 minutes.
- Discord operational commands run only in the configured `คำสั่งทั่วไป` room, return ephemeral responses, and re-check the current ShopMember permissions on every interaction. Username, password, customer contact, cost, profit, and internal notes must not leak into public channels or notification payloads.
- Superseded requests are aborted; server data is invalidated after mutations.

## Validation and permissions

- Product forms use `noValidate`, Zod client validation, Laravel FormRequest server validation, first-invalid focus, and duplicate-submit prevention.
- Secret inputs are masked with accessible show/hide controls and `autocomplete` metadata.
- Irrelevant routes are hidden; visible-but-read-only actions are disabled with an accessible explanation; direct access receives 403.
- Clipboard copies never echo the copied value in a toast, log, URL, or analytics event.
- Customer-facing inventory copy excludes Username, Password, internal notes, and cost. The reusable footer is owned by the shop settings screen.

## Verification

- Static: Oxlint, TypeScript build, Vitest, PHPUnit, Pint, premium audit.
- Browser: 1440px desktop plus 390px mobile; keyboard, empty/no-result, dialog/select open, reduced motion, and failure states.
- Canonical comparison: dashboard and inventory share the same app shell, action labels, feedback, row state, and signal-rail treatment.
