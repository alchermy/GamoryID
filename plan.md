# แผนพัฒนา GamoryID

## 1. เป้าหมายและขอบเขต

สร้าง SaaS ภาษาไทยสำหรับจัดการสต็อกไอดีเกม โดย MVP เน้นลดเวลานำเข้า ค้นหา จอง และบันทึกขายให้พ่อค้า

- กลุ่มแรก: ไม่เกิน 20 ร้าน รวมประมาณ 50,000 รายการ
- อุปกรณ์: งาน bulk บน Desktop; ค้นหา จอง ขาย และ copy บนมือถือได้
- MVP: Merchant Backoffice, ระบบสมาชิก/แพ็กเกจ, Minimal Super Admin และหน้า Landing/สมัครใช้งานแบบย่อ
- ยังไม่รวม: Public Storefront, Riot API/Login และธุรกรรมผ่าน Discord
- Trial 30 วัน → Read-only 14 วัน → ระงับการใช้งาน เหลือเฉพาะชำระเงินและ export ข้อมูล
- แพ็กเกจจำกัดด้วยจำนวน active inventory และจำนวนสมาชิก โดยราคา/limit แก้ไขได้จาก Super Admin

## 2. สถาปัตยกรรมและ Interface

- Backend: Laravel + MySQL, Redis queue/cache และ private S3-compatible storage
- Super Admin: Laravel Blade แยก guard และบังคับ 2FA
- Frontend: React + TypeScript
  - Merchant SPA
  - Public React app สำหรับ Landing และ Public Display ในอนาคต
- REST API อยู่ใต้ `/api/v1` พร้อม OpenAPI specification และ generated TypeScript client
- ใช้ Laravel Sanctum แบบ secure HttpOnly cookie, CSRF protection, email verification, session/device management และ rate limiting
- Multi-tenant แบบ shared database ทุกข้อมูลร้านมี `shop_id`, ใช้ Laravel Policies/Scopes และทดสอบ tenant isolation ทุก endpoint

Domain หลัก:

- `User`, `Shop`, `ShopMember`, `Permission`
- `InventoryItem`, `InventoryCredential`, `CustomFieldDefinition`, `InventoryMedia`
- `Customer`, `Reservation`, `Sale`, `ActivityLog`
- `ImportJob`, `ImportError`
- `SubscriptionPlan`, `Subscription`, `PaymentSubmission`, `SlipVerification`

สถานะสำคัญ:

- Inventory: `available`, `reserved`, `sold`, `archived`
- Subscription: `trialing`, `pending_payment`, `active`, `grace_read_only`, `suspended`, `cancelled`
- Permissions: จัดการสต็อก, จอง/ขาย, ดูต้นทุนกำไร, export, ดู credentials และจัดการทีม

## 3. การพัฒนาเป็น Milestone

### Milestone 1 — Foundation และ UX

- วาง design system จาก Gammy: น้ำเงินเข้ม/ฟ้าเป็นสีหลัก ส้มเป็น CTA และใช้ mascot ตามสถานะค้นหา สำเร็จ ความปลอดภัย และ error
- UI ภาษาไทย ใช้งานง่าย เป็นมิตร และ responsive
- สร้าง Auth, self-signup, onboarding สร้างร้าน, invite พนักงาน, Owner/Staff และ permission รายคน
- สร้าง Minimal Super Admin สำหรับดูร้าน ระงับ/เปิดร้าน แพ็กเกจ การชำระเงิน และ audit

### Milestone 2 — Inventory Core

- Core fields: tag, region, rank, skin summary, รายละเอียด, ต้นทุน, ราคาตั้งขาย, credentials และหมายเหตุ
- ร้านเพิ่ม custom fields แบบ text, number, boolean, date และ select ได้
- สร้าง tag อัตโนมัติรูปแบบ `#23DX5` จากอักษร/ตัวเลขที่ไม่สับสน พร้อม unique index และ collision retry
- ค้นหา exact tag ได้ทันที รวมถึง filter ตามสถานะ ราคา rank region และ custom fields
- รองรับรูปภาพสินค้า, soft delete, activity timeline และ archive โดยไม่ทำลายประวัติ
- Credentials อยู่ในตารางแยกและไม่ปรากฏใน API response ปกติ

### Milestone 3 — Import, Reservation และ Sales

- เพิ่มรายการด้วยฟอร์มและ CSV แบบ upload → map columns → preview → validate → confirm
- ประมวลผล CSV ผ่าน queue พร้อม progress, error รายแถว, duplicate detection และ rollback ทั้ง import batch
- การจองมีผู้จอง เวลาหมดอายุ หมายเหตุ และยกเลิกจองได้
- การขายทำเป็น database transaction เพื่อป้องกันพนักงานขายรายการเดียวกันพร้อมกัน
- บันทึกลูกค้า ช่องทางติดต่อ ราคาขาย ต้นทุน กำไร ผู้ทำรายการ และเวลา
- Dashboard แสดงจำนวนพร้อมขาย/จอง/ขาย, มูลค่าสต็อก, ยอดขาย, กำไร และรายการเคลื่อนไหวล่าสุด

### Milestone 4 — Subscription และ SlipOK

- ร้านเลือกแพ็กเกจและอัปโหลด JPEG/PNG ของสลิป
- Laravel ส่งตรวจ SlipOK ผ่าน service adapter และ queue
- ตรวจจำนวนเงิน บัญชีผู้รับ transaction reference เวลาทำรายการ และป้องกันสลิปซ้ำก่อนเปิดสิทธิ์
- รายการไม่ตรงหรือ API ล้มเหลวเข้าสู่ `pending_review` ให้ Super Admin อนุมัติหรือปฏิเสธแบบมี audit
- SlipOK เองแนะนำให้ระบบตรวจยอด ผู้รับ และเก็บข้อมูลเพื่อป้องกันสลิปซ้ำ จึงต้องไม่เชื่อผล `success` เพียงค่าเดียว ([SlipOK API guidance](https://slipok.com/api-documentation/check-slip-quota/))
- ส่งอีเมลแจ้ง trial ใกล้หมด, ตรวจสลิปสำเร็จ/ไม่สำเร็จ, เข้า grace period และถูกระงับ

### Milestone 5 — Pilot Hardening

- เพิ่ม export ข้อมูลร้าน, masked customer data, audit search และ session revocation
- ตั้ง monitoring, error tracking, queue alerts, encrypted backup และทดสอบ restore จริง
- เปิด pilot เป็นกลุ่มเล็ก เก็บเวลาที่ใช้ import/search/sell และปรับ UX ก่อนเริ่มเฟสถัดไป

### Discord Phase 1 — ดำเนินการแล้ว (อัปเดต 31 สิงหาคม 2026)

- ร้านเชื่อม Discord server ด้วยรหัสใช้ครั้งเดียวและกำหนดห้องแจ้งเตือนแยกตามระบบ การขาย การจอง และคลังไอดี
- สมาชิกเชื่อมบัญชี Discord ของตนเองก่อนใช้คำสั่งในห้อง `คำสั่งทั่วไป`; รองรับสรุป รายการ ค้นหา จอง ยกเลิกจอง ปิดการขาย โน้ต เพิ่มไอดี และช่วยเหลือ โดยคำตอบเป็นข้อความส่วนตัว
- คำสั่งตรวจสิทธิ์ ShopMember ล่าสุดทุกครั้ง: `inventory.manage` สำหรับเพิ่ม/รายการ/โน้ต, `inventory.sell` สำหรับจอง/ยกเลิก/ขายพร้อมรายการ/โน้ต และ `profit.view` สำหรับกำไรในสรุป เจ้าของร้านใช้ได้ทั้งหมด
- การเพิ่มผ่าน Discord ไม่รับ Username หรือ Password และคำตอบ/แจ้งเตือนไม่ส่งข้อมูลเข้าสู่ระบบ ต้นทุน ข้อมูลติดต่อลูกค้า หรือโน้ตภายใน
- เพิ่ม notification queue สำหรับเหตุการณ์เพิ่มไอดี จอง ยกเลิกจอง และขาย พร้อมโหมดจำลองสำหรับ local/testing
- Production ใช้ HTTP Interactions จึงไม่ต้องเปิด Gateway bot ค้างไว้ แต่ต้องมี HTTPS, outbound HTTPS, PHP CLI และ cron/queue worker

### เฟสถัดไป

1. Discord Phase 2: เพิ่มแจ้งเตือนความสนใจและ workflow จาก Public Storefront หลังผ่าน legal/business-risk review
2. Public Storefront: แสดงเฉพาะข้อมูลที่อนุญาต, CTA Facebook/LINE/โทรศัพท์ และเก็บ unique view/click/lead
3. Super Admin เต็มรูปแบบและ Landing Page สำหรับทำตลาด

## 4. ความปลอดภัยและข้อจำกัดธุรกิจ

- เข้ารหัส credentials ด้วย authenticated encryption และ versioned key ที่เก็บนอกฐานข้อมูล
- ผู้มีสิทธิ์ดู credentials ต้องเปิด 2FA และ re-auth ก่อน reveal/copy ทุกครั้ง
- บันทึกผู้ใช้ เวลา IP อุปกรณ์ และรายการที่ reveal โดยไม่บันทึกค่าความลับ
- ห้ามส่ง credentials ไปยัง analytics, logs, error tracker หรือ notification
- ไฟล์ CSV, รูปสินค้า และสลิปเป็น private object; เข้าถึงผ่าน signed URL และลบไฟล์ import ชั่วคราวหลังประมวลผล
- ใช้ data minimization, privacy notice, export/delete workflow และกำหนด retention policy ก่อน production

Riot ระบุว่าการขายหรือโอนบัญชีขัดข้อกำหนด แม้ GamoryID จะไม่ใช้ Riot API หรือ Riot Login ก็ตาม ([Riot Terms](https://www.riotgames.com/en/terms-of-service-update-2024), [Riot Community Pact](https://www.riotgames.com/en/community-pact)). ดังนั้น:

- ห้ามใช้ Riot/VALORANT logo, artwork หรือข้อความที่สื่อว่าได้รับการรับรอง
- ไม่เชื่อม Riot API/RSO และไม่ทำ account verification อัตโนมัติ
- Public Storefront, Discord marketing และการเปิดบริการวงกว้างต้องผ่าน legal/business-risk review ก่อน
- โครงสร้างข้อมูลต้องไม่ผูกกับ Valorant จนขยายไปสินค้าดิจิทัลที่โอนได้อย่างถูกต้องไม่ได้

## 5. Test Plan และเกณฑ์รับงาน

- Tenant A ต้องไม่อ่าน แก้ไข ค้นหา หรือ export ข้อมูล Tenant B ได้ทุกกรณี
- CSV 1,000 แถวต้อง preview, รายงาน error และ rollback ได้โดยไม่มีข้อมูลค้าง
- Exact tag search มี P95 ต่ำกว่า 300 ms บนชุดทดสอบ 50,000 รายการ
- เมื่อพนักงานสองคนขายรายการเดียวกันพร้อมกัน ต้องสำเร็จเพียงรายการเดียว
- ผู้ไม่มี permission, ไม่มี 2FA หรือ re-auth หมดอายุ ต้อง reveal credentials ไม่ได้
- ทดสอบ SlipOK ครบทั้งสลิปถูกต้อง, ยอดผิด, ผู้รับผิด, สลิปซ้ำ, API timeout และ manual review
- ทดสอบ trial → grace read-only → suspended และการเปิดสิทธิ์หลังชำระเงิน
- ทำ automated tests ด้วย Pest/PHPUnit, React Testing Library/Vitest และ Playwright ที่ขนาดหน้าจอ 375, 768 และ 1440 px
- Pilot ถือว่าสำเร็จเมื่อร้านย้ายข้อมูลจาก spreadsheet ได้ ค้นหาด้วย tag ภายในไม่กี่วินาที และไม่มีเหตุขายรายการซ้ำ

สมมติฐาน: พัฒนาโดยคนเดียว จึงใช้ managed infrastructure, ทำทีละ milestone และเลื่อน Discord/Public Storefront ออกจาก MVP; ขั้นตอนถัดไปคือจัดทำ PRD และ wireflow ของ Merchant Backoffice ตาม Milestone 1–3 ก่อนเริ่มเขียนโค้ด
