<section class="card" aria-labelledby="shops-title">
    <div class="card-head">
        <div><h2 id="shops-title">ร้านค้าทั้งหมด</h2><p>คลิกชื่อร้านเพื่อดูสมาชิก ประวัติเครดิต และแพ็กเกจ</p></div>
        <div class="head-actions"><span class="count">{{ $shops->total() }} ร้าน</span><a class="button action" href="{{ route('admin.shops.create') }}">เพิ่มร้านค้า</a></div>
    </div>
    <form class="toolbar" method="get" novalidate role="search">
        <div class="search-control">
            <label class="sr-only" for="shop-search">ค้นหาร้านค้า</label>
            <input id="shop-search" name="q" value="{{ $query }}" placeholder="ค้นหาชื่อร้านหรือ slug">
            @if($query)<a class="search-clear" href="{{ route('admin.shops.index') }}" aria-label="ล้างคำค้นร้านค้า">×</a>@endif
        </div>
        <button class="button" type="submit">ค้นหา</button>
    </form>
    <div class="table-wrap">
        <table class="shops-table">
            <thead>
                <tr><th scope="col">ร้านค้า</th><th scope="col">พนักงาน</th><th scope="col">Package</th><th scope="col">วันที่สมัคร</th><th scope="col">วันที่หมดอายุ</th><th scope="col">เครดิตคงเหลือ</th><th scope="col">สถานะ</th><th scope="col">Action</th></tr>
            </thead>
            <tbody>
            @forelse($shops as $shop)
                @php
                    $subscription = $shop->latestSubscription;
                    $effectiveStatus = $shop->trashed() ? 'archived' : $shop->status;
                    $expiresAt = $subscription?->ends_at ?? $shop->trial_ends_at;
                @endphp
                <tr class="{{ $shop->trashed() ? 'row-archived' : '' }}">
                    <td class="shop-name"><a class="table-link" href="{{ route('admin.shops.show', $shop) }}">{{ $shop->name }}</a><small>{{ $shop->slug }}</small></td>
                    <td>{{ number_format($shop->staff_count) }} คน</td>
                    <td><strong>{{ $subscription?->plan?->name ?? ($subscription?->status?->value === 'trialing' ? 'Trial' : '—') }}</strong></td>
                    <td class="muted">{{ $shop->created_at->timezone('Asia/Bangkok')->format('d/m/Y') }}</td>
                    <td class="muted">{{ $expiresAt?->timezone('Asia/Bangkok')->format('d/m/Y') ?? '—' }}</td>
                    <td class="credit">{{ number_format($shop->credit_balance) }}</td>
                    <td><span class="status {{ $effectiveStatus }}">{{ $statusLabels[$effectiveStatus] ?? $effectiveStatus }}</span></td>
                    <td>
                        <div class="table-actions">
                            <a class="button secondary compact" href="{{ route('admin.shops.show', $shop) }}">ดูข้อมูล</a>
                            @unless($shop->trashed())<a class="button ghost compact" href="{{ route('admin.shops.edit', $shop) }}">แก้ไข</a>@endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty"><strong>ไม่พบร้านค้า</strong>ลองเปลี่ยนคำค้นหรือเพิ่มร้านค้าใหม่</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <span>แสดง {{ $shops->firstItem() ?? 0 }}–{{ $shops->lastItem() ?? 0 }} จาก {{ $shops->total() }} ร้าน</span>
        {{ $shops->withQueryString()->onEachSide(1)->links() }}
    </div>
</section>
