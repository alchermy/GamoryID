import { ArrowRight, Check } from "lucide-react";
import { merchantRegisterUrl } from "../../config/links";
import { Reveal } from "./Motion";

export function PricingSection() {
  return (
    <>
      <section className="section container" id="pricing">
        <Reveal className="section-head">
          <span className="kicker">เริ่มเล็ก ขยายตามร้าน</span>
          <h2>แพ็กเกจตรงไปตรงมา</h2>
          <p>
            ทดลองใช้ฟรี 30 วัน หลังจากนั้นเลือกแพ็กเกจตามจำนวนสต็อกและทีมของคุณ
          </p>
        </Reveal>
        <div className="price-wrap">
          <Reveal>
            <PriceCard
              name="Starter"
              amount="299"
              inventory="1,000"
              members="3"
            />
          </Reveal>
          <Reveal delay={90}>
            <PriceCard
              name="Growth"
              amount="699"
              inventory="5,000"
              members="8"
              recommended
            />
          </Reveal>
        </div>
      </section>
      <Reveal className="container cta">
        <div>
          <h2>พร้อมจัดร้านให้เป็นระบบหรือยัง?</h2>
          <p>เริ่มนำเข้าสต็อก ค้นหา และบันทึกขายได้วันนี้</p>
          <div className="hero-actions">
            <a href={merchantRegisterUrl} className="btn orange btn-lg">
              เริ่มทดลองใช้ฟรี <ArrowRight size={17} />
            </a>
          </div>
        </div>
        <img src="/mascot/gammy-hello.png" alt="Gammy กล่าวต้อนรับ" />
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
  amount: string;
  inventory: string;
  members: string;
  recommended?: boolean;
}) {
  return (
    <article className={`price ${recommended ? "recommended" : ""}`}>
      {recommended && (
        <span className="price-label">เหมาะกับร้านที่กำลังโต</span>
      )}
      <h3>{name}</h3>
      <div className="amount">
        ฿{amount} <small>/ 30 วัน</small>
      </div>
      <ul>
        <li>
          <Check size={17} /> สต็อกพร้อมขายสูงสุด {inventory} รายการ
        </li>
        <li>
          <Check size={17} /> สมาชิกในร้าน {members} คน
        </li>
        <li>
          <Check size={17} /> นำเข้า CSV และ export ข้อมูล
        </li>
        <li>
          <Check size={17} /> ประวัติกิจกรรมและการแบ่งสิทธิ์
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
