@php($featureLabels = [
    'bulk_import' => 'นำเข้า Excel/CSV แบบชุด',
    'advanced_export' => 'ส่งออกยอดขาย/กำไร/ประวัติ',
    'activity_log' => 'บันทึกกิจกรรม (audit log)',
    'discord' => 'เชื่อมต่อ Discord',
    'analytics' => 'วิเคราะห์ต้นทุน–กำไร / รายงานลึก',
    'early_access' => 'ได้ฟีเจอร์ใหม่ก่อนใคร',
    'priority_support' => 'ซัพพอร์ตแบบให้ความสำคัญก่อน',
])
<section class="form-card" aria-labelledby="plan-form-title">
    <div class="card-head"><div><h2 id="plan-form-title">{{ $formTitle }}</h2><p>1 เครดิต = 1 บาท · ราคาเป็นจำนวนเต็ม · เว้นว่าง = ไม่จำกัด / ไม่เปิดขายรอบนั้น</p></div></div>
    <form class="stack-form" method="post" action="{{ $formAction }}" novalidate>
        @csrf
        @if($plan) @method('PATCH') @endif
        <div class="field-grid">
            <label class="field" for="plan-name">ชื่อแพ็กเกจ<input id="plan-name" name="name" value="{{ old('name', $plan?->name) }}" required maxlength="100">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="plan-code">Code<input id="plan-code" name="code" value="{{ old('code', $plan?->code) }}" required maxlength="40" placeholder="เช่น growth">@error('code')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="sort-order">ลำดับแสดง<input id="sort-order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $plan?->sort_order ?? 0) }}">@error('sort_order')<span class="field-error">{{ $message }}</span>@enderror</label>

            <label class="field" for="price-monthly">ราคา/เดือน (เครดิต)<input id="price-monthly" name="price_monthly" type="number" min="0" step="1" value="{{ old('price_monthly', $plan?->price_monthly) }}" required>@error('price_monthly')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="price-yearly">ราคา/ปี (เครดิต)<input id="price-yearly" name="price_yearly" type="number" min="0" step="1" value="{{ old('price_yearly', $plan?->price_yearly) }}" placeholder="เว้นว่าง = ไม่ขายรายปี">@error('price_yearly')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="monthly-days">อายุรอบเดือน (วัน)<input id="monthly-days" name="monthly_days" type="number" min="1" max="366" value="{{ old('monthly_days', $plan?->monthly_days ?? 30) }}" required>@error('monthly_days')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="yearly-days">อายุรอบปี (วัน)<input id="yearly-days" name="yearly_days" type="number" min="1" max="400" value="{{ old('yearly_days', $plan?->yearly_days ?? 365) }}" required>@error('yearly_days')<span class="field-error">{{ $message }}</span>@enderror</label>

            <label class="field" for="sale-price-monthly">ราคาโปรฯ/เดือน<input id="sale-price-monthly" name="sale_price_monthly" type="number" min="0" step="1" value="{{ old('sale_price_monthly', $plan?->sale_price_monthly) }}" placeholder="เว้นว่าง = ไม่ลด">@error('sale_price_monthly')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="sale-price-yearly">ราคาโปรฯ/ปี<input id="sale-price-yearly" name="sale_price_yearly" type="number" min="0" step="1" value="{{ old('sale_price_yearly', $plan?->sale_price_yearly) }}" placeholder="เว้นว่าง = ไม่ลด">@error('sale_price_yearly')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="sale-label">ป้ายโปรฯ<input id="sale-label" name="sale_label" value="{{ old('sale_label', $plan?->sale_label) }}" maxlength="60" placeholder="เช่น โปรเปิดตัว">@error('sale_label')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="sale-ends-at">โปรฯ ถึงวันที่<input id="sale-ends-at" name="sale_ends_at" type="datetime-local" value="{{ old('sale_ends_at', $plan?->sale_ends_at?->format('Y-m-d\TH:i')) }}" placeholder="เว้นว่าง = ไม่มีกำหนด">@error('sale_ends_at')<span class="field-error">{{ $message }}</span>@enderror</label>

            <label class="field" for="inventory-limit">สต็อก active สูงสุด<input id="inventory-limit" name="active_inventory_limit" type="number" min="1" value="{{ old('active_inventory_limit', $plan?->active_inventory_limit) }}" placeholder="เว้นว่าง = ไม่จำกัด">@error('active_inventory_limit')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="member-limit">สมาชิกสูงสุด (รวมเจ้าของ)<input id="member-limit" name="member_limit" type="number" min="1" value="{{ old('member_limit', $plan?->member_limit) }}" placeholder="เว้นว่าง = ไม่จำกัด">@error('member_limit')<span class="field-error">{{ $message }}</span>@enderror</label>
        </div>

        <fieldset class="feature-set">
            <legend>สิทธิ์ฟีเจอร์ของแพ็กนี้</legend>
            @php($current = old('features', $plan ? array_keys(array_filter((array) $plan->features)) : []))
            @foreach($featureLabels as $key => $label)
                <label class="check-row"><input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, (array) $current, true))><span><strong>{{ $label }}</strong><small>{{ $key }}</small></span></label>
            @endforeach
        </fieldset>

        <label class="check-row" for="plan-active"><input type="hidden" name="is_active" value="0"><input id="plan-active" name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $plan?->is_active ?? true))><span><strong>เปิดขายแพ็กเกจนี้</strong><small>เมื่อปิด ร้านใหม่จะเลือกซื้อไม่ได้ แต่ประวัติเดิมยังคงอยู่</small></span></label>
        <div class="form-actions"><a class="button secondary" href="{{ route('admin.plans.index') }}">ยกเลิก</a><button class="button" type="submit">{{ $submitLabel }}</button></div>
    </form>
</section>
