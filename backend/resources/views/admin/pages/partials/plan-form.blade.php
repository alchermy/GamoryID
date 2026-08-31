<section class="form-card" aria-labelledby="plan-form-title">
    <div class="card-head"><div><h2 id="plan-form-title">{{ $formTitle }}</h2><p>1 เครดิต = 1 บาท และราคาต้องเป็นจำนวนเต็ม</p></div></div>
    <form class="stack-form" method="post" action="{{ $formAction }}" novalidate>
        @csrf
        @if($plan) @method('PATCH') @endif
        <div class="field-grid">
            <label class="field" for="plan-name">ชื่อแพ็กเกจ<input id="plan-name" name="name" value="{{ old('name', $plan?->name) }}" required maxlength="100" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="plan-code">Code<input id="plan-code" name="code" value="{{ old('code', $plan?->code) }}" required maxlength="40" placeholder="เช่น growth" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">@error('code')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="plan-price">ราคา (เครดิต)<input id="plan-price" name="price_thb" type="number" min="0" step="1" value="{{ old('price_thb', $plan ? (int) $plan->price_thb : null) }}" required aria-invalid="{{ $errors->has('price_thb') ? 'true' : 'false' }}">@error('price_thb')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="inventory-limit">สต็อก active สูงสุด<input id="inventory-limit" name="active_inventory_limit" type="number" min="1" value="{{ old('active_inventory_limit', $plan?->active_inventory_limit) }}" required aria-invalid="{{ $errors->has('active_inventory_limit') ? 'true' : 'false' }}">@error('active_inventory_limit')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="member-limit">สมาชิกสูงสุด<input id="member-limit" name="member_limit" type="number" min="1" value="{{ old('member_limit', $plan?->member_limit) }}" required aria-invalid="{{ $errors->has('member_limit') ? 'true' : 'false' }}">@error('member_limit')<span class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="duration-days">อายุแพ็กเกจ (วัน)<input id="duration-days" name="duration_days" type="number" min="1" max="365" value="{{ old('duration_days', $plan?->duration_days ?? 30) }}" required aria-invalid="{{ $errors->has('duration_days') ? 'true' : 'false' }}">@error('duration_days')<span class="field-error">{{ $message }}</span>@enderror</label>
        </div>
        <label class="check-row" for="plan-active"><input type="hidden" name="is_active" value="0"><input id="plan-active" name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $plan?->is_active ?? true))><span><strong>เปิดขายแพ็กเกจนี้</strong><small>เมื่อปิด ร้านใหม่จะเลือกซื้อไม่ได้ แต่ประวัติเดิมยังคงอยู่</small></span></label>
        <div class="form-actions"><a class="button secondary" href="{{ route('admin.plans.index') }}">ยกเลิก</a><button class="button" type="submit">{{ $submitLabel }}</button></div>
    </form>
</section>
