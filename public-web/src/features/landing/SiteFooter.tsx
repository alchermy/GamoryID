import { ArrowRight } from "lucide-react";
import { merchantLoginUrl, merchantRegisterUrl } from "../../config/links";
import { Mascot } from "./Mascot";

export function SiteFooter() {
  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div className="footer-brand">
          <a className="logo footer-logo" href="/" aria-label="GamoryID หน้าแรก">
            <Mascot pose="main" alt="" width={34} />
            <span>
              Gamory<span>ID</span>
            </span>
          </a>
          <p>จัดการสต็อกไอดี การจอง ลูกค้า และประวัติการขายของร้านไว้ในที่เดียว</p>
        </div>
        <div className="footer-column">
          <strong>ผลิตภัณฑ์</strong>
          <a href="/#features">ฟีเจอร์</a>
          <a href="/#about">เกี่ยวกับระบบ</a>
          <a href="/#workflow">วิธีใช้งาน</a>
          <a href="/#pricing">แพ็กเกจ</a>
        </div>
        <div className="footer-column">
          <strong>เริ่มใช้งาน</strong>
          <a href={merchantLoginUrl}>เข้าสู่ระบบ</a>
          <a href={merchantRegisterUrl}>สมัครเปิดร้าน</a>
          <a href="/terms">ข้อกำหนดการใช้บริการ</a>
          <a href="/privacy">นโยบายความเป็นส่วนตัว</a>
        </div>
        <div className="footer-start">
          <strong>พร้อมจัดร้านให้เป็นระบบ</strong>
          <p>ทดลองใช้ครบทุกขั้นตอนก่อนเลือกแพ็กเกจ</p>
          <a className="footer-cta" href={merchantRegisterUrl}>
            เริ่มใช้ฟรี <ArrowRight size={16} />
          </a>
        </div>
      </div>
      <div className="container footer-bottom">
        <span>
          © {new Date().getFullYear()} GamoryID · พัฒนาโดย Art Thanawat
        </span>
        <span>
          GamoryID ไม่มีความเกี่ยวข้องหรือได้รับการรับรองจากผู้พัฒนาเกมรายใด
        </span>
      </div>
    </footer>
  );
}
