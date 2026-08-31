<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>เข้าสู่ระบบ Super Admin — GamoryID</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="admin-login-page">
    <section class="admin-login-shell" aria-labelledby="login-title">
        <form class="admin-login-form" method="post" action="{{ route('admin.login') }}" novalidate>
            @csrf
            <div class="admin-login-brand">Gamory<span>ID</span><small>SUPER ADMIN</small></div>
            <h1 id="login-title">เข้าสู่ศูนย์ควบคุม</h1>
            <p>จัดการร้านค้า แพ็กเกจ เครดิต และบันทึกระบบ</p>
            @error('email')<div class="banner error" role="alert">{{ $message }}</div>@enderror
            <label class="admin-field" for="admin-email">อีเมล
                <input class="admin-input" id="admin-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus>
            </label>
            <label class="admin-field" for="admin-password">รหัสผ่าน
                <span class="admin-password">
                    <input class="admin-input" id="admin-password" name="password" type="password" required autocomplete="current-password">
                    <button class="password-toggle" type="button" data-password-toggle="admin-password" aria-label="แสดงรหัสผ่าน" aria-pressed="false">แสดง</button>
                </span>
            </label>
            <button class="button admin-login-submit" type="submit">เข้าสู่ระบบ</button>
        </form>
        <aside class="admin-login-art" aria-label="ข้อมูลระบบ">
            <div>
                <strong>ทุกการตัดสินใจสำคัญ<br>อยู่ในที่เดียว</strong>
                <p>ตรวจเครดิต ดูสุขภาพร้านค้า และติดตามเหตุการณ์ของระบบด้วยข้อมูลที่อ่านง่าย</p>
            </div>
        </aside>
    </section>
</main>
</body>
</html>
