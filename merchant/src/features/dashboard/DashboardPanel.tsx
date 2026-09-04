import type { ReactNode } from "react";
import {
  BarChart3,
  Box,
  ChevronRight,
  ClipboardCheck,
  Clock3,
  Eye,
  PackagePlus,
  RefreshCw,
  TrendingDown,
  TrendingUp,
  WalletCards,
} from "lucide-react";
import { formatDate, money } from "../../shared/lib/format";
import { activityIcon, activityLabel } from "../../shared/lib/activity";
import type {
  DashboardData,
  SalesSeries,
  ViewSeries,
} from "../../types/models";

type Granularity = "day" | "month" | "year";

const RANGE_LABEL: Record<Granularity, string> = {
  day: "30 วันล่าสุด",
  month: "12 เดือนล่าสุด",
  year: "5 ปีล่าสุด",
};

export function DashboardPanel({
  dashboard,
  summary,
  canViewProfit,
  canViewAnalytics,
  storefrontViews,
  viewGranularity,
  onViewGranularityChange,
  salesReport,
  salesGranularity,
  onSalesGranularityChange,
  onOpenInventory,
  onOpenImport,
  onOpenAdd,
  onRefresh,
}: {
  dashboard: DashboardData | null;
  summary: {
    available: number;
    reserved: number;
    sold: number;
    soldTotal: number;
    value: number | null;
  };
  canViewProfit: boolean;
  canViewAnalytics: boolean;
  storefrontViews: ViewSeries | null;
  viewGranularity: Granularity;
  onViewGranularityChange: (g: Granularity) => void;
  salesReport: SalesSeries | null;
  salesGranularity: Granularity;
  onSalesGranularityChange: (g: Granularity) => void;
  onOpenInventory: () => void;
  onOpenImport: () => void;
  onOpenAdd: () => void;
  onRefresh: () => void;
}) {
  const stockTotal = summary.available + summary.reserved + summary.soldTotal;
  const daysUntilTrial = dashboard?.subscription.trial_ends_at
    ? Math.max(
        0,
        Math.ceil(
          (new Date(dashboard.subscription.trial_ends_at).getTime() -
            Date.now()) /
            86_400_000,
        ),
      )
    : null;
  // Revenue / profit KPIs track the chart's window when the report has loaded,
  // otherwise fall back to the always-present "this month" figures.
  const windowRevenue =
    salesReport?.totals.revenue ??
    Number(dashboard?.summary.revenue_this_month ?? 0);
  const windowProfit =
    salesReport?.totals.profit ??
    (canViewProfit ? Number(dashboard?.summary.profit_this_month ?? 0) : null);
  const revenueRange = salesReport ? RANGE_LABEL[salesGranularity] : "เดือนนี้";

  const task =
    summary.reserved > 0
      ? {
          title: `มี ${summary.reserved} รายการกำลังถูกจอง`,
          detail: "ตรวจสอบว่าลูกค้ายืนยันและปิดการขายหรือยกเลิกการจองแล้ว",
          label: "ดูรายการที่จอง",
          action: onOpenInventory,
        }
      : summary.available === 0
        ? {
            title: "ยังไม่มีไอดีพร้อมขาย",
            detail:
              "นำเข้า Excel/CSV หรือเพิ่มไอดีใหม่เพื่อเริ่มขายจากหน้าร้าน",
            label: "นำเข้าข้อมูล",
            action: onOpenImport,
          }
        : null;

  return (
    <div className="shop-dashboard">
      <section className="dashboard-stats-wrap" aria-label="ตัวเลขภาพรวมร้าน">
        <button
          className="dashboard-refresh"
          onClick={onRefresh}
          aria-label="รีเฟรชข้อมูล"
        >
          <RefreshCw size={15} />
        </button>
        <div className="dashboard-stats">
          <DashboardMetric
            label="พร้อมขาย"
            value={summary.available.toLocaleString("th-TH")}
            detail="รายการที่พร้อมเสนอขาย"
            icon={<Box size={18} />}
          />
          <DashboardMetric
            label="กำลังจอง"
            value={summary.reserved.toLocaleString("th-TH")}
            detail="ต้องติดตามก่อนหมดเวลา"
            icon={<Clock3 size={18} />}
          />
          <DashboardMetric
            label="ยอดขาย"
            value={money.format(windowRevenue)}
            detail={
              <DeltaLine
                range={revenueRange}
                current={salesReport?.totals.revenue}
                previous={salesReport?.previous.revenue}
              />
            }
            icon={<WalletCards size={18} />}
          />
          <DashboardMetric
            label="กำไร"
            value={canViewProfit ? money.format(windowProfit ?? 0) : "–"}
            detail={
              canViewProfit ? (
                <DeltaLine
                  range={revenueRange}
                  current={salesReport?.totals.profit ?? undefined}
                  previous={salesReport?.previous.profit ?? undefined}
                />
              ) : (
                "ไม่มีสิทธิ์ดูต้นทุนและกำไร"
              )
            }
            icon={<TrendingUp size={18} />}
          />
          <DashboardMetric
            label="ยอดเข้าชมร้าน"
            value={
              canViewAnalytics
                ? Number(
                    dashboard?.summary.storefront_views ?? 0,
                  ).toLocaleString("th-TH")
                : "—"
            }
            detail={
              canViewAnalytics
                ? "รวมจากหน้าร้านสาธารณะ"
                : "ปลดล็อกด้วยแพ็ก Growth ขึ้นไป"
            }
            icon={<Eye size={18} />}
          />
        </div>
      </section>

      {task && (
        <section className="dashboard-task-strip" aria-labelledby="task-title">
          <span className="task-icon">
            <ClipboardCheck size={18} />
          </span>
          <div>
            <h2 id="task-title">{task.title}</h2>
            <p>{task.detail}</p>
          </div>
          <button className="button blue" onClick={task.action}>
            {task.label}
            <ChevronRight size={16} />
          </button>
        </section>
      )}

      <div className="dashboard-grid">
        <div className="dashboard-col dashboard-col-charts">
          <SalesTrendPanel
            series={salesReport}
            granularity={salesGranularity}
            onGranularity={onSalesGranularityChange}
          />
          <StorefrontViewsPanel
            series={storefrontViews}
            granularity={viewGranularity}
            onGranularity={onViewGranularityChange}
            canViewAnalytics={canViewAnalytics}
          />
        </div>
        <div className="dashboard-col dashboard-col-side">
        <section className="panel stock-plan" aria-labelledby="stock-plan-title">
          <div className="panel-head">
            <div>
              <h2 id="stock-plan-title">สถานะคลังและร้าน</h2>
              <small>{stockTotal.toLocaleString("th-TH")} รายการทั้งหมด</small>
            </div>
          </div>
          <div className="stock-plan-body">
            <div
              className="stock-track"
              aria-label={`พร้อมขาย ${summary.available} รายการ, ถูกจอง ${summary.reserved} รายการ, ขายแล้ว ${summary.soldTotal} รายการ`}
            >
              <i
                className="stock-available"
                style={{
                  width: `${stockTotal ? (summary.available / stockTotal) * 100 : 0}%`,
                }}
              />
              <i
                className="stock-reserved"
                style={{
                  width: `${stockTotal ? (summary.reserved / stockTotal) * 100 : 0}%`,
                }}
              />
              <i
                className="stock-sold"
                style={{
                  width: `${stockTotal ? (summary.soldTotal / stockTotal) * 100 : 0}%`,
                }}
              />
            </div>
            <div className="stock-legend">
              <span>
                <i className="stock-available" />
                พร้อมขาย <strong>{summary.available}</strong>
              </span>
              <span>
                <i className="stock-reserved" />
                ถูกจอง <strong>{summary.reserved}</strong>
              </span>
              <span>
                <i className="stock-sold" />
                ขายแล้ว <strong>{summary.soldTotal}</strong>
              </span>
            </div>
            {canViewProfit && (
              <div className="stock-value">
                <span>มูลค่าทุนของไอดีที่ยังไม่ขาย</span>
                <strong>{money.format(Number(summary.value ?? 0))}</strong>
              </div>
            )}
            <div className="stock-plan-divider" />
            <div className="subscription-inline">
              <span
                className={`subscription-state ${dashboard?.subscription.writable === false ? "limited" : "active"}`}
              >
                {dashboard?.subscription.writable === false
                  ? "อ่านอย่างเดียว"
                  : "พร้อมใช้งาน"}
              </span>
              <h3>
                {dashboard?.subscription.status === "trialing"
                  ? "ช่วงทดลองใช้ฟรี"
                  : "แพ็กเกจร้านค้า"}
              </h3>
              <p>
                {daysUntilTrial !== null
                  ? `เหลือเวลาใช้งานแบบเขียนข้อมูล ${daysUntilTrial.toLocaleString("th-TH")} วัน`
                  : "ตรวจสอบแพ็กเกจและการชำระเงินได้จากเมนูจัดการร้าน"}
              </p>
              <button className="button" onClick={onOpenAdd}>
                <PackagePlus size={16} />
                เพิ่มไอดีใหม่
              </button>
            </div>
          </div>
        </section>
        <section
          className="panel dashboard-activity"
          aria-labelledby="dashboard-activity-title"
        >
          <div className="panel-head">
            <div>
              <h2 id="dashboard-activity-title">การเคลื่อนไหวล่าสุด</h2>
              <small>อัปเดตจากรายการจริงในร้าน</small>
            </div>
          </div>
          <div className="activity-list">
            {dashboard?.activity.length ? (
              dashboard.activity.map((activity) => (
                <Activity
                  key={activity.id}
                  icon={activityIcon(activity.event)}
                  text={activityLabel(activity.event)}
                  time={formatDate(activity.created_at)}
                />
              ))
            ) : (
              <div className="dashboard-empty compact">
                <PackagePlus size={22} />
                <strong>ยังไม่มีการเคลื่อนไหว</strong>
                <span>เริ่มจากเพิ่มไอดีหรือนำเข้า Excel/CSV</span>
              </div>
            )}
          </div>
        </section>
        </div>
      </div>
    </div>
  );
}

function fmtPeriod(period: string, granularity: Granularity): string {
  if (granularity === "year") return String(Number(period) + 543);
  if (granularity === "month") {
    const [y, m] = period.split("-").map(Number);
    return new Intl.DateTimeFormat("th-TH", { month: "short" }).format(
      new Date(y, m - 1, 1),
    );
  }
  return new Intl.DateTimeFormat("th-TH", {
    day: "numeric",
    month: "numeric",
  }).format(new Date(`${period}T12:00:00`));
}

function GranularityTabs({
  granularity,
  onGranularity,
}: {
  granularity: Granularity;
  onGranularity: (g: Granularity) => void;
}) {
  return (
    <div className="cycle-toggle" role="tablist" aria-label="ช่วงเวลา">
      {(["day", "month", "year"] as Granularity[]).map((g) => (
        <button
          key={g}
          type="button"
          role="tab"
          aria-selected={granularity === g}
          className={granularity === g ? "is-on" : ""}
          onClick={() => onGranularity(g)}
        >
          {g === "day" ? "วัน" : g === "month" ? "เดือน" : "ปี"}
        </button>
      ))}
    </div>
  );
}

function SalesTrendPanel({
  series,
  granularity,
  onGranularity,
}: {
  series: SalesSeries | null;
  granularity: Granularity;
  onGranularity: (g: Granularity) => void;
}) {
  const points = series?.data ?? [];
  const maxRevenue = Math.max(1, ...points.map((p) => p.revenue));
  const empty = points.length === 0 || points.every((p) => p.revenue === 0);

  return (
    <section className="panel dashboard-sales" aria-labelledby="sales-title">
      <div className="panel-head">
        <div>
          <h2 id="sales-title">ยอดขาย</h2>
          <small>
            {money.format(series?.totals.revenue ?? 0)} · {RANGE_LABEL[granularity]}{" "}
            · {(series?.totals.sales ?? 0).toLocaleString("th-TH")} รายการ
          </small>
        </div>
        <GranularityTabs granularity={granularity} onGranularity={onGranularity} />
      </div>
      <div className="views-body">
        {empty ? (
          <div className="dashboard-empty">
            <BarChart3 size={24} />
            <strong>ยังไม่มียอดขายในช่วงนี้</strong>
            <span>เมื่อบันทึกการขาย กราฟจะแสดงผลที่นี่</span>
          </div>
        ) : (
          <div
            className={`sales-bars gran-${granularity}`}
            aria-label={`กราฟยอดขายราย${
              granularity === "day"
                ? "วัน"
                : granularity === "month"
                  ? "เดือน"
                  : "ปี"
            }`}
          >
            {points.map((point) => {
              const height = Math.max(
                4,
                Math.round((point.revenue / maxRevenue) * 100),
              );
              const profitPct =
                point.profit != null && point.revenue > 0
                  ? Math.min(
                      100,
                      Math.round((point.profit / point.revenue) * 100),
                    )
                  : null;
              return (
                <div className="sales-column" key={point.period}>
                  <span className="sales-tooltip">
                    {money.format(point.revenue)}
                    {point.profit != null &&
                      ` · กำไร ${money.format(point.profit)}`}
                    {` · ${point.sales.toLocaleString("th-TH")} รายการ · ${fmtPeriod(
                      point.period,
                      granularity,
                    )}`}
                  </span>
                  <i style={{ height: `${height}%` }}>
                    {profitPct != null && (
                      <b style={{ height: `${profitPct}%` }} />
                    )}
                  </i>
                  <span className="views-label">
                    {fmtPeriod(point.period, granularity)}
                  </span>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}

function StorefrontViewsPanel({
  series,
  granularity,
  onGranularity,
  canViewAnalytics,
}: {
  series: ViewSeries | null;
  granularity: Granularity;
  onGranularity: (g: Granularity) => void;
  canViewAnalytics: boolean;
}) {
  const points = series?.data ?? [];
  const maxViews = Math.max(1, ...points.map((p) => p.views));
  const empty = points.length === 0 || points.every((p) => p.views === 0);

  return (
    <section className="panel dashboard-views" aria-labelledby="views-title">
      <div className="panel-head">
        <div>
          <h2 id="views-title">ยอดเข้าชมร้าน</h2>
          <small>
            {canViewAnalytics
              ? `${(series?.total ?? 0).toLocaleString("th-TH")} ครั้ง รวมทั้งหมด`
              : "ปลดล็อกด้วยแพ็ก Growth ขึ้นไป"}
          </small>
        </div>
        {canViewAnalytics && (
          <GranularityTabs
            granularity={granularity}
            onGranularity={onGranularity}
          />
        )}
      </div>
      <div className="views-body">
        {!canViewAnalytics ? (
          <div className="dashboard-empty">
            <Eye size={24} />
            <strong>รายงานยอดเข้าชมสำหรับแพ็ก Growth ขึ้นไป</strong>
            <span>อัปเกรดเพื่อดูกราฟยอดเข้าชมรายวัน / เดือน / ปี</span>
          </div>
        ) : empty ? (
          <div className="dashboard-empty">
            <Eye size={24} />
            <strong>ยังไม่มียอดเข้าชมในช่วงนี้</strong>
            <span>เมื่อมีคนเปิดหน้าร้านสาธารณะ กราฟจะแสดงที่นี่</span>
          </div>
        ) : (
          <div
            className={`views-bars gran-${granularity}`}
            aria-label={`กราฟยอดเข้าชมราย${
              granularity === "day"
                ? "วัน"
                : granularity === "month"
                  ? "เดือน"
                  : "ปี"
            }`}
          >
            {points.map((point) => {
              const height = Math.max(
                4,
                Math.round((point.views / maxViews) * 100),
              );
              return (
                <div className="views-column" key={point.period}>
                  <span className="views-tooltip">
                    {point.views.toLocaleString("th-TH")} ครั้ง ·{" "}
                    {fmtPeriod(point.period, granularity)}
                  </span>
                  <i style={{ height: `${height}%` }} />
                  <span className="views-label">
                    {fmtPeriod(point.period, granularity)}
                  </span>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}

function DeltaLine({
  range,
  current,
  previous,
}: {
  range: string;
  current: number | undefined;
  previous: number | undefined;
}) {
  if (current == null || previous == null || previous === 0) {
    return <>{range}</>;
  }
  const change = Math.round(((current - previous) / previous) * 100);
  const dir = change > 0 ? "up" : change < 0 ? "down" : "flat";
  return (
    <span className="kpi-delta">
      {range} ·
      <span className={dir}>
        {dir === "up" ? (
          <TrendingUp size={12} />
        ) : dir === "down" ? (
          <TrendingDown size={12} />
        ) : null}
        {change > 0 ? "+" : ""}
        {change}% เทียบช่วงก่อน
      </span>
    </span>
  );
}

function DashboardMetric({
  label,
  value,
  detail,
  icon,
}: {
  label: string;
  value: string;
  detail: ReactNode;
  icon: ReactNode;
}) {
  return (
    <article className="dashboard-metric">
      <div className="dashboard-metric-icon">{icon}</div>
      <div>
        <span>{label}</span>
        <strong>{value}</strong>
        <small>{detail}</small>
      </div>
    </article>
  );
}
export function Kpi({
  label,
  value,
  note,
  icon,
  positive = false,
}: {
  label: string;
  value: string;
  note: string;
  icon: ReactNode;
  positive?: boolean;
}) {
  return (
    <div className="kpi">
      <div className="kpi-label">
        <span>{label}</span>
        {icon}
      </div>
      <div className="kpi-value">{value}</div>
      <div className={`kpi-note ${positive ? "positive" : ""}`}>{note}</div>
    </div>
  );
}
export function Activity({
  icon,
  text,
  time,
}: {
  icon: ReactNode;
  text: string;
  time: string;
}) {
  return (
    <div className="activity-item">
      <span className="activity-icon">{icon}</span>
      <div>
        <p>{text}</p>
        <time>{time}</time>
      </div>
    </div>
  );
}
