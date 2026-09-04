import { useCallback, useEffect, useState } from "react";
import { RefreshCw } from "lucide-react";
import { shopRequest } from "../../api";
import { AsyncError } from "../../shared/ui/async-state";
import { formatDate, money } from "../../shared/lib/format";
import type { AnalyticsReport } from "../../types/models";

function ymd(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(
    date.getDate(),
  ).padStart(2, "0")}`;
}

const MONTH_START = ymd(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const TODAY = ymd(new Date());

export function AnalyticsPanel({
  shopId,
  canViewProfit,
}: {
  shopId: number;
  canViewProfit: boolean;
}) {
  const [from, setFrom] = useState(MONTH_START);
  const [to, setTo] = useState(TODAY);
  const [data, setData] = useState<AnalyticsReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(
    async (range?: { from: string; to: string }) => {
      const f = range?.from ?? from;
      const t = range?.to ?? to;
      setLoading(true);
      setError("");
      try {
        const result = await shopRequest<AnalyticsReport>(
          `/reports/analytics?from=${f}&to=${t}`,
          shopId,
        );
        setData(result);
      } catch (reason) {
        setError(
          reason instanceof Error ? reason.message : "โหลดรายงานไม่สำเร็จ",
        );
      } finally {
        setLoading(false);
      }
    },
    [from, to, shopId],
  );

  useEffect(() => {
    void load({ from: MONTH_START, to: TODAY });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [shopId]);

  const summary = data?.summary;

  return (
    <section
      className="panel management-panel analytics-panel"
      aria-labelledby="analytics-title"
    >
      <div className="panel-head">
        <div>
          <h2 id="analytics-title">รายงานเชิงลึก</h2>
          <small>เจาะยอดขายตามแรงก์ ช่วงราคา ทีมขาย และลูกค้า</small>
        </div>
        <button
          className="button"
          onClick={() => void load()}
          disabled={loading}
        >
          <RefreshCw size={16} />
          รีเฟรช
        </button>
      </div>

      <div className="analytics-filter">
        <label>
          <span>ตั้งแต่</span>
          <input
            type="date"
            value={from}
            max={to || TODAY}
            onChange={(e) => setFrom(e.target.value)}
          />
        </label>
        <label>
          <span>ถึง</span>
          <input
            type="date"
            value={to}
            min={from || undefined}
            max={TODAY}
            onChange={(e) => setTo(e.target.value)}
          />
        </label>
        <button
          className="button primary"
          onClick={() => void load()}
          disabled={loading}
        >
          ดูรายงาน
        </button>
      </div>

      {error ? (
        <AsyncError error={error} retry={() => void load()} />
      ) : !data || !summary ? (
        <div className="dashboard-empty">
          <strong>กำลังโหลดรายงาน…</strong>
        </div>
      ) : (
        <>
          <div className="analytics-kpis">
            <Kpi label="ยอดขาย" value={money.format(summary.revenue)} />
            <Kpi
              label="จำนวนที่ขาย"
              value={`${summary.sales.toLocaleString("th-TH")} รายการ`}
            />
            <Kpi
              label="ราคาเฉลี่ย/รายการ"
              value={money.format(summary.avg_price)}
            />
            <Kpi
              label="กำไร"
              value={
                canViewProfit && summary.profit != null
                  ? money.format(summary.profit)
                  : "—"
              }
            />
            <Kpi
              label="อัตรากำไร"
              value={summary.margin_pct != null ? `${summary.margin_pct}%` : "—"}
            />
            <Kpi
              label="วันเฉลี่ยกว่าจะขายได้"
              value={
                summary.avg_days_to_sell != null
                  ? `${summary.avg_days_to_sell.toLocaleString("th-TH")} วัน`
                  : "—"
              }
            />
          </div>

          {summary.sales === 0 ? (
            <div className="dashboard-empty">
              <strong>ยังไม่มียอดขายในช่วงที่เลือก</strong>
              <span>ลองขยายช่วงวันที่ด้านบน</span>
            </div>
          ) : (
            <div className="analytics-grid">
              <BarCard
                title="ยอดขายตามแรงก์"
                rows={data.by_rank.map((r) => ({
                  label: r.label,
                  revenue: r.revenue,
                  note: `${r.sales.toLocaleString("th-TH")} รายการ`,
                }))}
              />
              <BarCard
                title="ยอดขายตามช่วงราคา"
                rows={data.by_price_band.map((b) => ({
                  label: b.label,
                  revenue: b.revenue,
                  note: `${b.sales.toLocaleString("th-TH")} รายการ`,
                }))}
              />
              <div className="panel analytics-card">
                <div className="panel-head">
                  <h3>ผลงานทีมขาย</h3>
                </div>
                <table className="analytics-table">
                  <thead>
                    <tr>
                      <th>ผู้ขาย</th>
                      <th>จำนวน</th>
                      <th>ยอดขาย</th>
                      {canViewProfit && <th>กำไร</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {data.by_staff.map((row) => (
                      <tr key={row.id ?? row.name}>
                        <td>{row.name}</td>
                        <td>{row.sales.toLocaleString("th-TH")}</td>
                        <td>{money.format(row.revenue)}</td>
                        {canViewProfit && (
                          <td>
                            {row.profit != null
                              ? money.format(row.profit)
                              : "—"}
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="panel analytics-card">
                <div className="panel-head">
                  <h3>ลูกค้าที่ซื้อมากที่สุด</h3>
                </div>
                {data.top_customers.length === 0 ? (
                  <p className="analytics-card-empty">
                    ยังไม่มีการขายที่ผูกกับลูกค้าในช่วงนี้
                  </p>
                ) : (
                  <table className="analytics-table">
                    <thead>
                      <tr>
                        <th>ลูกค้า</th>
                        <th>จำนวน</th>
                        <th>ยอดรวม</th>
                        <th>ซื้อล่าสุด</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.top_customers.map((row) => (
                        <tr key={row.id}>
                          <td>{row.name}</td>
                          <td>{row.sales.toLocaleString("th-TH")}</td>
                          <td>{money.format(row.revenue)}</td>
                          <td>{formatDate(row.last_bought_at)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}
        </>
      )}
    </section>
  );
}

function Kpi({ label, value }: { label: string; value: string }) {
  return (
    <div className="analytics-kpi">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function BarCard({
  title,
  rows,
}: {
  title: string;
  rows: Array<{ label: string; revenue: number; note: string }>;
}) {
  const max = Math.max(1, ...rows.map((r) => r.revenue));
  const shown = rows.filter((r) => r.revenue > 0);
  return (
    <div className="panel analytics-card">
      <div className="panel-head">
        <h3>{title}</h3>
      </div>
      {shown.length === 0 ? (
        <p className="analytics-card-empty">ยังไม่มีข้อมูลในช่วงนี้</p>
      ) : (
        <ul className="analytics-bar-list">
          {shown.map((row) => (
            <li key={row.label}>
              <div className="analytics-bar-head">
                <span>{row.label}</span>
                <strong>{money.format(row.revenue)}</strong>
              </div>
              <div className="analytics-bar-track">
                <i style={{ width: `${Math.round((row.revenue / max) * 100)}%` }} />
              </div>
              <small>{row.note}</small>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
