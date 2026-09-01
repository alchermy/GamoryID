import {
  ArrowRight,
  FileSpreadsheet,
  KeyRound,
  Play,
  Search,
  ShieldCheck,
} from "lucide-react";
import { merchantRegisterUrl } from "../../config/links";
import { Reveal } from "./Motion";

export function HeroSection() {
  return (
    <>
      <section className="container hero">
        <Reveal className="hero-copy">
          <span className="badge">
            <ShieldCheck size={15} />
            ระบบจัดการสต็อกสำหรับร้านดิจิทัล
          </span>
          <h1>
            หาไอดีให้เจอ
            <br />
            <em>ปิดการขายให้ไว</em>
          </h1>
          <p>
            GamoryID รวมสต็อก การจอง ลูกค้า และประวัติการขายไว้ในที่เดียว
            ให้ทีมร้านทำงานกับข้อมูลจำนวนมากได้ง่ายขึ้น
          </p>
          <div className="hero-actions">
            <a className="btn orange btn-lg" href={merchantRegisterUrl}>
              ทดลองใช้ฟรี 30 วัน <ArrowRight size={17} />
            </a>
            <a className="btn btn-lg btn-secondary" href="#workflow">
              <Play size={16} fill="currentColor" />
              ดูวิธีทำงาน
            </a>
          </div>
          <p className="small-note">
            ไม่ต้องใส่บัตรเครดิต · ส่งออกข้อมูลของร้านได้
          </p>
        </Reveal>
        <Reveal className="hero-visual" delay={120}>
          <ProductPreview />
        </Reveal>
      </section>
      <TrustStrip />
    </>
  );
}

function ProductPreview() {
  return (
    <div className="product-shot" aria-label="ตัวอย่างหน้าระบบ GamoryID">
      <div className="signal-node signal-node-top" />
      <div className="signal-node signal-node-bottom" />
      <div className="mock">
        <div className="mock-top">
          <span className="dot" />
          <span className="dot" />
          <span className="dot" />
        </div>
        <div className="mock-body">
          <div className="mock-side">
            <div className="mock-line active" />
            <div className="mock-line" />
            <div className="mock-line" />
            <div className="mock-line" />
          </div>
          <div className="mock-main">
            <div className="mock-title" />
            <div className="mock-kpis">
              <i />
              <i />
              <i />
            </div>
            <div className="mock-table">
              {[1, 2, 3, 4, 5].map((row) => (
                <div className="mock-row" key={row}>
                  <b />
                  <i />
                  <i />
                  <i />
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
      <img
        className="hero-mascot"
        src="/mascot/gammy-secure.png"
        alt="Gammy ดูแลความปลอดภัย"
      />
      <div className="floating-tag floating-tag-search">
        <Search size={14} /> ค้นหา #23DX5
      </div>
      <div className="floating-tag floating-tag-status">
        <ShieldCheck size={14} /> พร้อมขาย
      </div>
    </div>
  );
}

function TrustStrip() {
  return (
    <section className="trust" aria-label="จุดเด่นด้านการจัดการข้อมูล">
      <div className="container">
        <div className="trust-item">
          <KeyRound size={18} /> ข้อมูลร้านแยกเป็นสัดส่วน
        </div>
        <div className="trust-item">
          <Search size={18} /> Exact tag search
        </div>
        <div className="trust-item">
          <ShieldCheck size={18} /> สิทธิ์แยกตามคน
        </div>
        <div className="trust-item">
          <FileSpreadsheet size={18} /> นำเข้าและส่งออก CSV
        </div>
      </div>
    </section>
  );
}
