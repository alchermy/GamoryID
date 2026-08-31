# GamoryID

SaaS ภาษาไทยสำหรับจัดการสต็อกสินค้าดิจิทัล การจอง ลูกค้า และการขาย โดยแยกข้อมูลลับออกจาก API ปกติและรองรับ multi-tenant ทุกตารางระดับร้าน

## โครงสร้าง

- `backend/` Laravel 12 REST API + Super Admin Blade
- `merchant/` React + TypeScript Merchant Backoffice
- `public-web/` React Landing Page
- `backend/openapi.yaml` API contract
- `DESIGN.md` และ `UX-CONTRACT.md` ข้อตกลงด้านภาพและพฤติกรรม

Frontend ทั้งสองส่วนแยก application entry, routes, domain features, shared UI,
utilities และ types ออกจากกันแล้ว ดูแนวทางเพิ่มฟีเจอร์ Merchant ได้ที่
`merchant/README.md`

## เริ่มต้นบน XAMPP (Windows)

1. เปิด Apache และ MySQL จาก XAMPP Control Panel
2. สร้างฐานข้อมูลชื่อ `gamoryid` แบบ `utf8mb4_unicode_ci` (ฐานข้อมูลในเครื่องนี้สร้างไว้แล้ว)
3. ตรวจ `backend/.env`: MySQL `127.0.0.1:3306`, database `gamoryid`, user `root`, password ว่าง
4. รันคำสั่งต่อไปนี้คนละ Terminal

```powershell
cd C:\gamoryid\backend
php artisan migrate --seed
php artisan serve
```

```powershell
cd C:\gamoryid\backend
php artisan queue:work
```

```powershell
cd C:\gamoryid\merchant
npm run dev
```

```powershell
cd C:\gamoryid\public-web
npm run dev -- --port 5174
```

Merchant demo: `http://localhost:5173` · API: `http://localhost:8000` · Landing: `http://localhost:5174` · Super Admin: `http://localhost:8000/admin/login`

บัญชี seed สำหรับ API คือ `owner@gamoryid.local` / `password` ใช้เพื่อการพัฒนาเท่านั้น ข้อมูล credentials ใช้ AES-256-GCM ใน XAMPP; ก่อน production ต้องตั้ง `CREDENTIAL_ENCRYPTION_KEY_V1` เป็นคีย์สุ่ม 32 bytes แบบ base64 และห้ามเก็บร่วมฐานข้อมูล

Super Admin สำหรับการพัฒนาใช้บัญชี `admin@gamoryid.local` / `password`

## ตรวจสอบ

```powershell
cd C:\gamoryid\backend; php artisan test
cd C:\gamoryid\merchant; npm test; npm run build
cd C:\gamoryid\public-web; npm run build
```
