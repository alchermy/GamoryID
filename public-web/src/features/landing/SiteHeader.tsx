export function SiteHeader() {
  return (
    <header className="container nav">
      <a className="logo" href="#top">
        <img src="/mascot/gammy-hello.png" alt="Gammy" />
        <span>
          Gamory<span>ID</span>
        </span>
      </a>
      <nav className="links" aria-label="เมนูเว็บไซต์">
        <a href="#features">ฟีเจอร์</a>
        <a href="#workflow">วิธีใช้งาน</a>
        <a href="#pricing">แพ็กเกจ</a>
        <a className="btn" href="http://localhost:5173">
          เข้าสู่ระบบ
        </a>
        <a className="btn orange" href="#pricing">
          เริ่มใช้ฟรี
        </a>
      </nav>
    </header>
  );
}
