import { ArrowRight, Check } from "lucide-react";
import { merchantRegisterUrl } from "../../config/links";
import { CountUp, Reveal } from "./Motion";
import { Mascot } from "./Mascot";

const PLANS = [
  { name: "Starter", amount: 299, inventory: "1,000", members: "3" },
  {
    name: "Growth",
    amount: 699,
    inventory: "5,000",
    members: "8",
    recommended: true,
  },
] as const;

export function PricingSection() {
  return (
    <>
      <section className="section container" id="pricing">
        <Reveal className="section-head">
          <span className="kicker">
            <span className="kicker-tick" aria-hidden="true" />
            เริ่มเล็ก ขยายตามร้าน
          </span>
          <h2>แพ็กเกจตรงไปตรงมา</h2>
          <p>
            ทดลองใช้ฟรี 30 วัน หลังจากนั้นเลือกแพ็กเกจตามจำนวนสต็อกและทีมของคุณ
            ปรับขึ้น–ลงได้ทุกเดือน
          </p>
        </Reveal>
        <div className="price-wrap">
          {PLANS.map((plan, i) => (
            <Reveal key={plan.name} delay={i * 90}>
              <PriceCard {...plan} />
            </Reveal>
          ))}
        </div>
      </section>

      <Reveal className="container cta">
        <div className="cta-copy">
          <h2>พร้อมจัดร้านให้เป็นระบบหรือยัง?</h2>
          <p>เริ่มนำเข้าสต็อก ค้นหา และบันทึกขายได้วันนี้</p>
          <a href={merchantRegisterUrl} className="btn orange btn-lg">
            เริ่มทดลองใช้ฟรี <ArrowRight size={17} />
          </a>
        </div>
        <Mascot pose="hello" alt="Gammy ชวนเปิดร้าน" width={185} className="cta-mascot" />
      </Reveal>
    </>
  );
}

function PriceCard({
  name,
  amount,
  inventory,
  members,
  recommended = false,
}: {
  name: string;
  amount: number;
  inventory: string;
  members: string;
  recommended?: boolean;
}) {
  return (
    <article className={`price ${recommended ? "recommended" : ""}`}>
      {recommended && <span className="price-label">เหมาะกับร้านที่กำลังโต</span>}
      <h3>{name}</h3>
      <div className="amount">
        ฿<CountUp value={amount} /> <small>/ 30 วัน</small>
      </div>
      <ul>
        <li>
          <Check size={17} /> สต็อกพร้อมขายสูงสุด {inventory} รายการ
        </li>
        <li>
          <Check size={17} /> สมาชิกในร้าน {members} คน
        </li>
        <li>
          <Check size={17} /> นำเข้า Excel/CSV และส่งออกข้อมูล
        </li>
        <li>
          <Check size={17} /> บันทึกกิจกรรมและการแบ่งสิทธิ์รายคน
        </li>
      </ul>
      <a
        className={`btn price-action ${recommended ? "blue" : ""}`}
        href={merchantRegisterUrl}
      >
        เริ่มทดลองใช้ฟรี
      </a>
    </article>
  );
}
