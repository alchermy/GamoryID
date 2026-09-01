# GamoryID

SaaS ภาษาไทยสำหรับจัดการสต็อกสินค้าดิจิทัล การจอง ลูกค้า และการขาย โดยแยกข้อมูลลับออกจาก API ปกติและรองรับ multi-tenant ทุกตารางระดับร้าน

## โครงสร้าง

- `backend/` Laravel 12 REST API + Super Admin Blade
- `merchant/` React + TypeScript Merchant Backoffice
- `public-web/` React Landing Page
- `backend/openapi.yaml` API contract
- `DESIGN.md` และ `UX-CONTRACT.md` ข้อตกลงด้านภาพและพฤติกรรม
- `docs/discord-setup.md` คู่มือตั้งค่า Gamory Bot และ queue สำหรับ Production

Frontend ทั้งสองส่วนแยก application entry, routes, domain features, shared UI,
utilities และ types ออกจากกันแล้ว ดูแนวทางเพิ่มฟีเจอร์ Merchant ได้ที่
`merchant/README.md`

## เริ่มต้นบน XAMPP (Windows)

1. เปิด Apache และ MySQL จาก XAMPP Control Panel
2. สร้างฐานข้อมูลสำหรับพัฒนา เช่น `gamoryid_dev` แบบ `utf8mb4_unicode_ci`
3. ตรวจ `backend/.env`: MySQL `127.0.0.1:3306`, ชื่อฐานข้อมูลให้ตรงกับข้อ 2, user `root`, password ว่าง
4. รันคำสั่งต่อไปนี้คนละ Terminal

```powershell
cd C:\gamoryid\backend
php artisan migrate --seed
php artisan serve
```

```powershell
cd C:\gamoryid\backend
php artisan queue:work --queue=notifications,default
```

```powershell
cd C:\gamoryid\merchant
npm run dev
```

```powershell
cd C:\gamoryid\public-web
npm run dev -- --port 5174
```

Merchant: `http://localhost:5173` · สมัครร้าน: `http://localhost:5173/register` · API: `http://localhost:8000` · Landing: `http://localhost:5174` · Super Admin: `http://localhost:8000/admin/login`

`php artisan migrate:fresh --seed` จะสร้างฐานสะอาดที่มีเฉพาะแพ็กเกจและ Super Admin เหมาะสำหรับทดสอบ Flow สมัครร้านใหม่ หากต้องการข้อมูลตัวอย่างภายหลังให้รัน `php artisan db:seed --class=DemoShopSeeder`

การทดสอบยืนยันอีเมลบนเครื่องใช้ `MAIL_MAILER=log`: เปิด `backend/storage/logs/laravel.log` แล้วค้นหา `/email/verify/` จากอีเมลล่าสุด จากนั้นเปิดลิงก์ดังกล่าวใน Browser

ข้อมูล credentials ใช้ AES-256-GCM ใน XAMPP; ก่อน production ต้องตั้ง `CREDENTIAL_ENCRYPTION_KEY_V1` เป็นคีย์สุ่ม 32 bytes แบบ base64 และห้ามเก็บร่วมฐานข้อมูล

Super Admin สำหรับการพัฒนาใช้บัญชี `admin@gamoryid.local` / `password`

## ตรวจสอบ

```powershell
cd C:\gamoryid\backend; php artisan test
cd C:\gamoryid\merchant; npm test; npm run build
cd C:\gamoryid\public-web; npm run build
```
