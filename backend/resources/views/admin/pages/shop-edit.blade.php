<div class="back-row"><a class="back-link" href="{{ route('admin.shops.show', $shop) }}">← กลับไปข้อมูลร้าน</a></div>
<section class="form-card" aria-labelledby="shop-edit-title">
    <div class="card-head"><div><h2 id="shop-edit-title">ข้อมูลร้านค้า</h2><p>ยอดเครดิตแก้ไขจากหน้านี้ไม่ได้ เพื่อให้ ledger ทางการเงินตรงเสมอ</p></div></div>
    <form class="stack-form" method="post" action="{{ route('admin.shops.update', $shop) }}" novalidate>
        @csrf @method('PATCH')
        <div class="field-grid">
            <label class="field" for="shop-name">ชื่อร้านค้า<input id="shop-name" name="name" value="{{ old('name', $shop->name) }}" required maxlength="120" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="shop-slug">Slug ร้านค้า<input id="shop-slug" name="slug" value="{{ old('slug', $shop->slug) }}" required maxlength="120" aria-invalid="{{ $errors->has('slug') ? 'true' : 'false' }}">@error('slug')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="shop-status">สถานะ<select id="shop-status" name="status" required>
                @foreach(['trialing' => 'ทดลองใช้', 'pending_payment' => 'รอชำระเงิน', 'active' => 'ใช้งาน', 'grace_read_only' => 'อ่านอย่างเดียว', 'suspended' => 'ระงับ', 'cancelled' => 'ยกเลิก'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $shop->status) === $value)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label class="field field-wide" for="shop-description">รายละเอียดร้าน<textarea id="shop-description" name="description" rows="4" maxlength="2000">{{ old('description', $shop->description) }}</textarea></label>
            <label class="field" for="shop-facebook">Facebook URL<input id="shop-facebook" name="facebook_url" type="url" value="{{ old('facebook_url', $shop->facebook_url) }}" placeholder="https://facebook.com/..."></label>
            <label class="field" for="shop-line">LINE URL<input id="shop-line" name="line_url" type="url" value="{{ old('line_url', $shop->line_url) }}" placeholder="https://line.me/..."></label>
            <label class="field" for="shop-phone">เบอร์โทร<input id="shop-phone" name="phone" value="{{ old('phone', $shop->phone) }}" maxlength="32" autocomplete="tel"></label>
        </div>
        <div class="form-actions"><a class="button secondary" href="{{ route('admin.shops.show', $shop) }}">ยกเลิก</a><button class="button" type="submit">บันทึกการแก้ไข</button></div>
    </form>
</section>
