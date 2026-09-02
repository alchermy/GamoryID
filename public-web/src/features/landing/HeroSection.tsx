import type { CSSProperties } from "react";
import { ArrowRight, PlayCircle } from "lucide-react";
import { merchantRegisterUrl } from "../../config/links";
import { specs } from "./content";
import { CountUp, Reveal } from "./Motion";
import { Mascot } from "./Mascot";

export function HeroSection() {
  return (
    <section className="container hero">
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
          <a className="btn btn-lg btn-secondary" href="#workflow">
            <PlayCircle size={17} />
            ดูวิธีทำงาน
          </a>
        </div>
        <p className="small-note">ไม่ต้องใส่บัตรเครดิต · ส่งออกข้อมูลของร้านได้ทุกเมื่อ</p>
      </div>

      <div
        className="hero-visual hero-enter"
        style={{ "--reveal-delay": "140ms" } as CSSProperties}
      >
        <ProductDemo />
      </div>
    </section>
  );
}

function ProductDemo() {
  return (
    <div className="demo" role="img" aria-label="ตัวอย่างการค้นหาไอดีด้วยแท็กและปิดการขายในระบบ GamoryID">
      <span className="signal-node signal-node-top" aria-hidden="true" />
      <div className="demo-frame">
        <div className="demo-bar">
          <span className="demo-dot" />
          <span className="demo-dot" />
          <span className="demo-dot" />
          <div className="demo-search">
            <span className="demo-search-typed" />
            <span className="demo-caret" aria-hidden="true" />
          </div>
        </div>
        <div className="demo-body">
          <nav className="demo-side" aria-hidden="true">
            <span className="demo-nav is-active" />
            <span className="demo-nav" />
            <span className="demo-nav" />
            <span className="demo-nav" />
            <span className="demo-side-rail" />
          </nav>
          <div className="demo-main" aria-hidden="true">
            <div className="demo-kpis">
              <span>
                <b>128</b>พร้อมขาย
              </span>
              <span>
                <b>9</b>ถูกจอง
              </span>
              <span>
                <b>฿82K</b>ยอดวันนี้
              </span>
            </div>
            <div className="demo-rows">
              {[0, 1, 2, 3].map((i) => (
                <div className={`demo-row${i === 1 ? " is-focus" : ""}`} key={i}>
                  <span className="demo-tag">
                    {i === 1 ? "#23DX5" : ["#7K9PM", "#Q4RTX", "#M2WZ8"][i > 1 ? i - 1 : i]}
                  </span>
                  <span className="demo-cell" />
                  {i === 1 ? (
                    <span className="demo-status">
                      <b className="s-ready">พร้อมขาย</b>
                      <b className="s-hold">จอง</b>
                      <b className="s-sold">ขายแล้ว</b>
                    </span>
                  ) : (
                    <span className="demo-cell demo-cell-sm" />
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
      <div className="demo-chip demo-chip-search">
        ค้นด้วยแท็ก · <b>1 รายการ</b>
      </div>
      <Mascot
        pose="secure"
        alt="Gammy มาสคอตของ GamoryID"
        width={168}
        className="demo-mascot"
        priority
      />
    </div>
  );
}

export function SpecBand() {
  return (
    <Reveal as="section" className="spec-band" aria-label="ข้อมูลจำเพาะของระบบ">
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
