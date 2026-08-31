<section class="card" aria-labelledby="topups-title">
    <div class="card-head">
        <div><h2 id="topups-title">รายการเติมเครดิต</h2><p>ค้นหารายการแล้วกดดูรายละเอียดเพื่อตรวจสลิป อนุมัติ หรือปฏิเสธ</p></div>
        <span class="count">{{ $topUps->total() }} รายการ</span>
    </div>

    <form class="filter-toolbar" method="get" action="{{ route('admin.top-ups.index') }}" novalidate role="search">
        <label class="filter-field filter-search" for="topup-search">
            <span>ร้านค้า</span>
            <div class="search-control">
                <input id="topup-search" name="q" value="{{ $query }}" placeholder="ค้นหาชื่อร้านหรือ slug">
                @if($query)<a class="search-clear" href="{{ route('admin.top-ups.index', array_filter(['date' => $date, 'status' => $status !== 'all' ? $status : null])) }}" aria-label="ล้างคำค้นร้านค้า">×</a>@endif
            </div>
        </label>
        <label class="filter-field" for="topup-date"><span>วันที่ส่งรายการ</span><input id="topup-date" name="date" type="date" value="{{ $date }}"></label>
        <label class="filter-field" for="topup-status"><span>สถานะ</span><select id="topup-status" name="status">
            @foreach(['all' => 'ทุกสถานะ', 'pending' => 'กำลังตรวจสลิป', 'pending_review' => 'รออนุมัติ', 'verified' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
        <div class="filter-actions">
            <button class="button" type="submit">ค้นหา</button>
            @if($query || $date || $status !== 'all')<a class="button secondary" href="{{ route('admin.top-ups.index') }}">ล้าง Filter</a>@endif
        </div>
    </form>

    <div class="table-wrap">
        <table class="topups-table">
            <thead><tr><th scope="col">#</th><th scope="col">ร้านค้า</th><th scope="col">วันที่ส่ง</th><th scope="col">เครดิต</th><th scope="col">ผู้ส่ง</th><th scope="col">สถานะ</th><th scope="col">Action</th></tr></thead>
            <tbody>
            @forelse($topUps as $topUp)
                <tr>
                    <td class="row-index">{{ number_format(($topUps->firstItem() ?? 1) + $loop->index) }}</td>
                    <td class="shop-name"><a class="table-link" href="{{ route('admin.top-ups.show', $topUp) }}">{{ $topUp->shop->name }}</a><small>{{ $topUp->shop->slug }}</small></td>
                    <td class="muted">{{ $topUp->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} น.</td>
                    <td class="credit">{{ number_format($topUp->credit_amount) }}</td>
                    <td>{{ $topUp->submittedBy?->name ?? 'ไม่ระบุผู้ส่ง' }}</td>
                    <td><span class="status {{ $topUp->status }}">{{ $statusLabels[$topUp->status] ?? $topUp->status }}</span></td>
                    <td><a class="button secondary compact" href="{{ route('admin.top-ups.show', $topUp) }}">ดูรายละเอียด</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty"><strong>ไม่พบรายการเติมเครดิต</strong>ลองเปลี่ยนชื่อร้าน วันที่ หรือสถานะที่ค้นหา</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <span>แสดง {{ $topUps->firstItem() ?? 0 }}–{{ $topUps->lastItem() ?? 0 }} จาก {{ $topUps->total() }} รายการ</span>
        {{ $topUps->withQueryString()->onEachSide(1)->links() }}
    </div>
</section>
