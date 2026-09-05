<section class="form-card" aria-labelledby="profile-title">
    <div class="card-head"><div><h2 id="profile-title">บัญชีของฉัน</h2><p>{{ $admin->name }} · {{ $admin->email }}</p></div></div>
    <form class="stack-form" method="post" action="{{ route('admin.profile.password') }}" novalidate>
        @csrf
        @method('PATCH')
        <div class="field-grid">
            <label class="field" for="current-password">รหัสผ่านปัจจุบัน<input id="current-password" name="current_password" type="password" required autocomplete="current-password">@error('current_password')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="new-password">รหัสผ่านใหม่<input id="new-password" name="password" type="password" required minlength="10" autocomplete="new-password">@error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="new-password-confirm">ยืนยันรหัสผ่านใหม่<input id="new-password-confirm" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password"></label>
        </div>
        <p class="muted">อย่างน้อย 10 ตัวอักษร</p>
        <div class="form-actions"><button class="button" type="submit">เปลี่ยนรหัสผ่าน</button></div>
    </form>
</section>
