import { ArrowRight, PlayCircle } from "lucide-react";
import { merchantRegisterUrl } from "../../config/links";
import { specs } from "./content";
import { CountUp, Reveal } from "./Motion";
import { AppShowcase } from "./AppShowcase";

export function HeroSection() {
  return (
    <section className="hero">
      <div className="hero-glow" aria-hidden="true" />
      <div className="container hero-inner">
        <div className="hero-copy hero-enter">
          <span className="badge">
            <span className="badge-dot" aria-hidden="true" />
            ระบบจัดการสต็อกไอดีเกม
          </span>
          <h1>
            ค้นด้วย <span className="code">#23DX5</span>
            <br />
            จองไม่ชน
            <br />
            ปิดการขายไว
          </h1>
          <p>
            GamoryID รวมคลังไอดี การจอง ลูกค้า และประวัติการขายไว้ในที่เดียว
            ให้ทีมร้านทำงานกับข้อมูลหลักหมื่นรายการได้โดยไม่พลาด
          </p>
          <div className="hero-actions">
            <a className="btn orange btn-lg" href={merchantRegisterUrl}>
              ทดลองใช้ฟรี 30 วัน <ArrowRight size={17} />
            </a>
            <a className="btn btn-lg btn-ghost" href="#workflow">
              <PlayCircle size={17} />
              ดูวิธีทำงาน
            </a>
          </div>
          <p className="small-note">
            ไม่ต้องใส่บัตรเครดิต · ส่งออกข้อมูลของร้านได้ทุกเมื่อ
          </p>
        </div>

        <div className="hero-visual hero-enter">
          <AppShowcase />
        </div>
      </div>
    </section>
  );
}

export function SpecStrip() {
  return (
    <Reveal as="section" className="spec-strip" aria-label="ข้อมูลจำเพาะของระบบ">
      <div className="container spec-grid">
        {specs.map((spec) => (
          <div className="spec" key={spec.label}>
            <div className="spec-value">
              {"prefix" in spec && spec.prefix ? spec.prefix : ""}
              <CountUp value={spec.value} />
              {"suffix" in spec && spec.suffix ? spec.suffix : ""}
            </div>
            <div className="spec-label">{spec.label}</div>
          </div>
        ))}
      </div>
    </Reveal>
  );
}
