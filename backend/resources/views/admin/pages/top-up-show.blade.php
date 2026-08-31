@php $canReview = in_array($payment->status, ['pending', 'pending_review'], true); @endphp
<div class="back-row"><a class="back-link" href="{{ route('admin.top-ups.index') }}">← กลับไปรายการเติมเครดิต</a></div>

<section class="topup-summary" aria-label="สรุปรายการเติมเครดิต">
    <div><span class="eyebrow">TOP-UP #{{ $payment->id }}</span><h2>{{ $payment->shop->name }}</h2><p>ส่งรายการเมื่อ {{ $payment->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} น.</p></div>
    <div class="topup-amount"><span>จำนวนเครดิต</span><strong>{{ number_format($payment->credit_amount) }}</strong><small>เครดิต · {{ number_format((float) $payment->expected_amount, 2) }} บาท</small></div>
    <span class="status {{ $payment->status }}">{{ $statusLabels[$payment->status] ?? $payment->status }}</span>
</section>

<section class="topup-detail-grid">
    <section class="card slip-card" aria-labelledby="slip-title">
        <div class="card-head"><div><h2 id="slip-title">สลิปการเติมเครดิต</h2><p>ตรวจสอบยอด วันที่ เวลา และบัญชีผู้รับจากภาพจริง</p></div><a class="button secondary compact" target="_blank" rel="noopener" href="{{ route('admin.top-ups.slip', $payment) }}">เปิดภาพเต็ม ↗</a></div>
        <div class="slip-preview"><img src="{{ route('admin.top-ups.slip', $payment) }}" alt="สลิปเติมเครดิตหมายเลข {{ $payment->id }} ของร้าน {{ $payment->shop->name }}"></div>
    </section>

    <div>
        <section class="card" aria-labelledby="topup-info-title">
            <div class="card-head"><div><h2 id="topup-info-title">ข้อมูลรายการ</h2><p>ข้อมูลที่ส่งมาพร้อมสลิป</p></div></div>
            <dl class="info-list">
                <div><dt>ร้านค้า</dt><dd><a class="table-link" href="{{ route('admin.shops.show', $payment->shop) }}">{{ $payment->shop->name }}</a></dd></div>
                <div><dt>ผู้ส่งรายการ</dt><dd>{{ $payment->submittedBy?->name ?? 'ไม่ระบุ' }}@if($payment->submittedBy)<small>{{ $payment->submittedBy->email }}</small>@endif</dd></div>
                <div><dt>เครดิตที่ขอเติม</dt><dd>{{ number_format($payment->credit_amount) }} เครดิต</dd></div>
                <div><dt>ยอดที่แจ้ง</dt><dd>{{ number_format((float) $payment->expected_amount, 2) }} บาท</dd></div>
                <div><dt>เลขอ้างอิง</dt><dd>{{ $payment->provider_reference ?: '—' }}</dd></div>
                <div><dt>เครดิตร้านปัจจุบัน</dt><dd>{{ number_format($payment->shop->credit_balance) }} เครดิต</dd></div>
                <div><dt>ผลการตรวจ</dt><dd>{{ $payment->review_note ?: 'ยังไม่มีผลการตรวจ' }}</dd></div>
            </dl>
        </section>

        <section class="card decision-card" aria-labelledby="decision-title">
            <div class="card-head"><div><h2 id="decision-title">ปรับสถานะรายการ</h2><p>{{ $canReview ? 'อนุมัติเพื่อเพิ่มเครดิต หรือปฏิเสธพร้อมระบุเหตุผล' : 'รายการนี้ได้รับการตรวจสอบแล้ว' }}</p></div></div>
            @if($canReview)
                <form class="decision-form" method="post" action="{{ route('admin.top-ups.review', $payment) }}" novalidate data-review-form>
                    @csrf @method('PATCH')
                    <input type="hidden" name="decision" value="">
                    <input type="hidden" name="review_payment_id" value="{{ $payment->id }}">
                    <label for="review-note">หมายเหตุ <span>(จำเป็นเมื่อปฏิเสธ)</span></label>
                    <textarea id="review-note" name="review_note" rows="4" maxlength="1000" placeholder="เช่น ชื่อบัญชีผู้รับไม่ตรง หรือยอดเงินไม่ครบ" aria-describedby="review-note-help review-error" @error('review_note') aria-invalid="true" @enderror>{{ old('review_note') }}</textarea>
                    <small id="review-note-help">หากอนุมัติโดยไม่กรอก ระบบจะบันทึกว่า “ตรวจสอบสลิปและยอดเงินถูกต้อง”</small>
                    <span class="field-error" id="review-error" data-review-error>@error('review_note'){{ $message }}@enderror</span>
                    <div class="decision-actions">
                        <button class="button reject" type="button" value="rejected" data-admin-confirm="ปฏิเสธรายการเติม {{ number_format($payment->credit_amount) }} เครดิตของร้าน {{ $payment->shop->name }}? ร้านค้าจะเห็นเหตุผลที่ระบุ" data-confirm-label="ปฏิเสธรายการ" data-confirm-intent="reject" data-confirm-requires="review_note">ปฏิเสธ</button>
                        <button class="button approve" type="button" value="approved" data-admin-confirm="อนุมัติ {{ number_format($payment->credit_amount) }} เครดิตให้ร้าน {{ $payment->shop->name }}? เครดิตจะถูกเพิ่มทันทีหลังยืนยัน" data-confirm-label="อนุมัติเครดิต" data-confirm-intent="approve">อนุมัติ</button>
                    </div>
                </form>
            @else
                <div class="decision-result"><span class="status {{ $payment->status }}">{{ $statusLabels[$payment->status] ?? $payment->status }}</span><strong>{{ $payment->review_note ?: 'ไม่มีหมายเหตุ' }}</strong>@if($payment->verified_at)<small>ตรวจเมื่อ {{ $payment->verified_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} น.</small>@endif</div>
            @endif
        </section>
    </div>
</section>
