import { Eye } from "lucide-react";
import { Link } from "react-router-dom";
import { formatDate, money } from "../../shared/lib/format";
import { AsyncError } from "../../shared/ui/async-state";
import type { CustomerRecord, SaleRecord } from "../../types/models";

export function SalesPanel({
  records,
  loading,
  error,
  retry,
  canViewProfit,
}: {
  records: SaleRecord[];
  loading: boolean;
  error: string;
  retry: () => void;
  canViewProfit: boolean;
}) {
  return (
    <section className="panel history-panel" aria-labelledby="sales-title">
      <div className="panel-head">
        <div>
          <h2 id="sales-title">ประวัติการขาย</h2>
          <small>รายการขายล่าสุดของร้าน</small>
        </div>
      </div>
      {error ? (
        <AsyncError error={error} retry={retry} />
      ) : loading ? (
        <div className="empty" aria-live="polite">
          กำลังโหลดรายการขาย…
        </div>
      ) : records.length === 0 ? (
        <div className="empty">
          <strong>ยังไม่มีรายการขาย</strong>
          <p>เมื่อบันทึกการขายแล้ว ประวัติจะแสดงที่นี่</p>
        </div>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>วันเวลา</th>
                <th>ไอดี</th>
                <th>ลูกค้า</th>
                <th>ผู้ทำรายการ</th>
                <th>ราคาขาย</th>
                {canViewProfit ? <th>กำไร</th> : null}
                <th className="sale-action-column">Action</th>
              </tr>
            </thead>
            <tbody>
              {records.map((record) => (
                <tr key={record.id}>
                  <td>{formatDate(record.sold_at)}</td>
                  <td>
                    <strong>#{record.inventory_item?.tag ?? "–"}</strong>
                    <br />
                    <small>
                      {record.inventory_item?.title ?? "รายการถูกลบ"}
                    </small>
                  </td>
                  <td>
                    {record.customer?.name ?? "–"}
                    <br />
                    <small>
                      {record.customer?.line_id ?? record.customer?.phone ?? ""}
                    </small>
                  </td>
                  <td>{record.creator?.name ?? "–"}</td>
                  <td>
                    <strong>{money.format(Number(record.sold_price))}</strong>
                  </td>
                  {canViewProfit ? (
                    <td>{money.format(Number(record.profit ?? 0))}</td>
                  ) : null}
                  <td className="sale-action-column">
                    <Link
                      className="button ghost sale-detail-link"
                      to={`/sales/${record.id}`}
                      aria-label={`ดูรายละเอียดการขาย #${record.inventory_item?.tag ?? record.id}`}
                    >
                      <Eye size={16} /> ดูรายละเอียด
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <div className="pagination">
        <span>{records.length} รายการที่แสดง</span>
      </div>
    </section>
  );
}
export function CustomersPanel({
  records,
  loading,
  error,
  retry,
}: {
  records: CustomerRecord[];
  loading: boolean;
  error: string;
  retry: () => void;
}) {
  return (
    <section className="panel history-panel" aria-labelledby="customers-title">
      <div className="panel-head">
        <div>
          <h2 id="customers-title">ลูกค้า</h2>
          <small>ข้อมูลติดต่อลูกค้าที่บันทึกจากการขาย</small>
        </div>
      </div>
      {error ? (
        <AsyncError error={error} retry={retry} />
      ) : loading ? (
        <div className="empty" aria-live="polite">
          กำลังโหลดข้อมูลลูกค้า…
        </div>
      ) : records.length === 0 ? (
        <div className="empty">
          <strong>ยังไม่มีข้อมูลลูกค้า</strong>
          <p>ลูกค้าจะถูกบันทึกอัตโนมัติเมื่อมีรายการขาย</p>
        </div>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ชื่อลูกค้า</th>
                <th>LINE</th>
                <th>เบอร์โทร</th>
                <th>Facebook</th>
                <th>จำนวนครั้งที่ซื้อ</th>
                <th>อัปเดตล่าสุด</th>
              </tr>
            </thead>
            <tbody>
              {records.map((record) => (
                <tr key={record.id}>
                  <td>
                    <strong>{record.name}</strong>
                  </td>
                  <td>{record.line_id ?? "–"}</td>
                  <td>{record.phone ?? "–"}</td>
                  <td>{record.facebook_url ?? "–"}</td>
                  <td>{record.sales_count.toLocaleString("th-TH")} ครั้ง</td>
                  <td>{formatDate(record.updated_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <div className="pagination">
        <span>{records.length} รายการที่แสดง</span>
      </div>
    </section>
  );
}
