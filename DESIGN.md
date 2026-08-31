---
version: alpha
name: "GamoryID"
description: "Friendly gaming operations console for Thai digital-inventory merchants"
colors:
  ink: "#071A33"
  ink-soft: "#17324F"
  primary: "#0B6BFF"
  signal: "#00C2FF"
  action: "#FF7900"
  success: "#16A765"
  warning: "#A96B00"
  danger: "#D93A45"
  reserved: "#7457EB"
  background: "#F7FAFF"
  surface: "#FFFFFF"
  border: "#D8E3F0"
  muted: "#64758B"
typography:
  display:
    fontFamily: "Noto Sans Thai, Leelawadee UI, Tahoma, sans-serif"
    fontSize: "2rem"
    lineHeight: "1.25"
  sans:
    fontFamily: "Noto Sans Thai, Leelawadee UI, Tahoma, sans-serif"
    fontSize: "1rem"
    lineHeight: "1.55"
  data:
    fontFamily: "Inter, Noto Sans Thai, Segoe UI, sans-serif"
    fontSize: "0.875rem"
    lineHeight: "1.45"
rounded:
  sm: "0.5rem"
  DEFAULT: "0.75rem"
  lg: "1rem"
  full: "999px"
spacing:
  control: "0.75rem"
  cluster: "1rem"
  section: "1.5rem"
  page: "2rem"
components:
  button: {}
  input: {}
  table: {}
  dialog: {}
  toast: {}
  sidebar: {}
---

# GamoryID Design System

## Overview

### Creative North Star

GamoryID feels like the control panel embedded in Gammy's illuminated inventory backpack: precise enough for repetitive stock operations, approachable enough for first-time merchants, and never styled like a dark cyberpunk game HUD.

### Product context and register

- **Audience and primary job:** Thai digital-inventory merchants importing, finding, reserving, and selling high volumes of inventory.
- **Target market and evidence:** Thailand; the approved product plan uses Thai copy, THB, Asia/Bangkok, bank-slip billing, and desktop-plus-mobile use.
- **Locale and language:** `th-TH` is the product locale. Use Gregorian dates, Thai labels, Arabic numerals, THB currency formatting, and `Asia/Bangkok` for operational timestamps.
- **Usage scene:** Desktop for Excel/CSV imports and dense tables; mobile for exact-tag search, reservation, selling, and secure copy.
- **Register:** Hybrid. Public routes are friendly brand surfaces; authenticated routes are task-first product surfaces.
- **Memorable signature:** A thin cyan signal rail links navigation selection, KPI context, search focus, and the active work region.
- **Restraint:** Tables, forms, credentials, permissions, and billing remain quiet and familiar. Mascot art appears only in brand, guidance, or state illustration roles.
- **Anti-references:** No neon-dark cyberpunk UI, bento-card dashboard, Riot/VALORANT art, glassmorphism, excessive pills, or decorative gaming HUD chrome.
- **Token ownership/runtime mapping:** This file is canonical. Tailwind v4 semantic adapters in `merchant/src/index.css` and `backend/resources/css/app.css` mirror these exact values; `merchant/src/styles/tokens.css` remains the Merchant primitive source and `public-web/src/index.css` mirrors the public surface. Shared component recipes consume semantic Tailwind/CSS variables, never screen-local palette values.

Visual references: `docs/design/dashboard-desktop-concept.png` and `docs/design/inventory-mobile-concept.png`.

## Colors

`ink` anchors the sidebar and primary text. `primary` owns links, focus, selected navigation, and information actions. `signal` is the thin connecting motif, not a general background. `action` is reserved for the single most important create/upgrade CTA. Success, warning, danger, and reserved colors retain semantic meaning and always include a textual or icon cue. The base canvas is the cool near-white `background`; working surfaces use true white.

## Typography

Noto Sans Thai is the preferred family, with native Thai-capable fallbacks. Display headings use strong weight sparingly. Body copy stays at 14–16px with comfortable Thai line height. Codes, tags, counts, prices, and table values use the data stack with tabular numerals. Buttons use direct Thai verbs and never browser-default typography.

## Layout

Desktop uses a 236px persistent navy sidebar, a quiet 72px top bar, and an open main canvas. Tables own their horizontal overflow. Mobile replaces the sidebar with a five-item bottom navigation and transforms each table row into a compact, border-separated inventory row. Breakpoints: compact below 768px, intermediate from 768–1099px, desktop at 1100px and above. No global `100vh` lock; the document scrolls naturally.

## Elevation & Depth

Hierarchy comes from surface tone, 1px borders, and the cyan signal rail. Static panels have no shadow. Menus, dialogs, mobile quick-action sheets, and the orange mobile add button may use restrained elevation. Backdrops use neutral navy transparency without blur-heavy glass effects.

## Shapes

Controls use 12px radii; panels use 16px only when they group a complete task. Status labels may use an 8px radius but should not become decorative pills. Icons use 1.75–2px rounded outline strokes. Table geometry remains mostly rectangular to preserve density.

## Components

### Foundational visual states

Hover darkens or lightly tints the current semantic color. Focus-visible uses a 3px primary ring with white offset. Selected rows use a pale blue fill and primary border. Disabled controls preserve readable labels and include an explanation where needed. Busy buttons keep their dimensions. Errors are inline and never rely on color alone.

### Buttons and actions

Buttons combine emphasis (`solid`, `outline`, `ghost`) and intent (`brand`, `neutral`, `success`, `warning`, `danger`). Orange solid is restricted to high-value creation/upgrade. Destructive actions stay separated and use danger only at confirmation.

Tailwind v4 is the runtime styling framework for Merchant and Super Admin. Merchant recipes live in `merchant/src/styles/tailwind-system.css`; Super Admin recipes live in `backend/resources/css/app.css`. These files are the canonical owners for control height, spacing, focus, hover, disabled, busy, table, dialog, alert, and responsive states.

### Navigation and data display

Sidebar items show one icon and one Thai label. Admin tables use semantic `<table>`, server-shaped pagination, sortable button headers, stable loading geometry, and URL-owned filters. Mobile inventory uses compact rows rather than stacked marketing cards.

### Forms and overlays

Inputs are 44px minimum height, use app-owned validation, and always have labels. Search includes an accessible clear control. Secrets remain masked. Dialogs and sheets trap focus only when modal and restore focus to their trigger. Toasts appear top-right on desktop and top-center on mobile.

Inventory media uses one 4:3 Display image followed by up to four detail images. Upload controls explain JPEG/PNG/WebP, 5 MB per-file, and count limits before selection; each selected file has a real preview and an accessible remove action. Product detail reserves the main media geometry, uses `object-fit: contain` for inspection, and uses cropped thumbnails only for navigation.

Inventory reminder notes are an internal team annotation: use `MessageSquareText`, a compact warm-tinted preview beneath the Riot ID, and one current note per item. Notes are editable from the list and detail view, never included in customer copy, and never written verbatim to audit metadata.

Bulk inventory import uses one canonical Excel workbook with machine-readable English headers on the first sheet and Thai guidance on the second sheet. The importer also accepts CSV with the same headers. Preview masks password values, reports the total row count, and confirms column mapping before the whole batch enters the queue.

### Iconography

Lucide is canonical at 18–20px with consistent rounded outline strokes. Text labels remain for non-universal operations. The Gammy mascot is a raster brand/state asset, never an icon substitute.

### Motion

Use 140–180ms ease-out transitions for focus, selection, drawer, and toast feedback. Exact-tag search may use one restrained cyan scan-line. Disable nonessential transitions with `prefers-reduced-motion`.

### Content and data visualization

Copy is conversational, concise, and action-led. Use `เพิ่มไอดี`, `จองไอดี`, `บันทึกขาย`, and `นำเข้าข้อมูล` consistently. Charts use primary, reserved, action, and success series with a textual summary and tooltip. Prices use `Intl.NumberFormat('th-TH', { currency: 'THB' })`.

## Do's and Don'ts

- **Do:** Keep the inventory table/list as the dominant working surface.
- **Do:** Use Gammy only where it helps orientation, trust, or recovery.
- **Do:** Preserve semantic color meanings and visible keyboard focus.
- **Don't:** wrap every metric, filter, and row in separate rounded cards.
- **Don't:** expose credentials in lists, URLs, logs, analytics, or notifications.
- **Don't:** use Riot/VALORANT logos, art, or implied endorsement.
