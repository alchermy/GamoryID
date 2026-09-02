# แผนพัฒนา GamoryID

> อัปเดตล่าสุด 1 กันยายน 2026 — ปรับให้ตรงกับโค้ดที่ลงจริงถึง commit `e2721a9`
> เอกสารนี้เป็น product plan หลักที่ `UX-CONTRACT.md` และ `DESIGN.md` อ้างอิง

## 1. เป้าหมายและขอบเขต

สร้าง SaaS ภาษาไทยสำหรับจัดการสต็อกไอดีเกม โดย MVP เน้นลดเวลานำเข้า ค้นหา จอง และบันทึกขายให้พ่อค้า

- กลุ่มแรก: ไม่เกิน 20 ร้าน รวมประมาณ 50,000 รายการ
- อุปกรณ์: งาน bulk บน Desktop; ค้นหา จอง ขาย และ copy บนมือถือได้
- MVP: Merchant Backoffice, ระบบสมาชิก/แพ็กเกจ, Minimal Super Admin และหน้า Landing/สมัครใช้งานแบบย่อ
- ยังไม่รวม: Public Storefront, Riot API/Login
- Trial 30 วัน → Read-only 14 วัน → ระงับการใช้งาน เหลือเฉพาะชำระเงินและ export ข้อมูล
- แพ็กเกจจำกัดด้วยจำนวน active inventory และจำนวนสมาชิก โดยราคา/limit แก้ไขได้จาก Super Admin

## 2. สถาปัตยกรรมและ Interface

- Backend: Laravel 12 + MySQL, queue/cache (Redis ใน production; database driver ใน dev), private storage
- Super Admin: Laravel Blade แยก guard (`EnsureSuperAdminSession`); 2FA มี infra ครบ (`Totp`, คำสั่ง `gamoryid:admin-2fa`) แต่ product decision ปัจจุบัน **ถอด 2FA gate ออกจาก flow** ทั้ง Merchant และ Super Admin
- Frontend: React + TypeScript
  - Merchant SPA (`merchant/`) — React Router, แยกเป็น `src/features/*` ตาม domain
  - Public React app (`public-web/`) — ปัจจุบันมีแค่ Landing Page
- REST API อยู่ใต้ `/api/v1` พร้อม OpenAPI spec (`backend/openapi.yaml`)
- ใช้ Laravel Sanctum แบบ secure HttpOnly cookie, CSRF protection, email verification, session/device management และ rate limiting
- Multi-tenant แบบ shared database ทุกข้อมูลร้านมี `shop_id`
  - บังคับ tenant ผ่าน middleware (`shop.writable`, `shop.permission:*`) + query scope `forShop()` + service `CurrentShop`
  - **ยังไม่มี Laravel Policy class** — authz อยู่ที่ middleware + scope; ควรพิจารณาเพิ่ม Policy ถ้าความซับซ้อนโต

Domain หลัก (Models จริง):

- `User`, `Shop`, `ShopMember`, `ShopInvitation` — สิทธิ์เก็บเป็น array บน `ShopMember` เทียบกับ enum `ShopPermission` (ไม่มี `Permission` model แยก)
- `InventoryItem`, `InventoryCredential`, `CustomFieldDefinition`, `InventoryMedia`, `ActivityLog`
- `Customer`, `Reservation`, `Sale`
- `ImportJob`, `ImportError`
- `SubscriptionPlan`, `Subscription`, `PaymentSubmission`, `SlipVerification`, `CreditTransaction`
- Discord: `DiscordInstallation`, `DiscordChannelBinding`, `DiscordUserLink`, `DiscordSetupCode`, `DiscordLinkCode`, `DiscordCommandLog`

สถานะสำคัญ:

- Inventory: `available`, `reserved`, `sold`, `archived`
- Subscription / Shop: `trialing`, `pending_payment`, `active`, `grace_read_only`, `suspended`, `cancelled`
- `ShopPermission`: `inventory.manage`, `inventory.sell`, `profit.view`, `data.export`, `credentials.reveal`, `team.manage`, `billing.manage`, `discord.manage`

### หมายเหตุการเปลี่ยนแปลงจากแผนเดิม

- **Billing เปลี่ยนเป็น Credit Wallet**: ร้านเติมเครดิตด้วยสลิป → SlipOK ตรวจ → Super Admin อนุมัติ → เครดิตเข้ากระเป๋า → ซื้อแพ็กเกจด้วยเครดิต (idempotency key) + auto-renew หักเครดิตอัตโนมัติ (`CreditWallet`, `SubscriptionLifecycle`) แทนการอัปสลิปแยกรายแพ็กเกจ
- Permission model รวมอยู่ใน `ShopMember` ไม่แยกตาราง

## 3. สถานะการพัฒนาเป็น Milestone

### Milestone 1 — Foundation และ UX — ✅ เสร็จ

- ✅ Design system จาก Gammy (`DESIGN.md` + Tailwind v4 adapters); UI ไทย responsive
- ✅ Auth, self-signup, onboarding สร้างร้าน, invite พนักงาน (`ShopInvitation` + `ShopInvitationNotification`), Owner/Staff + permission รายคน
- ✅ Minimal Super Admin: dashboard, shops (list/create/edit/show/archive/restore), plans CRUD, top-ups review, audit logs
- ✅ Session/device management + revocation, email verification
- ⚠️ 2FA gate ถูกถอดออกจาก flow ตาม product decision (infra ยังอยู่)

### Milestone 2 — Inventory Core — ✅ เสร็จ

- ✅ Core fields + TH identity fields, custom fields CRUD (text/number/boolean/date/select)
- ✅ `TagGenerator` สร้าง tag `#23DX5` พร้อม unique index + collision retry
- ✅ Exact-tag search + filter (สถานะ/ราคา/rank/region/custom fields)
- ✅ `InventoryMedia` (1 Display 4:3 + detail สูงสุด 4), signed URL, private object
- ✅ Soft delete, timeline (`InventoryTimelineController`), archive โดยไม่ทำลายประวัติ
- ✅ `InventoryCredential` ตารางแยก, AES-256-GCM (`CredentialCipher`), ไม่โผล่ใน API ปกติ
- ✅ Reveal credentials: ต้องมี permission + re-auth (`sensitive` middleware) + throttle + audit (ไม่บันทึกค่าลับ)
- ✅ Inventory reminder notes (ภายในทีม, ไม่เข้า customer copy / audit metadata)

### Milestone 3 — Import, Reservation และ Sales — ✅ เสร็จเป็นส่วนใหญ่

- ✅ เพิ่มรายการด้วยฟอร์ม และ Excel/CSV: upload → map → preview → validate → confirm
- ✅ ประมวลผลผ่าน queue (`ProcessInventoryImport`), error รายแถว (`ImportError`), template workbook, mask password ใน preview
- ✅ การจอง: ผู้จอง เวลาหมดอายุ หมายเหตุ ยกเลิกจองได้ (`ReservationController` store/release)
- ✅ การขาย: DB transaction + row lock กันขายซ้ำ (`SaleController::store`), บันทึกลูกค้า/ช่องทาง/ราคา/ต้นทุน/กำไร/ผู้ทำ/เวลา + warranty date + notes
- ✅ Sale detail route `/sales/{id}` (กำไรเห็นเฉพาะ `profit.view`)
- ✅ Dashboard: พร้อมขาย/จอง/ขาย, มูลค่าสต็อก, ยอดขาย, กำไร, รายการล่าสุด
- ⬜ **Reservation auto-expire**: มี field `expires_at` แต่ยังไม่มี scheduled job ปลดล็อกอัตโนมัติเมื่อหมดเวลา
- ⬜ Import: ควรยืนยัน test coverage ของ duplicate detection + rollback ทั้ง batch ให้ครบ (`InventoryImportTest`)

### Milestone 4 — Subscription และ Credit Wallet + SlipOK — ✅ เสร็จเป็นส่วนใหญ่ (เปลี่ยนโมเดล)

- ✅ ร้านเติมเครดิตด้วย JPEG/PNG ของสลิป (`CreditController::topUp`)
- ✅ `SlipVerifier` เรียก SlipOK ผ่าน HTTP + test bypass สำหรับ local/testing + fallback `pending_review` เมื่อยังไม่ตั้งค่า/API ล้ม
- ✅ ตรวจยอดเงิน บัญชีผู้รับ transaction reference และกันสลิปซ้ำ (`SlipVerification`); สลิปที่ผ่านอัตโนมัติยังเข้า `pending_review` ให้ Super Admin อนุมัติเสมอ (ไม่เชื่อ `success` ค่าเดียว)
- ✅ ซื้อแพ็กเกจด้วยเครดิต (idempotency key), auto-renew, admin review top-up (อนุมัติ/ปฏิเสธพร้อมเหตุผล + audit)
- ✅ `SubscriptionLifecycle` รันทุกชั่วโมง: active → grace 14 วัน → suspended, trial expiry, auto-renew หักเครดิต
- ⬜ **Email แจ้งเตือน**: ยังไม่มี Mail class สำหรับ trial ใกล้หมด / ตรวจสลิปสำเร็จ-ไม่สำเร็จ / เข้า grace / ถูกระงับ (มีแค่ `ShopInvitationNotification`)
- 🔗 [SlipOK API guidance](https://slipok.com/api-documentation/check-slip-quota/)

### Milestone 5 — Pilot Hardening — 🟡 ทำบางส่วน

- ✅ Export ข้อมูลร้านเป็น CSV (`ExportController`, permission `data.export`)
- ✅ Audit search (admin logs), session revocation, `AuditLogger` service
- ⬜ Masked customer data ใน export / มุมมองที่ไม่จำเป็น
- ⬜ Monitoring, error tracking, queue alerts
- ⬜ Encrypted backup + ทดสอบ restore จริง
- ⬜ Privacy notice, export/delete (คำขอลบข้อมูล) workflow, retention policy
- ⬜ เปิด pilot กลุ่มเล็ก + เก็บ metric เวลา import/search/sell

### Discord Phase 1 — ✅ เสร็จ (31 สิงหาคม 2026)

- ✅ เชื่อม Discord server ด้วยรหัสใช้ครั้งเดียว (hash + หมดอายุ 10 นาที), HTTP Interactions + Ed25519 signature verify (`DiscordSignatureVerifier`)
- ✅ สมาชิกเชื่อมบัญชี Discord ตนเองก่อนใช้คำสั่งในห้อง `คำสั่งทั่วไป`; รองรับ สรุป/รายการ/ค้นหา/จอง/ยกเลิกจอง/ปิดการขาย/โน้ต/เพิ่มไอดี/ช่วยเหลือ — ตอบเป็นข้อความส่วนตัว
- ✅ ตรวจสิทธิ์ ShopMember ล่าสุดทุกครั้ง (`inventory.manage` / `inventory.sell` / `profit.view`)
- ✅ เพิ่มผ่าน Discord ไม่รับ Username/Password; คำตอบ/แจ้งเตือนไม่ส่ง credentials/ต้นทุน/ข้อมูลติดต่อลูกค้า/โน้ตภายใน
- ✅ Notification queue (`SendDiscordShopNotification`, `DiscordNotificationMessageBuilder`) สำหรับ เพิ่ม/จอง/ยกเลิก/ขาย + โหมดจำลอง local
- ✅ Production ใช้ HTTP Interactions ไม่ต้องเปิด Gateway bot ค้าง (`docs/discord-setup.md`)

## 4. เฟสถัดไป (ยังไม่เริ่ม)

1. **Discord Phase 2**: แจ้งเตือนความสนใจ + workflow จาก Public Storefront หลังผ่าน legal/business-risk review
2. **Public Storefront**: แสดงเฉพาะข้อมูลที่อนุญาต, CTA Facebook/LINE/โทรศัพท์, เก็บ unique view/click/lead
3. **Super Admin เต็มรูปแบบ** และ Landing Page สำหรับทำตลาด

## 5. Backlog ที่ควรทำต่อ (เรียงตามความสำคัญ)

### P0 — ปิดช่องโหว่ก่อน pilot

1. **Reservation auto-expire job** — scheduled sweeper ปลด `reserved` → `available` เมื่อ `expires_at` ผ่าน + append timeline/notification; ปัจจุบันของค้างจองถาวรถ้าพนักงานลืมยกเลิก
2. **Email lifecycle notifications** (M4) — trial ใกล้หมด (เช่น 7/3/1 วัน), เข้า grace, ถูกระงับ, ผลตรวจสลิป; ผูกเข้า `SubscriptionLifecycle` + จุด review top-up
3. **ยืนยัน concurrency + rollback tests** — ทดสอบ "พนักงาน 2 คนขายรายการเดียวกันพร้อมกัน สำเร็จรายการเดียว" และ CSV 1,000 แถว rollback ทั้ง batch ให้เป็น automated test ตามเกณฑ์ §6

### P1 — ความถูกต้องและความเชื่อมั่น

4. **openapi.yaml sync** — spec มี ~257 บรรทัดแต่ routes จริง ~50 endpoint; อัปเดตให้ครบ (billing, discord, sales, reservation, import, export) แล้วพิจารณา generate TS client
5. **Data export/delete + retention** (M5) — privacy notice, คำขอลบข้อมูลลูกค้า, retention policy, mask PII ใน export
6. **Monitoring/alerting** (M5) — error tracking, queue failure alert, backup + ทดสอบ restore

### P2 — คุณภาพระยะยาว

7. **E2E tests (Playwright)** — ยังไม่มีเลย ทั้งที่ §6 กำหนดที่ 375/768/1440 px สำหรับ flow import/search/sell และ tenant isolation
8. **พิจารณา Laravel Policy classes** — ถ้า authz rule เริ่มซับซ้อนเกิน middleware + scope
9. **Reservation/Sale จาก Discord — cover edge cases** ใน `DiscordIntegrationTest` เพิ่มเติม (สิทธิ์ถูกถอนกลางคัน, ห้องผิด)

## 6. Test Plan และเกณฑ์รับงาน

- Tenant A ต้องไม่อ่าน แก้ไข ค้นหา หรือ export ข้อมูล Tenant B ได้ทุกกรณี
- CSV 1,000 แถวต้อง preview, รายงาน error และ rollback ได้โดยไม่มีข้อมูลค้าง
- Exact tag search มี P95 ต่ำกว่า 300 ms บนชุดทดสอบ 50,000 รายการ
- เมื่อพนักงานสองคนขายรายการเดียวกันพร้อมกัน ต้องสำเร็จเพียงรายการเดียว
- ผู้ไม่มี permission หรือ re-auth หมดอายุ ต้อง reveal credentials ไม่ได้
- ทดสอบ SlipOK ครบทั้งสลิปถูกต้อง, ยอดผิด, ผู้รับผิด, สลิปซ้ำ, API timeout และ manual review
- ทดสอบ trial → grace read-only → suspended และการเปิดสิทธิ์หลังชำระเงิน
- automated tests: Pest/PHPUnit (มี `backend/tests/Feature/*` 8 ไฟล์), Vitest/RTL (มี `merchant/src/test/*` 6 ไฟล์), Playwright ที่ 375/768/1440 px (ยังไม่มี)
- Pilot สำเร็จเมื่อร้านย้ายข้อมูลจาก spreadsheet ได้ ค้นหาด้วย tag ภายในไม่กี่วินาที และไม่มีเหตุขายรายการซ้ำ

## 7. ความปลอดภัยและข้อจำกัดธุรกิจ

- เข้ารหัส credentials ด้วย authenticated encryption + versioned key ที่เก็บนอกฐานข้อมูล (`CREDENTIAL_ENCRYPTION_KEY_V1`)
- ผู้มีสิทธิ์ดู credentials ต้อง re-auth ก่อน reveal/copy (2FA gate ถูกถอดตาม product decision ปัจจุบัน)
- บันทึกผู้ใช้ เวลา IP อุปกรณ์ และรายการที่ reveal โดยไม่บันทึกค่าความลับ
- ห้ามส่ง credentials ไปยัง analytics, logs, error tracker หรือ notification (รวมถึง Discord)
- ไฟล์ CSV, รูปสินค้า และสลิปเป็น private object; เข้าถึงผ่าน signed URL และลบไฟล์ import ชั่วคราวหลังประมวลผล
- ใช้ data minimization, privacy notice, export/delete workflow และ retention policy ก่อน production (ดู Backlog P1 #5)

Riot ระบุว่าการขายหรือโอนบัญชีขัดข้อกำหนด แม้ GamoryID จะไม่ใช้ Riot API หรือ Riot Login ก็ตาม ([Riot Terms](https://www.riotgames.com/en/terms-of-service-update-2024), [Riot Community Pact](https://www.riotgames.com/en/community-pact)). ดังนั้น:

- ห้ามใช้ Riot/VALORANT logo, artwork หรือข้อความที่สื่อว่าได้รับการรับรอง
- ไม่เชื่อม Riot API/RSO และไม่ทำ account verification อัตโนมัติ
- Public Storefront, Discord marketing และการเปิดบริการวงกว้างต้องผ่าน legal/business-risk review ก่อน
- โครงสร้างข้อมูลต้องไม่ผูกกับ Valorant จนขยายไปสินค้าดิจิทัลที่โอนได้อย่างถูกต้องไม่ได้
