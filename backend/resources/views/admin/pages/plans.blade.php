<div class="section-actions"><div><h2>แพ็กเกจบริการ</h2><p>เลือกรายการเพื่อดูและแก้ไขข้อมูล</p></div><a class="button action" href="{{ route('admin.plans.create') }}">เพิ่มแพ็กเกจ</a></div>
<section class="plan-grid" aria-label="แพ็กเกจทั้งหมด">
    @forelse($plans as $plan)
        <a class="plan-card {{ $plan->is_active ? '' : 'inactive' }}" href="{{ route('admin.plans.edit', $plan) }}">
            <div class="plan-card-top"><span class="plan-code">{{ strtoupper($plan->code) }}</span><span class="status {{ $plan->is_active ? 'active' : 'suspended' }}">{{ $plan->is_active ? 'เปิดขาย' : 'ปิดขาย' }}</span></div>
            <h2>{{ $plan->name }}</h2>
            <div class="plan-price"><strong>{{ number_format((int) $plan->price_thb) }}</strong><span>เครดิต / {{ $plan->duration_days }} วัน</span></div>
            <dl class="plan-limits"><div><dt>สต็อก active</dt><dd>{{ number_format($plan->active_inventory_limit) }} รายการ</dd></div><div><dt>สมาชิก</dt><dd>{{ number_format($plan->member_limit) }} คน</dd></div></dl>
            <span class="plan-edit-label">ดูและแก้ไขแพ็กเกจ →</span>
        </a>
    @empty
        <div class="empty-card"><strong>ยังไม่มีแพ็กเกจ</strong><span>สร้างแพ็กเกจแรกเพื่อเปิดให้ร้านค้าเลือกซื้อ</span><a class="button" href="{{ route('admin.plans.create') }}">เพิ่มแพ็กเกจ</a></div>
    @endforelse
</section>
