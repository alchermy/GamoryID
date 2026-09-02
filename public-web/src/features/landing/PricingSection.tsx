import { useEffect, useState } from "react";
import { ArrowRight, Check, Minus } from "lucide-react";
import { apiBaseUrl, merchantRegisterUrl } from "../../config/links";
import { CountUp, Reveal } from "./Motion";
import { Mascot } from "./Mascot";

type Cycle = "monthly" | "yearly";

type FeatureKey =
  | "bulk_import"
  | "advanced_export"
  | "activity_log"
  | "discord"
  | "analytics"
  | "early_access"
  | "priority_support";

type ApiPlan = {
  code: string;
  name: string;
  sort_order: number;
  is_free: boolean;
  price_monthly: number;
  price_yearly: number | null;
  sale_price_monthly: number | null;
  sale_price_yearly: number | null;
  sale_label: string | null;
  active_inventory_limit: number | null;
  member_limit: number | null;
  features: Record<FeatureKey, boolean>;
};

/**
 * Fallback catalogue — mirrors the backend default plans. Shown only if the
 * public plans API is unreachable at build/runtime.
 */
const FALLBACK_PLANS: ApiPlan[] = [
  {
    code: "free",
    name: "Free",
    sort_order: 0,
    is_free: true,
    price_monthly: 0,
    price_yearly: null,
    sale_price_monthly: null,
    sale_price_yearly: null,
    sale_label: null,
    active_inventory_limit: 30,
    member_limit: 1,
    features: feats(),
  },
  {
    code: "starter",
    name: "Starter",
    sort_order: 1,
    is_free: false,
    price_monthly: 299,
    price_yearly: 2990,
    sale_price_monthly: null,
    sale_price_yearly: null,
    sale_label: null,
    active_inventory_limit: 150,
    member_limit: 3,
    features: feats("bulk_import", "activity_log"),
  },
  {
    code: "growth",
    name: "Growth",
    sort_order: 2,
    is_free: false,
    price_monthly: 690,
    price_yearly: 6900,
    sale_price_monthly: null,
    sale_price_yearly: null,
    sale_label: null,
    active_inventory_limit: 1000,
    member_limit: 8,
    features: feats(
      "bulk_import",
      "activity_log",
      "advanced_export",
      "discord",
      "analytics",
      "early_access",
    ),
  },
  {
    code: "pro",
    name: "Pro",
    sort_order: 3,
    is_free: false,
    price_monthly: 1490,
    price_yearly: 14900,
    sale_price_monthly: null,
    sale_price_yearly: null,
    sale_label: null,
    active_inventory_limit: 10000,
    member_limit: null,
    features: feats(
      "bulk_import",
      "activity_log",
      "advanced_export",
      "discord",
      "analytics",
      "early_access",
      "priority_support",
    ),
  },
];

function feats(...on: FeatureKey[]): Record<FeatureKey, boolean> {
  const keys: FeatureKey[] = [
    "bulk_import",
    "advanced_export",
    "activity_log",
    "discord",
    "analytics",
    "early_access",
    "priority_support",
  ];
  return Object.fromEntries(
    keys.map((k) => [k, on.includes(k)]),
  ) as Record<FeatureKey, boolean>;
}

const TAG_BY_CODE: Record<string, string> = {
  free: "เริ่มต้นใช้งาน",
  starter: "พ่อค้าคนเดียว",
  growth: "ร้านมีทีม",
  pro: "ร้านใหญ่",
};

const FEATURE_ROWS: { key: FeatureKey; label: string }[] = [
  { key: "bulk_import", label: "นำเข้า Excel/CSV แบบชุด" },
  { key: "activity_log", label: "บันทึกกิจกรรม (ตรวจย้อนหลัง)" },
  { key: "discord", label: "เชื่อมต่อ Discord" },
  { key: "advanced_export", label: "ส่งออกยอดขาย/กำไร/ประวัติ" },
  { key: "analytics", label: "วิเคราะห์ต้นทุน–กำไร / รายงานลึก" },
  { key: "early_access", label: "ได้ฟีเจอร์ใหม่ก่อนใคร" },
];

export function PricingSection() {
  const [cycle, setCycle] = useState<Cycle>("monthly");
  const [plans, setPlans] = useState<ApiPlan[]>(FALLBACK_PLANS);

  useEffect(() => {
    const controller = new AbortController();
    fetch(`${apiBaseUrl}/public/plans`, {
      signal: controller.signal,
      headers: { Accept: "application/json" },
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((body: { data?: ApiPlan[] }) => {
        if (Array.isArray(body.data) && body.data.length) {
          setPlans(
            [...body.data].sort((a, b) => a.sort_order - b.sort_order),
          );
        }
      })
      .catch(() => {
        /* keep the fallback catalogue */
      });
    return () => controller.abort();
  }, []);

  return (
    <>
      <section className="section container" id="pricing">
        <Reveal className="section-head centered">
          <span className="kicker">
            <span className="kicker-tick" aria-hidden="true" />
            เริ่มเล็ก ขยายตามร้าน
          </span>
          <h2>เลือกแพ็กที่ใช่กับร้านคุณ</h2>
          <p>
            ทดลองใช้ฟรี 14 วันเต็มทุกฟีเจอร์ระดับ Growth หลังจากนั้นเลือกแพ็กเกจ
            ตามจำนวนสต็อกและทีมของคุณ ปรับขึ้น–ลงได้ทุกเดือน
          </p>
          <div className="cycle-switch" role="tablist" aria-label="รอบชำระ">
            {(["monthly", "yearly"] as Cycle[]).map((c) => (
              <button
                key={c}
                type="button"
                role="tab"
                aria-selected={cycle === c}
                className={cycle === c ? "is-on" : ""}
                onClick={() => setCycle(c)}
              >
                {c === "monthly" ? "รายเดือน" : "รายปี · ประหยัด 2 เดือน"}
              </button>
            ))}
          </div>
        </Reveal>

        <div className="price-wrap price-wrap-4">
          {plans.map((plan, i) => (
            <Reveal key={plan.code} delay={i * 70}>
              <PriceCard plan={plan} cycle={cycle} />
            </Reveal>
          ))}
        </div>
      </section>

      <Reveal className="container cta">
        <div className="cta-glow" aria-hidden="true" />
        <div className="cta-copy">
          <h2>พร้อมจัดร้านให้เป็นระบบหรือยัง?</h2>
          <p>เริ่มนำเข้าสต็อก ค้นหา และบันทึกขายได้วันนี้</p>
          <a href={merchantRegisterUrl} className="btn btn-lg btn-on-cta">
            เริ่มทดลองใช้ฟรี <ArrowRight size={17} />
          </a>
        </div>
        <Mascot
          pose="hello"
          alt="Gammy ชวนเปิดร้าน"
          width={210}
          className="cta-mascot"
        />
      </Reveal>
    </>
  );
}

function PriceCard({ plan, cycle }: { plan: ApiPlan; cycle: Cycle }) {
  const listPrice =
    cycle === "yearly" ? plan.price_yearly : plan.price_monthly;
  const salePrice =
    cycle === "yearly" ? plan.sale_price_yearly : plan.sale_price_monthly;
  const amount = salePrice ?? listPrice ?? 0;
  const recommended = plan.code === "growth";
  const soldThisCycle = plan.is_free || listPrice != null;

  const limitLabel = (v: number | null, unit: string) =>
    v == null ? "ไม่จำกัด" : `${v.toLocaleString("th-TH")} ${unit}`;

  return (
    <article className={`price-card ${recommended ? "is-top" : ""}`}>
      {recommended && <span className="price-flag">แนะนำ</span>}
      <span className="price-tag">
        {TAG_BY_CODE[plan.code] ?? plan.name}
      </span>
      <h3>{plan.name}</h3>
      <div className="amount">
        {plan.is_free ? (
          <span className="amount-now">ฟรี</span>
        ) : listPrice == null ? (
          <span className="amount-now amount-na">ไม่มีรอบรายปี</span>
        ) : (
          <>
            {salePrice != null && (
              <span className="amount-was">
                <s>฿{listPrice.toLocaleString("th-TH")}</s>
                {plan.sale_label && (
                  <span className="sale-badge">{plan.sale_label}</span>
                )}
              </span>
            )}
            <span className="amount-now">
              ฿<CountUp value={amount} />
              <small> / {cycle === "yearly" ? "ปี" : "เดือน"}</small>
            </span>
          </>
        )}
      </div>
      <ul>
        <li>
          <Check size={17} /> สต็อกพร้อมขาย{" "}
          {limitLabel(plan.active_inventory_limit, "รายการ")}
        </li>
        <li>
          <Check size={17} /> สมาชิกในร้าน {limitLabel(plan.member_limit, "คน")}
        </li>
        {FEATURE_ROWS.map((row) => {
          const on = plan.features[row.key];
          return (
            <li key={row.key} className={on ? "" : "is-off"}>
              {on ? <Check size={17} /> : <Minus size={17} />} {row.label}
            </li>
          );
        })}
      </ul>
      <a
        className={`btn price-action ${recommended ? "blue" : ""}`}
        href={merchantRegisterUrl}
      >
        {plan.is_free
          ? "เริ่มใช้ฟรี"
          : soldThisCycle
            ? "เริ่มทดลองใช้ฟรี"
            : "ดูรอบรายเดือน"}
      </a>
    </article>
  );
}
