<section class="card" aria-labelledby="logs-title">
    <div class="card-head">
        <div><h2 id="logs-title">Log การทำรายการ</h2><p>บันทึกเหตุการณ์ของร้านค้าและผู้ดูแลระบบ</p></div>
        <span class="count">{{ $logs->total() }} รายการ</span>
    </div>
    <form class="toolbar" method="get" novalidate role="search">
        <div class="search-control">
            <label class="sr-only" for="log-search">ค้นหา Log</label>
            <input id="log-search" name="q" value="{{ $query }}" placeholder="ค้นหา event หรือชื่อร้าน">
            @if($query)<a class="search-clear" href="{{ route('admin.logs.index') }}" aria-label="ล้างคำค้น Log">×</a>@endif
        </div>
        <button class="button" type="submit">ค้นหา</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">เวลา</th><th scope="col">Event</th><th scope="col">ร้านค้า</th><th scope="col">ผู้ทำรายการ</th><th scope="col">รายละเอียด</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="muted">{{ $log->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i:s') }}</td>
                    <td class="log-event">{{ $log->event }}</td>
                    <td>{{ $log->shop?->name ?? 'ระบบกลาง' }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td class="muted">{{ collect($log->metadata ?? [])->filter(fn($value) => is_scalar($value))->map(fn($value, $key) => $key.': '.$value)->join(' · ') ?: '–' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">ไม่พบ Log ที่ตรงกับคำค้น</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <span>แสดง {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} จาก {{ $logs->total() }} รายการ</span>
        {{ $logs->withQueryString()->onEachSide(1)->links() }}
    </div>
</section>
