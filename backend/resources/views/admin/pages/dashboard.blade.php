<section class="stats" aria-label="ตัวเลขภาพรวมระบบ">
    <article class="stat accent">
        <div class="label">เครดิตคงเหลือทุกร้าน</div>
        <div class="value">{{ number_format($totals['credit_balance']) }}</div>
        <small>เครดิต · 1 เครดิต = 1 บาท</small>
    </article>
    <article class="stat">
        <div class="label">เครดิตที่อนุมัติแล้ว</div>
        <div class="value">{{ number_format($totals['credited_total']) }}</div>
        <small>สะสมตลอดระบบ</small>
    </article>
    <article class="stat">
        <div class="label">รายการรอตรวจ</div>
        <div class="value">{{ number_format($totals['pending_top_ups']) }}</div>
        <small>ต้องอนุมัติก่อนเพิ่มเครดิต</small>
    </article>
    <article class="stat">
        <div class="label">ร้านค้า / ผู้ใช้งาน</div>
        <div class="value">{{ number_format($totals['shops']) }} / {{ number_format($totals['users']) }}</div>
        <small>{{ number_format($totals['items']) }} รายการในคลัง</small>
    </article>
</section>

<section class="grid">
    <section class="card" aria-labelledby="recent-topups-title">
        <div class="card-head">
            <div><h2 id="recent-topups-title">เครดิตล่าสุด</h2><p>รายการล่าสุดจากร้านค้า</p></div>
            <a class="button secondary" href="{{ route('admin.top-ups.index') }}">ดูทั้งหมด</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">ร้าน</th><th scope="col">เครดิต</th><th scope="col">สถานะ</th><th scope="col">เวลา</th></tr></thead>
                <tbody>
                @forelse($recentTopUps as $topUp)
                    <tr>
                        <td><a class="table-link" href="{{ route('admin.shops.show', $topUp->shop) }}">{{ $topUp->shop->name }}</a></td>
                        <td class="credit">{{ number_format($topUp->credit_amount) }}</td>
                        <td><span class="status {{ $topUp->status }}">{{ $statusLabels[$topUp->status] ?? $topUp->status }}</span></td>
                        <td class="muted">{{ $topUp->created_at->timezone('Asia/Bangkok')->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">ยังไม่มีรายการเติมเครดิต</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" aria-labelledby="recent-logs-title">
        <div class="card-head">
            <div><h2 id="recent-logs-title">Log ล่าสุด</h2><p>เหตุการณ์สำคัญในระบบ</p></div>
            <a class="button secondary" href="{{ route('admin.logs.index') }}">ดูทั้งหมด</a>
        </div>
        @forelse($recentLogs as $log)
            <div class="row-pad">
                <div class="log-event">{{ $log->event }}</div>
                <div class="meta">{{ $log->shop?->name ?? 'ระบบกลาง' }} · {{ $log->user?->name ?? 'System' }} · {{ $log->created_at->timezone('Asia/Bangkok')->format('d/m H:i') }}</div>
            </div>
        @empty
            <div class="empty"><strong>ยังไม่มี Log</strong>เหตุการณ์สำคัญจะแสดงที่นี่</div>
        @endforelse
    </section>
</section>
