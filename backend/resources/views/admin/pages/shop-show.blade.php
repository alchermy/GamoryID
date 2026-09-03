@php
    $currentSubscription = $shop->latestSubscription;
    $shopStatus = $shop->trashed() ? 'archived' : $shop->status;
    $expiresAt = $currentSubscription?->ends_at ?? $shop->trial_ends_at;
@endphp
<div class="back-row">
    <a class="back-link" href="{{ route('admin.shops.index') }}">← กลับไปรายการร้านค้า</a>
    <div class="head-actions">
        @if($shop->trashed())
            <form method="post" action="{{ route('admin.shops.restore', $shop) }}" novalidate>@csrf @method('PATCH')<button class="button" type="submit">กู้คืนร้านค้า</button></form>
        @else
            <a class="button secondary" href="{{ route('admin.shops.edit', $shop) }}">แก้ไขข้อมูลร้าน</a>
            <form method="post" action="{{ route('admin.shops.destroy', $shop) }}" novalidate>
                @csrf @method('DELETE')
                <button class="button danger-outline" type="button" data-admin-confirm="เก็บร้าน {{ $shop->name }} ไว้ถาวร? ร้านจะเข้าใช้งานไม่ได้ แต่ประวัติเครดิต แพ็กเกจ และ Log จะไม่ถูกลบ" data-confirm-label="เก็บร้านถาวร" data-confirm-intent="reject">เก็บร้านถาวร</button>
            </form>
        @endif
    </div>
</div>

<section class="shop-hero" aria-label="สรุปร้านค้า">
    <div class="shop-identity"><span class="eyebrow">SHOP #{{ $shop->id }}</span><h2>{{ $shop->name }}</h2><p>{{ $shop->slug }} · สมัครเมื่อ {{ $shop->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} น.</p></div>
    <div class="shop-hero-status"><span class="status {{ $shopStatus }}">{{ $statusLabels[$shopStatus] ?? $shopStatus }}</span></div>
    <div class="shop-kpis">
        <div><span>เครดิตคงเหลือ</span><strong>{{ number_format($shop->credit_balance) }}</strong><small>เครดิต</small></div>
        <div><span>แพ็กเกจปัจจุบัน</span><strong>{{ $currentSubscription?->plan?->name ?? ($currentSubscription?->status?->value === 'trialing' ? 'Trial' : '—') }}</strong><small>{{ $expiresAt ? 'หมดอายุ '.$expiresAt->timezone('Asia/Bangkok')->format('d/m/Y') : 'ยังไม่กำหนดวันหมดอายุ' }}</small></div>
        <div><span>สมาชิกในร้าน</span><strong>{{ number_format($shop->users->count()) }}</strong><small>{{ number_format($shop->users->where('pivot.role', 'staff')->count()) }} พนักงาน</small></div>
    </div>
</section>

<section class="detail-grid">
    <section class="card" aria-labelledby="subscription-control-title">
        <div class="card-head"><div><h2 id="subscription-control-title">การสมัครและต่ออายุ</h2><p>ตั้งค่าการหักเครดิตเมื่อแพ็กเกจครบกำหนด</p></div></div>
        <div class="settings-row">
            <div><strong>ต่ออายุอัตโนมัติ</strong><p>{{ $currentSubscription ? 'แพ็กเกจ '.$currentSubscription?->plan?->name.' · หักเครดิตตามราคาปัจจุบันเมื่อครบกำหนด' : 'ร้านนี้ยังไม่มีแพ็กเกจที่ตั้งค่าต่ออายุได้' }}</p></div>
            @if($currentSubscription && !$shop->trashed())
                <form method="post" action="{{ route('admin.shops.auto-renew', $shop) }}" novalidate>
                    @csrf @method('PATCH')
                    <input type="hidden" name="auto_renew" value="{{ $currentSubscription->auto_renew ? 0 : 1 }}">
                    <button class="switch-control {{ $currentSubscription->auto_renew ? 'on' : '' }}" type="button" role="switch" aria-checked="{{ $currentSubscription->auto_renew ? 'true' : 'false' }}" data-admin-confirm="{{ $currentSubscription->auto_renew ? 'ปิด' : 'เปิด' }}ต่ออายุอัตโนมัติสำหรับร้าน {{ $shop->name }}?" data-confirm-label="{{ $currentSubscription->auto_renew ? 'ปิดต่ออายุ' : 'เปิดต่ออายุ' }}" data-confirm-intent="{{ $currentSubscription->auto_renew ? '' : 'approve' }}"><span aria-hidden="true"></span><b>{{ $currentSubscription->auto_renew ? 'เปิด' : 'ปิด' }}</b></button>
                </form>
            @else
                <span class="muted">ไม่พร้อมใช้งาน</span>
            @endif
        </div>
        <dl class="info-list">
            <div><dt>สถานะ subscription</dt><dd>{{ $currentSubscription ? ($statusLabels[$currentSubscription->status->value] ?? $currentSubscription->status->value) : '—' }}</dd></div>
            <div><dt>เริ่มใช้งาน</dt><dd>{{ $currentSubscription?->starts_at?->timezone('Asia/Bangkok')->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>หมดอายุ</dt><dd>{{ $expiresAt?->timezone('Asia/Bangkok')->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="card" aria-labelledby="contact-title">
        <div class="card-head"><div><h2 id="contact-title">ข้อมูลร้านและการติดต่อ</h2><p>ข้อมูลที่เจ้าของร้านตั้งค่าไว้</p></div></div>
        <dl class="info-list">
            <div><dt>Facebook</dt><dd>{{ $shop->facebook_url ?: '—' }}</dd></div>
            <div><dt>LINE</dt><dd>{{ $shop->line_url ?: '—' }}</dd></div>
            <div><dt>เบอร์โทร</dt><dd>{{ $shop->phone ?: '—' }}</dd></div>
            <div><dt>รายละเอียด</dt><dd>{{ $shop->description ?: '—' }}</dd></div>
            <div><dt>หน้าร้านสาธารณะ</dt><dd>{{ $shop->storefront_enabled ? 'เปิด' : 'ปิด' }}</dd></div>
            <div><dt>หน้ารวม /browse</dt><dd>{{ $shop->hidden_from_directory ? 'ถูกซ่อนโดยผู้ดูแล' : 'แสดงตามปกติ' }}</dd></div>
        </dl>
    </section>
</section>

<section class="card" aria-labelledby="directory-title">
    <div class="card-head"><div><h2 id="directory-title">รายการในหน้ารวม /browse</h2><p>ซ่อนไอดีที่ไม่เหมาะสมออกจากหน้ารวม โดยหน้าร้านตรง /s/{{ $shop->slug }}/&lt;tag&gt; ยังเปิดปกติ</p></div><span class="count">{{ $directoryListings->count() }} รายการพร้อมขาย</span></div>
    <div class="table-wrap"><table><thead><tr><th scope="col">แท็ก</th><th scope="col">ชื่อไอดี</th><th scope="col">ราคา</th><th scope="col">สถานะในหน้ารวม</th><th scope="col">การจัดการ</th></tr></thead><tbody>
        @forelse($directoryListings as $listing)
            <tr>
                <td><strong>#{{ $listing->tag }}</strong></td>
                <td>{{ $listing->title ?: '—' }}</td>
                <td class="credit">{{ number_format($listing->list_price) }}</td>
                <td>{!! $listing->hidden_from_directory ? '<span class="status suspended">ถูกซ่อน</span>' : '<span class="status active">แสดงอยู่</span>' !!}</td>
                <td>
                    <form method="post" action="{{ route('admin.shops.listing-visibility', [$shop, $listing]) }}" novalidate>
                        @csrf @method('PATCH')
                        <button class="button secondary" type="submit">{{ $listing->hidden_from_directory ? 'แสดงในหน้ารวม' : 'ซ่อนจากหน้ารวม' }}</button>
                    </form>
                </td>
            </tr>
        @empty<tr><td colspan="5" class="empty">ยังไม่มีไอดีที่พร้อมขายในร้านนี้</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="card" aria-labelledby="members-title">
    <div class="card-head"><div><h2 id="members-title">สมาชิกในร้าน</h2><p>เจ้าของร้านและพนักงานที่เข้าใช้งานได้</p></div><span class="count">{{ $shop->users->count() }} คน</span></div>
    <div class="table-wrap"><table><thead><tr><th scope="col">ชื่อ</th><th scope="col">อีเมล</th><th scope="col">Role</th><th scope="col">เข้าร่วมเมื่อ</th></tr></thead><tbody>
        @forelse($shop->users as $member)
            <tr><td><strong>{{ $member->name }}</strong></td><td>{{ $member->email }}</td><td><span class="role-badge">{{ $member->pivot->role === 'owner' ? 'เจ้าของร้าน' : 'พนักงาน' }}</span></td><td class="muted">{{ $member->pivot->joined_at ? \Carbon\Carbon::parse($member->pivot->joined_at)->timezone('Asia/Bangkok')->format('d/m/Y H:i') : '—' }}</td></tr>
        @empty<tr><td colspan="4" class="empty">ยังไม่มีสมาชิกในร้าน</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="card" aria-labelledby="subscriptions-title">
    <div class="card-head"><div><h2 id="subscriptions-title">ประวัติการสมัครแพ็กเกจ</h2><p>แพ็กเกจทุกช่วงเวลาของร้าน เรียงจากล่าสุด</p></div><span class="count">{{ $subscriptions->total() }} รายการ</span></div>
    <div class="table-wrap"><table><thead><tr><th scope="col">Package</th><th scope="col">สถานะ</th><th scope="col">เริ่มใช้งาน</th><th scope="col">หมดอายุ</th><th scope="col">ต่ออายุอัตโนมัติ</th></tr></thead><tbody>
        @forelse($subscriptions as $subscription)
            <tr><td><strong>{{ $subscription->plan?->name ?? 'Trial' }}</strong></td><td><span class="status {{ $subscription->status->value }}">{{ $statusLabels[$subscription->status->value] ?? $subscription->status->value }}</span></td><td>{{ $subscription->starts_at?->timezone('Asia/Bangkok')->format('d/m/Y H:i') ?? '—' }}</td><td>{{ $subscription->ends_at?->timezone('Asia/Bangkok')->format('d/m/Y H:i') ?? '—' }}</td><td>{{ $subscription->auto_renew ? 'เปิด' : 'ปิด' }}</td></tr>
        @empty<tr><td colspan="5" class="empty">ยังไม่มีประวัติแพ็กเกจ</td></tr>@endforelse
    </tbody></table></div>
    @if($subscriptions->hasPages())<div class="pagination">{{ $subscriptions->onEachSide(1)->links() }}</div>@endif
</section>

<section class="card" aria-labelledby="shop-topups-title">
    <div class="card-head"><div><h2 id="shop-topups-title">ประวัติการเติมเครดิต</h2><p>จำนวน สถานะ และเหตุผลจากผู้ตรวจสอบ</p></div><span class="count">{{ $topUps->total() }} รายการ</span></div>
    <div class="table-wrap"><table><thead><tr><th scope="col">วันที่ส่ง</th><th scope="col">ผู้ส่ง</th><th scope="col">เครดิต</th><th scope="col">สถานะ</th><th scope="col">เหตุผล / หมายเหตุ</th><th scope="col">สลิป</th></tr></thead><tbody>
        @forelse($topUps as $topUp)
            <tr><td>{{ $topUp->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</td><td>{{ $topUp->submittedBy?->name ?? '—' }}</td><td class="credit">{{ number_format($topUp->credit_amount) }}</td><td><span class="status {{ $topUp->status }}">{{ $statusLabels[$topUp->status] ?? $topUp->status }}</span></td><td class="muted">{{ $topUp->review_note ?: 'รอตรวจสอบรายการ' }}</td><td><a class="slip-link" target="_blank" rel="noopener" href="{{ route('admin.top-ups.slip', $topUp) }}">เปิดสลิป</a></td></tr>
        @empty<tr><td colspan="6" class="empty">ยังไม่มีประวัติการเติมเครดิต</td></tr>@endforelse
    </tbody></table></div>
    @if($topUps->hasPages())<div class="pagination">{{ $topUps->onEachSide(1)->links() }}</div>@endif
</section>

<section class="card" aria-labelledby="ledger-title">
    <div class="card-head"><div><h2 id="ledger-title">Credit ledger ล่าสุด</h2><p>รายการเพิ่มและหักเครดิตที่เกิดขึ้นจริง</p></div></div>
    <div class="table-wrap"><table><thead><tr><th scope="col">เวลา</th><th scope="col">รายการ</th><th scope="col">Package</th><th scope="col">เครดิต</th><th scope="col">คงเหลือหลังรายการ</th></tr></thead><tbody>
        @forelse($creditTransactions as $transaction)
            <tr><td>{{ $transaction->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</td><td>{{ $transaction->type }}</td><td>{{ $transaction->plan?->name ?? '—' }}</td><td class="{{ $transaction->credits >= 0 ? 'credit-positive' : 'credit-negative' }}">{{ $transaction->credits >= 0 ? '+' : '' }}{{ number_format($transaction->credits) }}</td><td class="credit">{{ number_format($transaction->balance_after) }}</td></tr>
        @empty<tr><td colspan="5" class="empty">ยังไม่มีรายการใน credit ledger</td></tr>@endforelse
    </tbody></table></div>
</section>
