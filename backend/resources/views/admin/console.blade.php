<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title }} — GamoryID</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $statusLabels = [
        'pending' => 'กำลังตรวจสลิป', 'pending_review' => 'รออนุมัติ', 'verified' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ', 'trialing' => 'ทดลองใช้', 'pending_payment' => 'รอชำระเงิน',
        'active' => 'ใช้งาน', 'expired' => 'หมดอายุ', 'grace_read_only' => 'อ่านอย่างเดียว',
        'suspended' => 'ระงับ', 'cancelled' => 'ยกเลิก', 'archived' => 'เก็บถาวร',
    ];
    $isShopPage = str_starts_with($page, 'shop');
    $isPlanPage = str_starts_with($page, 'plan');
    $isTopUpPage = str_starts_with($page, 'top-up');
    $pageDescriptions = [
        'dashboard' => 'ดูแลแพลตฟอร์ม เครดิต และเหตุการณ์สำคัญจากศูนย์กลาง',
        'shops' => 'ดูแพ็กเกจ สมาชิก เครดิต และสถานะของทุกร้าน',
        'shop-create' => 'สร้างร้านพร้อมบัญชีเจ้าของและช่วงทดลองใช้',
        'shop-show' => 'ข้อมูลสมาชิก ประวัติเครดิต และการสมัครแพ็กเกจ',
        'shop-edit' => 'แก้ไขข้อมูลติดต่อและสถานะการให้บริการ',
        'plans' => 'กำหนดราคา ระยะเวลา และขีดจำกัดบริการ',
        'plan-create' => 'สร้างตัวเลือกบริการใหม่สำหรับร้านค้า',
        'plan-edit' => 'แก้ไขราคา ขีดจำกัด และสถานะการเปิดขาย',
        'top-ups' => 'ตรวจสลิปและบันทึกเหตุผลก่อนเพิ่มเครดิตให้ร้านค้า',
        'top-up-show' => 'ตรวจข้อมูล สลิป และตัดสินใจรายการเติมเครดิต',
        'logs' => 'ติดตามเหตุการณ์สำคัญของระบบและผู้ดูแล',
        'profile' => 'จัดการบัญชีผู้ดูแลระบบของตัวเอง',
    ];
@endphp
<div class="shell">
    <aside class="sidebar">
        <div class="brand">Gamory<span>ID</span><small>SUPER ADMIN</small></div>
        <div class="nav-label">ระบบจัดการ</div>
        <nav class="nav" aria-label="เมนู Super Admin">
            <a class="{{ $page === 'dashboard' ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" @if($page === 'dashboard') aria-current="page" @endif>ภาพรวม</a>
            <a class="{{ $isShopPage ? 'active' : '' }}" href="{{ route('admin.shops.index') }}" @if($isShopPage) aria-current="page" @endif>จัดการร้านค้า</a>
            <a class="{{ $isPlanPage ? 'active' : '' }}" href="{{ route('admin.plans.index') }}" @if($isPlanPage) aria-current="page" @endif>จัดการแพ็กเกจ</a>
            <a class="{{ $isTopUpPage ? 'active' : '' }}" href="{{ route('admin.top-ups.index') }}" @if($isTopUpPage) aria-current="page" @endif>
                รายการเติมเครดิต
                @if($totals['pending_top_ups'])<i class="dot" aria-label="{{ $totals['pending_top_ups'] }} รายการรอตรวจ"></i>@endif
            </a>
            <a class="{{ $page === 'logs' ? 'active' : '' }}" href="{{ route('admin.logs.index') }}" @if($page === 'logs') aria-current="page" @endif>Log</a>
            <a class="{{ $page === 'profile' ? 'active' : '' }}" href="{{ route('admin.profile.edit') }}" @if($page === 'profile') aria-current="page" @endif>บัญชีของฉัน</a>
        </nav>
        <div class="sidebar-bottom">1 เครดิต = 1 บาท</div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div><strong>GamoryID Super Admin</strong><span>ศูนย์ควบคุมระบบ</span></div>
            <form method="post" action="{{ route('admin.logout') }}" novalidate>
                @csrf
                <button class="logout" type="submit">ออกจากระบบ</button>
            </form>
        </header>

        <main>
            <section class="intro">
                <div>
                    <span class="eyebrow">SYSTEM CONTROL</span>
                    <h1>{{ $title }}</h1>
                    <p>{{ $pageDescriptions[$page] ?? 'ดูแลระบบ GamoryID' }}</p>
                </div>
                <p class="muted">{{ now()->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} น.</p>
            </section>

            @if(session('message'))<div class="banner" role="status">{{ session('message') }}</div>@endif
            @if($errors->any())
                <div class="banner error" role="alert" tabindex="-1" data-error-summary>
                    <div><strong>บันทึกข้อมูลไม่สำเร็จ</strong><span>{{ $errors->first() }}</span></div>
                </div>
            @endif

            @include('admin.pages.'.$page)
        </main>
    </div>
</div>

<dialog class="admin-dialog" id="admin-confirm-dialog" aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-description">
    <div class="admin-dialog-head">
        <h2 id="admin-confirm-title">ยืนยันการทำรายการ</h2>
        <p id="admin-confirm-description" data-confirm-description>ตรวจสอบรายละเอียดก่อนยืนยันรายการนี้</p>
    </div>
    <div class="admin-dialog-actions">
        <button class="button secondary" type="button" data-confirm-cancel>ยกเลิก</button>
        <button class="button" type="button" data-confirm-submit>ยืนยัน</button>
    </div>
</dialog>
</body>
</html>
