import { ReceiptText, WalletCards } from "lucide-react";
import { formatDate } from "../../shared/lib/format";
import { AsyncError } from "../../shared/ui/async-state";
import type { BillingHistory } from "../../types/models";

export function TransactionsPanel({
  history,
  loading,
  error,
  retry,
}: {
  history: BillingHistory | null;
  loading: boolean;
  error: string;
  retry: () => void;
}) {
  const subscriptions = history?.subscriptions.items ?? [],
    topUps = history?.top_ups.items ?? [];
  if (error)
    return (
      <section className="panel management-panel">
        <AsyncError error={error} retry={retry} />
      </section>
    );
  if (loading && !history)
    return (
      <section className="panel management-panel">
        <div className="management-loading" aria-live="polite">
          กำลังโหลดประวัติธุรกรรม…
        </div>
      </section>
    );
  return (
    <div className="transactions-layout" aria-busy={loading}>
      <section
        className="panel transaction-section"
        aria-labelledby="subscription-history-title"
      >
        <div className="panel-head">
          <div>
            <h2 id="subscription-history-title">ประวัติการสมัครบริการ</h2>
            <small>การซื้อ ต่ออายุ และสถานะแพ็กเกจของร้าน</small>
          </div>
          <span className="history-count">
            {(history?.subscriptions.total ?? 0).toLocaleString("th-TH")} รายการ
          </span>
        </div>
        {subscriptions.length === 0 ? (
          <div className="empty">
            <ReceiptText size={28} />
            <strong>ยังไม่มีประวัติการสมัครบริการ</strong>
            <p>เมื่อซื้อแพ็กเกจหรือเริ่มรอบบริการใหม่ รายการจะแสดงที่นี่</p>
          </div>
        ) : (
          <>
            <div className="table-wrap transaction-table">
              <table>
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">แพ็กเกจ</th>
                    <th scope="col">วันที่เริ่มบริการ</th>
                    <th scope="col">วันที่หมดอายุ</th>
                    <th scope="col">เครดิตที่ใช้</th>
                    <th scope="col">สถานะ</th>
                    <th scope="col">ต่ออายุอัตโนมัติ</th>
                  </tr>
                </thead>
                <tbody>
                  {subscriptions.map((record, index) => (
                    <tr key={record.id}>
                      <td>{index + 1}</td>
                      <td>
                        <strong>{record.plan?.name ?? "ทดลองใช้"}</strong>
                        <br />
                        <small>
                          {record.plan
                            ? record.billing_cycle === "yearly"
                              ? "รายปี"
                              : "รายเดือน"
                            : "สิทธิ์เริ่มต้น"}
                        </small>
                      </td>
                      <td>
                        {optionalDate(record.starts_at ?? record.created_at)}
                      </td>
                      <td>{optionalDate(record.ends_at)}</td>
                      <td>
                        <strong>
                          {record.plan
                            ? Number(
                                record.price_paid ?? record.plan.price_monthly,
                              ).toLocaleString("th-TH")
                            : "0"}
                        </strong>{" "}
                        เครดิต
                      </td>
                      <td>
                        <span className={`status ${record.status}`}>
                          {subscriptionHistoryLabels[record.status] ??
                            record.status}
                        </span>
                      </td>
                      <td>
                        <span
                          className={`renewal-state ${record.auto_renew ? "on" : "off"}`}
                        >
                          {record.auto_renew ? "เปิด" : "ปิด"}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="transaction-mobile-list">
              {subscriptions.map((record, index) => (
                <article className="transaction-card" key={record.id}>
                  <div className="transaction-card-head">
                    <span>
                      #{index + 1} · {record.plan?.name ?? "ทดลองใช้"}
                    </span>
                    <span className={`status ${record.status}`}>
                      {subscriptionHistoryLabels[record.status] ??
                        record.status}
                    </span>
                  </div>
                  <dl>
                    <div>
                      <dt>เริ่มบริการ</dt>
                      <dd>
                        {optionalDate(record.starts_at ?? record.created_at)}
                      </dd>
                    </div>
                    <div>
                      <dt>หมดอายุ</dt>
                      <dd>{optionalDate(record.ends_at)}</dd>
                    </div>
                    <div>
                      <dt>เครดิตที่ใช้</dt>
                      <dd>
                        {record.plan
                          ? Number(
                              record.price_paid ?? record.plan.price_monthly,
                            ).toLocaleString("th-TH")
                          : "0"}{" "}
                        เครดิต
                      </dd>
                    </div>
                    <div>
                      <dt>ต่ออายุอัตโนมัติ</dt>
                      <dd>{record.auto_renew ? "เปิด" : "ปิด"}</dd>
                    </div>
                  </dl>
                </article>
              ))}
            </div>
          </>
        )}
        <div className="pagination">
          <span>
            แสดงล่าสุด {subscriptions.length.toLocaleString("th-TH")} รายการ
          </span>
        </div>
      </section>
      <section
        className="panel transaction-section"
        aria-labelledby="top-up-history-title"
      >
        <div className="panel-head">
          <div>
            <h2 id="top-up-history-title">ประวัติการเติมเครดิต</h2>
            <small>รายการส่งสลิปและผลการตรวจสอบเครดิต</small>
          </div>
          <span className="history-count">
            {(history?.top_ups.total ?? 0).toLocaleString("th-TH")} รายการ
          </span>
        </div>
        {topUps.length === 0 ? (
          <div className="empty">
            <WalletCards size={28} />
            <strong>ยังไม่มีประวัติการเติมเครดิต</strong>
            <p>รายการที่ส่งจากหน้าแพ็กเกจจะแสดงสถานะการตรวจสอบที่นี่</p>
          </div>
        ) : (
          <>
            <div className="table-wrap transaction-table">
              <table>
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">วันที่เติม</th>
                    <th scope="col">จำนวนเครดิต</th>
                    <th scope="col">ผู้ส่งรายการ</th>
                    <th scope="col">สถานะ</th>
                    <th scope="col">วันที่อนุมัติ</th>
                    <th scope="col">หมายเหตุ</th>
                  </tr>
                </thead>
                <tbody>
                  {topUps.map((record, index) => (
                    <tr key={record.id}>
                      <td>{index + 1}</td>
                      <td>{formatDate(record.created_at)}</td>
                      <td>
                        <strong>
                          {record.credits.toLocaleString("th-TH")}
                        </strong>{" "}
                        เครดิต
                      </td>
                      <td>{record.submitted_by?.name ?? "–"}</td>
                      <td>
                        <span className={`status ${record.status}`}>
                          {topUpHistoryLabels[record.status] ?? record.status}
                        </span>
                      </td>
                      <td>{optionalDate(record.verified_at)}</td>
                      <td className="transaction-note">
                        {record.review_note ?? "–"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="transaction-mobile-list">
              {topUps.map((record, index) => (
                <article className="transaction-card" key={record.id}>
                  <div className="transaction-card-head">
                    <span>
                      #{index + 1} · {record.credits.toLocaleString("th-TH")}{" "}
                      เครดิต
                    </span>
                    <span className={`status ${record.status}`}>
                      {topUpHistoryLabels[record.status] ?? record.status}
                    </span>
                  </div>
                  <dl>
                    <div>
                      <dt>วันที่เติม</dt>
                      <dd>{formatDate(record.created_at)}</dd>
                    </div>
                    <div>
                      <dt>ผู้ส่งรายการ</dt>
                      <dd>{record.submitted_by?.name ?? "–"}</dd>
                    </div>
                    <div>
                      <dt>วันที่อนุมัติ</dt>
                      <dd>{optionalDate(record.verified_at)}</dd>
                    </div>
                    <div>
                      <dt>หมายเหตุ</dt>
                      <dd>{record.review_note ?? "–"}</dd>
                    </div>
                  </dl>
                </article>
              ))}
            </div>
          </>
        )}
        <div className="pagination">
          <span>แสดงล่าสุด {topUps.length.toLocaleString("th-TH")} รายการ</span>
        </div>
      </section>
    </div>
  );
}

const subscriptionHistoryLabels: Record<string, string> = {
  trialing: "ทดลองใช้",
  pending_payment: "รอชำระเงิน",
  active: "ใช้งานอยู่",
  expired: "หมดอายุ",
  grace_read_only: "ช่วงผ่อนผัน",
  suspended: "ระงับการใช้งาน",
  cancelled: "ยกเลิกแล้ว",
};

const topUpHistoryLabels: Record<string, string> = {
  pending: "รอตรวจสอบ",
  pending_review: "รอแอดมินตรวจ",
  verified: "อนุมัติแล้ว",
  rejected: "ปฏิเสธ",
};

const optionalDate = (value: string | null) =>
  value ? formatDate(value) : "–";
