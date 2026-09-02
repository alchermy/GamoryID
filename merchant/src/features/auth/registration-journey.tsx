import { CalendarClock, FileSpreadsheet, UsersRound } from "lucide-react";

export function RegistrationJourney() {
  return (
    <aside className="registration-journey" aria-label="ขั้นตอนการเปิดร้าน">
      <div className="registration-journey-content">
        <p className="context-eyebrow">เริ่มต้นใช้งาน</p>
        <h2>
          พร้อมจัดการร้าน
          <br />
          ในไม่กี่นาที
        </h2>
        <ol className="context-list context-list-numbered">
          <li>
            <span aria-hidden="true">1</span>
            เปิดร้านและตั้งชื่อ
          </li>
          <li>
            <span aria-hidden="true">2</span>
            ยืนยันอีเมลเจ้าของร้าน
          </li>
          <li>
            <span aria-hidden="true">3</span>
            เริ่มนำเข้าคลังไอดี
          </li>
        </ol>
        <ul className="context-benefits">
          <li>
            <CalendarClock size={16} strokeWidth={2.25} aria-hidden="true" />
            ทดลองใช้ฟรี 30 วัน
          </li>
          <li>
            <FileSpreadsheet size={16} strokeWidth={2.25} aria-hidden="true" />
            นำเข้าคลังจาก Excel หรือ CSV
          </li>
          <li>
            <UsersRound size={16} strokeWidth={2.25} aria-hidden="true" />
            ชวนทีมงานเข้าร้านภายหลังได้
          </li>
        </ul>
        <img
          src="/mascot/gammy-main.png"
          alt=""
          className="registration-mascot"
        />
      </div>
    </aside>
  );
}
