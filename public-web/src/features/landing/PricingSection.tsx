import { useState } from "react";
import { ArrowRight, Check, Minus } from "lucide-react";
import { merchantRegisterUrl } from "../../config/links";
import { CountUp, Reveal } from "./Motion";
import { Mascot } from "./Mascot";

type Cycle = "monthly" | "yearly";

/**
 * Static mirror of the backend plan catalogue
 * (`SubscriptionPlan::defaults()` / the default-plans data migration).
 * Keep prices and feature flags in sync when Super Admin changes them.
 */
const PLANS = [
  {
    name: "Free",
    tag: "เริ่มต้นใช้งาน",
    monthly: 0,
    yearly: 0,
    inventory: "50",
    members: "1",
    features: {
      bulk_import: false,
      advanced_export: false,
      activity_log: false,
      discord: false,
      analytics: false,
    },
  },
  {
    name: "Starter",
    tag: "พ่อค้าคนเดียว",
    monthly: 299,
    yearly: 2990,
    inventory: "1,000",
    members: "2",
    features: {
      bulk_import: true,
      advanced_export: false,
      activity_log: true,
      discord: false,
      analytics: false,
    },
  },
  {
    name: "Growth",
    tag: "ร้านมีทีม",
    monthly: 699,
    yearly: 6990,
    inventory: "5,000",
    members: "6",
    recommended: true,
    features: {
      bulk_import: true,
      advanced_export: true,
      activity_log: true,
      discord: true,
      analytics: true,
    },
  },
  {
    name: "Pro",
    tag: "ร้านใหญ่",
    monthly: 1490,
    yearly: 14900,
    inventory: "50,000",
    members: "ไม่จำกัด",
    features: {
      bulk_import: true,
      advanced_export: true,
      activity_log: true,
      discord: true,
      analytics: true,
    },
  },
] as const;

const FEATURE_ROWS: { key: keyof (typeof PLANS)[number]["features"]; label: string }[] = [
  { key: "bulk_import", label: "นำเข้า Excel/CSV แบบชุด" },
  { key: "activity_log", label: "บันทึกกิจกรรม (ตรวจย้อนหลัง)" },
  { key: "discord", label: "เชื่อมต่อ Discord" },
  { key: "advanced_export", label: "ส่งออกยอดขาย/กำไร/ประวัติ" },
  { key: "analytics", label: "วิเคราะห์ต้นทุน–กำไร / รายงานลึก" },
];

export function PricingSection() {
  const [cycle, setCycle] = useState<Cycle>("monthly");

  return (
    <>
      <section className="section container" id="pricing">
        <Reveal className="section-head centered">
          <span className="kicker">
            <span className="kicker-tick" aria-hidden="true" />
            เริ่มเล็ก ขยายตามร้าน
          </span>
          <h2>แพ็กเกจตรงไปตรงมา</h2>
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
          {PLANS.map((plan, i) => (
            <Reveal key={plan.name} delay={i * 70}>
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

function PriceCard({
  plan,
  cycle,
}: {
  plan: (typeof PLANS)[number];
  cycle: Cycle;
}) {
  const amount = cycle === "yearly" ? plan.yearly : plan.monthly;
  const recommended = "recommended" in plan && plan.recommended;
  const isFree = plan.monthly === 0;

  return (
    <article className={`price-card ${recommended ? "is-top" : ""}`}>
      {recommended && <span className="price-flag">แนะนำ</span>}
      <span className="price-tag">{plan.tag}</span>
      <h3>{plan.name}</h3>
      <div className="amount">
        {isFree ? (
          <>ฟรี</>
        ) : (
          <>
            ฿<CountUp value={amount} />{" "}
            <small>/ {cycle === "yearly" ? "ปี" : "30 วัน"}</small>
          </>
        )}
      </div>
      <ul>
        <li>
          <Check size={17} /> สต็อกพร้อมขาย {plan.inventory} รายการ
        </li>
        <li>
          <Check size={17} /> สมาชิกในร้าน {plan.members} คน
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
        {isFree ? "เริ่มใช้ฟรี" : "เริ่มทดลองใช้ฟรี"}
      </a>
    </article>
  );
}
