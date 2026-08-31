# GamoryID Merchant SPA

React + TypeScript backoffice สำหรับเจ้าของร้านและพนักงาน ใช้ React Router,
REST API ของ Laravel และ design tokens กลางของ GamoryID

## โครงสร้าง frontend

```text
src/
├─ app/                 # application entry และ route tree
├─ config/              # navigation และ route mapping
├─ features/            # UI และ flow แยกตาม domain
│  ├─ auth/
│  ├─ billing/
│  ├─ dashboard/
│  ├─ history/
│  ├─ imports/
│  ├─ inventory/
│  ├─ merchant/         # authenticated application orchestrator
│  ├─ settings/
│  ├─ team/
│  └─ transactions/
├─ shared/
│  ├─ hooks/            # behavior ที่ใช้ร่วมกัน
│  ├─ lib/              # pure utilities
│  └─ ui/               # primitive UI components
├─ styles/              # shared Tailwind component recipes
├─ test/
└─ types/               # API และ domain models กลาง
```

`src/App.tsx` เป็น public entry เท่านั้น ห้ามเพิ่ม page, API state หรือ business
logic ลงในไฟล์นี้ ฟีเจอร์ใหม่ควรอยู่ใต้ `features/<domain>` และนำของที่ใช้ซ้ำ
ตั้งแต่สอง domain ขึ้นไปไว้ใน `shared` เท่านั้น

## Routing

Route tree อยู่ที่ `src/app/router.tsx` และ path mapping สำหรับเมนูอยู่ที่
`src/config/navigation.tsx` การเปลี่ยนหน้าภายในระบบต้องใช้ React Router เพื่อให้
URL, browser history และ active navigation สอดคล้องกัน

## Theme และ CSS

ลำดับชั้น styling คือ `DESIGN.md` → CSS tokens ใน `src/index.css` → shared
recipes ใน `src/styles/tailwind-system.css` → feature stylesheet เฉพาะหน้า
Component ควรใช้ semantic class ที่มีอยู่ก่อนเพิ่มสี ระยะ หรือ shadow ใหม่

## คำสั่งพัฒนา

```powershell
npm run dev
npm run lint
npm run test:unit
npm run build
npm run format:check
```
