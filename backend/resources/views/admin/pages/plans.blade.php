@php($featureLabels = [
    'bulk_import' => 'นำเข้าชุด',
    'advanced_export' => 'ส่งออกรายงาน',
    'activity_log' => 'บันทึกกิจกรรม',
    'discord' => 'Discord',
    'analytics' => 'วิเคราะห์กำไร',
    'early_access' => 'ฟีเจอร์ใหม่ก่อน',
    'priority_support' => 'ซัพพอร์ตพิเศษ',
])
<div class="section-actions"><div><h2>แพ็กเกจบริการ</h2><p>เลือกรายการเพื่อดูและแก้ไขราคา/สิทธิ์</p></div><a class="button action" href="{{ route('admin.plans.create') }}">เพิ่มแพ็กเกจ</a></div>
<section class="plan-grid" aria-label="แพ็กเกจทั้งหมด">
    @forelse($plans as $plan)
        <a class="plan-card {{ $plan->is_active ? '' : 'inactive' }}" href="{{ route('admin.plans.edit', $plan) }}">
            <div class="plan-card-top"><span class="plan-code">{{ strtoupper($plan->code) }}</span><span class="status {{ $plan->is_active ? 'active' : 'suspended' }}">{{ $plan->is_active ? 'เปิดขาย' : 'ปิดขาย' }}</span></div>
            <h2>{{ $plan->name }}</h2>
            <div class="plan-price">
                <strong>{{ $plan->isFree() ? 'ฟรี' : number_format($plan->price_monthly) }}</strong>
                <span>@if(!$plan->isFree())เครดิต / เดือน @endif</span>
            </div>
            @if($plan->price_yearly)<p class="plan-sub">รายปี {{ number_format($plan->price_yearly) }} เครดิต</p>@endif
            @if($plan->sale_label && ($plan->sale_price_monthly || $plan->sale_price_yearly))<p class="plan-sub">🏷️ {{ $plan->sale_label }} @if($plan->sale_price_monthly){{ number_format($plan->sale_price_monthly) }}/เดือน @endif</p>@endif
            <dl class="plan-limits">
                <div><dt>สต็อก active</dt><dd>{{ $plan->active_inventory_limit ? number_format($plan->active_inventory_limit).' รายการ' : 'ไม่จำกัด' }}</dd></div>
                <div><dt>สมาชิก</dt><dd>{{ $plan->member_limit ? number_format($plan->member_limit).' คน' : 'ไม่จำกัด' }}</dd></div>
            </dl>
            <p class="plan-features">
                @foreach($featureLabels as $key => $label)@if($plan->feature($key))<span class="tag">{{ $label }}</span>@endif @endforeach
            </p>
            <span class="plan-edit-label">ดูและแก้ไข →</span>
        </a>
    @empty
        <div class="empty-card"><strong>ยังไม่มีแพ็กเกจ</strong><span>สร้างแพ็กเกจแรกเพื่อเปิดให้ร้านค้าเลือกซื้อ</span><a class="button" href="{{ route('admin.plans.create') }}">เพิ่มแพ็กเกจ</a></div>
    @endforelse
</section>
