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
  TrendingUp,
  WalletCards,
} from "lucide-react";
import { formatDate, money } from "../../shared/lib/format";
import { activityIcon, activityLabel } from "../../shared/lib/activity";
import type { DashboardData, ViewSeries } from "../../types/models";

type Granularity = "day" | "month" | "year";

export function DashboardPanel({
  dashboard,
  summary,
  canViewProfit,
  canViewAnalytics,
  storefrontViews,
  viewGranularity,
  onViewGranularityChange,
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
  onOpenInventory: () => void;
  onOpenImport: () => void;
  onOpenAdd: () => void;
  onRefresh: () => void;
}) {
  const trend = dashboard?.sales_last_7_days ?? [];
  const maxRevenue = Math.max(1, ...trend.map((point) => point.revenue));
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
        : {
            title: `มี ${summary.available} ไอดีพร้อมขาย`,
            detail: "ตรวจสอบราคาและรายละเอียดให้พร้อมก่อนส่งต่อให้ลูกค้า",
            label: "เปิดคลังไอดี",
            action: onOpenInventory,
          };
  return (
    <div className="shop-dashboard">
      <section
        className="dashboard-snapshot"
        aria-labelledby="dashboard-snapshot-title"
      >
        <div>
          <span className="eyebrow">SHOP PULSE · เดือนนี้</span>
          <h2 id="dashboard-snapshot-title">ภาพรวมที่ช่วยให้ตัดสินใจได้เร็ว</h2>
          <p>ติดตามสต็อก การจอง และยอดขายจากข้อมูลของร้านแบบล่าสุด</p>
        </div>
        <button className="button" onClick={onRefresh}>
          <RefreshCw size={17} />
          รีเฟรชข้อมูล
        </button>
      </section>
      <section className="dashboard-stats" aria-label="ตัวเลขภาพรวมร้าน">
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
          label="ยอดขายเดือนนี้"
          value={money.format(
            Number(dashboard?.summary.revenue_this_month ?? 0),
          )}
          detail={`${summary.sold.toLocaleString("th-TH")} รายการที่ปิดการขาย`}
          icon={<WalletCards size={18} />}
        />
        <DashboardMetric
          label="กำไรเดือนนี้"
          value={
            canViewProfit
              ? money.format(Number(dashboard?.summary.profit_this_month ?? 0))
              : "–"
          }
          detail={
            canViewProfit
              ? "คำนวณจากยอดขายเดือนปัจจุบัน"
              : "ไม่มีสิทธิ์ดูต้นทุนและกำไร"
          }
          icon={<TrendingUp size={18} />}
        />
        <DashboardMetric
          label="ยอดเข้าชมร้าน"
          value={
            canViewAnalytics
              ? Number(dashboard?.summary.storefront_views ?? 0).toLocaleString(
                  "th-TH",
                )
              : "—"
          }
          detail={
            canViewAnalytics
              ? "รวมจากหน้าร้านสาธารณะ"
              : "ปลดล็อกด้วยแพ็ก Growth ขึ้นไป"
          }
          icon={<Eye size={18} />}
        />
      </section>
      <div className="dashboard-main-grid">
        <section
          className="panel dashboard-trend"
          aria-labelledby="trend-title"
        >
          <div className="panel-head">
            <div>
              <h2 id="trend-title">ยอดขาย 7 วันล่าสุด</h2>
              <small>
                {money.format(
                  trend.reduce((total, point) => total + point.revenue, 0),
                )}{" "}
                ในรอบ 7 วัน
              </small>
            </div>
          </div>
          <div className="trend-body">
            {trend.length === 0 ? (
              <div className="dashboard-empty">
                <BarChart3 size={24} />
                <strong>ยังไม่มีข้อมูลยอดขายใน 7 วันล่าสุด</strong>
                <span>เมื่อบันทึกการขาย กราฟจะแสดงผลที่นี่</span>
              </div>
            ) : (
              <>
                <div className="trend-bars" aria-label="กราฟรายได้ 7 วันล่าสุด">
                  {trend.map((point) => {
                    const height = Math.max(
                      6,
                      Math.round((point.revenue / maxRevenue) * 100),
                    );
                    const label = new Intl.DateTimeFormat("th-TH", {
                      weekday: "short",
                      timeZone: "Asia/Bangkok",
                    }).format(new Date(`${point.date}T12:00:00`));
                    return (
                      <div className="trend-column" key={point.date}>
                        <span className="trend-tooltip">
                          {money.format(point.revenue)} · {point.sales} รายการ
                        </span>
                        <i style={{ height: `${height}%` }} />
                        <span>{label}</span>
                      </div>
                    );
                  })}
                </div>
                <p className="trend-summary">
                  รวม{" "}
                  <strong>
                    {trend
                      .reduce((total, point) => total + point.sales, 0)
                      .toLocaleString("th-TH")}{" "}
                    รายการ
                  </strong>{" "}
                  · ยอดขายจะอัปเดตหลังบันทึกขายสำเร็จ
                </p>
              </>
            )}
          </div>
        </section>
        <section className="panel dashboard-task" aria-labelledby="task-title">
          <div className="panel-head">
            <div>
              <h2 id="task-title">สิ่งที่ควรทำต่อ</h2>
              <small>จัดลำดับงานจากสต็อกปัจจุบัน</small>
            </div>
          </div>
          <div className="task-body">
            <span className="task-icon">
              <ClipboardCheck size={20} />
            </span>
            <h3>{task.title}</h3>
            <p>{task.detail}</p>
            <button className="button blue" onClick={task.action}>
              {task.label}
              <ChevronRight size={16} />
            </button>
          </div>
        </section>
      </div>
      <StorefrontViewsPanel
        series={storefrontViews}
        granularity={viewGranularity}
        onGranularity={onViewGranularityChange}
        canViewAnalytics={canViewAnalytics}
      />
      <div className="dashboard-lower-grid">
        <section
          className="panel stock-health"
          aria-labelledby="stock-health-title"
        >
          <div className="panel-head">
            <div>
              <h2 id="stock-health-title">สถานะคลัง</h2>
              <small>{stockTotal.toLocaleString("th-TH")} รายการทั้งหมด</small>
            </div>
          </div>
          <div className="stock-health-body">
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
        <section
          className="panel dashboard-subscription"
          aria-labelledby="subscription-title"
        >
          <div className="panel-head">
            <div>
              <h2 id="subscription-title">สถานะร้าน</h2>
              <small>สิทธิ์การใช้งานปัจจุบัน</small>
            </div>
          </div>
          <div className="subscription-body">
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
        </section>
      </div>
    </div>
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
  const fmtPeriod = (period: string): string => {
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
  };
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
              granularity === "day" ? "วัน" : granularity === "month" ? "เดือน" : "ปี"
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
                    {fmtPeriod(point.period)}
                  </span>
                  <i style={{ height: `${height}%` }} />
                  <span className="views-label">{fmtPeriod(point.period)}</span>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </section>
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
  detail: string;
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
