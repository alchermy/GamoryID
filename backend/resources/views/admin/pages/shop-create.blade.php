<div class="back-row"><a class="back-link" href="{{ route('admin.shops.index') }}">← กลับไปรายการร้านค้า</a></div>
<section class="form-card" aria-labelledby="shop-create-title">
    <div class="card-head"><div><h2 id="shop-create-title">ข้อมูลร้านและเจ้าของ</h2><p>ระบบจะสร้าง Trial และยืนยันอีเมลของบัญชีที่ Super Admin สร้างให้</p></div></div>
    <form class="stack-form" method="post" action="{{ route('admin.shops.store') }}" novalidate>
        @csrf
        <fieldset class="form-section">
            <legend>ข้อมูลร้านค้า</legend>
            <div class="field-grid">
                <label class="field" for="shop-name">ชื่อร้านค้า<input id="shop-name" name="name" value="{{ old('name') }}" required maxlength="120" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field" for="shop-slug">Slug ร้านค้า<input id="shop-slug" name="slug" value="{{ old('slug') }}" required maxlength="120" placeholder="เช่น nexus-store" aria-invalid="{{ $errors->has('slug') ? 'true' : 'false' }}">@error('slug')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field" for="trial-days">ทดลองใช้ (วัน)<input id="trial-days" name="trial_days" type="number" min="1" max="365" value="{{ old('trial_days', 30) }}" required aria-invalid="{{ $errors->has('trial_days') ? 'true' : 'false' }}">@error('trial_days')<span class="field-error">{{ $message }}</span>@enderror</label>
            </div>
        </fieldset>
        <fieldset class="form-section">
            <legend>บัญชีเจ้าของร้าน</legend>
            <div class="field-grid">
                <label class="field" for="owner-name">ชื่อเจ้าของร้าน<input id="owner-name" name="owner_name" value="{{ old('owner_name') }}" required maxlength="120" autocomplete="name" aria-invalid="{{ $errors->has('owner_name') ? 'true' : 'false' }}">@error('owner_name')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field" for="owner-email">อีเมลเข้าสู่ระบบ<input id="owner-email" name="owner_email" type="email" value="{{ old('owner_email') }}" required maxlength="190" autocomplete="email" aria-invalid="{{ $errors->has('owner_email') ? 'true' : 'false' }}">@error('owner_email')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field" for="owner-password">รหัสผ่านชั่วคราว<input id="owner-password" name="password" type="password" required minlength="10" autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"><button class="field-reveal" type="button" data-password-toggle="owner-password" aria-label="แสดงรหัสผ่าน" aria-pressed="false">แสดง</button>@error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field" for="owner-password-confirmation">ยืนยันรหัสผ่าน<input id="owner-password-confirmation" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password"><button class="field-reveal" type="button" data-password-toggle="owner-password-confirmation" aria-label="แสดงรหัสผ่านยืนยัน" aria-pressed="false">แสดง</button></label>
            </div>
        </fieldset>
        <div class="form-actions"><a class="button secondary" href="{{ route('admin.shops.index') }}">ยกเลิก</a><button class="button action" type="submit">สร้างร้านค้า</button></div>
    </form>
</section>
