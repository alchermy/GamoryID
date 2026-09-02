import { merchantLoginUrl, merchantRegisterUrl } from "../../config/links";
import { Mascot } from "./Mascot";

export function SiteHeader() {
  return (
    <header className="site-header">
      <div className="container nav">
        <a className="logo" href="#top" aria-label="GamoryID หน้าแรก">
          <Mascot pose="main" alt="" width={36} priority />
          <span>
            Gamory<span>ID</span>
          </span>
        </a>
        <nav className="links" aria-label="เมนูเว็บไซต์">
          <div className="nav-links">
            <a href="#features">ฟีเจอร์</a>
            <a href="#workflow">วิธีใช้งาน</a>
            <a href="#pricing">แพ็กเกจ</a>
          </div>
          <div className="nav-actions">
            <a className="btn btn-quiet" href={merchantLoginUrl}>
              เข้าสู่ระบบ
            </a>
            <a className="btn orange" href={merchantRegisterUrl}>
              เริ่มใช้ฟรี
            </a>
          </div>
        </nav>
      </div>
    </header>
  );
}
