import type { ComponentType } from "react";
import {
  Boxes,
  CalendarClock,
  Compass,
  CreditCard,
  FileSpreadsheet,
  Lock,
  MessagesSquare,
  ReceiptText,
  ShieldCheck,
  Users,
} from "lucide-react";

type Point = { term?: string; text: string };
type Section = {
  id: string;
  title: string;
  icon: ComponentType<{ size?: number; strokeWidth?: number }>;
  points: Point[];
};

const SECTIONS: Section[] = [
  {
    id: "start",
    title: "เริ่มต้นใช้งาน",
    icon: Compass,
    points: [
      {
        term: "เมนูซ้าย",
        text: "แบ่งเป็น พื้นที่ทำงาน (ภาพรวม คลังไอดี รายการขาย ลูกค้า นำเข้าข้อมูล) และ จัดการร้าน (ทีมและสิทธิ์ บันทึกกิจกรรม แพ็กเกจ ประวัติธุรกรรม Discord ตั้งค่าร้าน)",
      },
      {
        text: "เจ้าของร้านเห็นทุกเมนู พนักงานเห็นเฉพาะเมนูที่ได้รับสิทธิ์ — เมนูไหนไม่ขึ้น แปลว่ายังไม่ได้รับสิทธิ์นั้น",
      },
    ],
  },
  {
    id: "inventory",
    title: "คลังไอดี",
    icon: Boxes,
    points: [
      {
        term: "เพิ่มไอดี",
        text: "บันทึกไอดีใหม่ ทุกไอดีได้แท็ก 5 ตัวอัตโนมัติ (เช่น #23DX5) ใช้บอกลูกค้าและค้นหาได้ทันที",
      },
      {
        term: "สถานะ",
        text: "พร้อมขาย · ถูกจอง (ล็อกให้ลูกค้าชั่วคราว) · ขายแล้ว — เปลี่ยนได้จากปุ่มในแต่ละแถว",
      },
      {
        text: "เปิดรายละเอียดไอดีเพื่อแก้ข้อมูล ใส่โน้ตภายในร้าน แนบรูปภาพ และคัดลอกรายละเอียดส่งลูกค้าในคลิกเดียว",
      },
      {
        text: "ค้นด้วยแท็ก ชื่อไอดี Username หรือ Riot ID กรองตามสถานะจากมุมขวาบน",
      },
    ],
  },
  {
    id: "imports",
    title: "นำเข้าข้อมูล",
    icon: FileSpreadsheet,
    points: [
      {
        text: "อัปโหลดไฟล์ Excel/CSV เพื่อเพิ่มไอดีทีละหลายรายการ ระบบมีไฟล์ตัวอย่างให้ดาวน์โหลด",
      },
      {
        text: "หลังประมวลผล ระบบสรุปว่าเพิ่มสำเร็จกี่รายการ และแจ้ง error เป็นรายแถวให้แก้เฉพาะจุดที่ผิด",
      },
    ],
  },
  {
    id: "reserve",
    title: "การจอง",
    icon: CalendarClock,
    points: [
      {
        text: "เปลี่ยนสถานะไอดีเป็น ถูกจอง เพื่อล็อกให้ลูกค้ารายหนึ่ง ระบุชื่อลูกค้าและระยะเวลาที่จะถือจองไว้",
      },
      {
        text: "ลูกค้ายืนยัน → ปิดการขาย · ลูกค้าไม่เอาแล้ว → ยกเลิกการจอง ไอดีกลับมาพร้อมขาย",
      },
    ],
  },
  {
    id: "sales",
    title: "การขายและประวัติ",
    icon: ReceiptText,
    points: [
      {
        text: "ปิดการขายจากคลังไอดี กรอกราคาขายจริง ระบบบันทึกกำไรจากต้นทุนที่ตั้งไว้",
      },
      {
        term: "เมนูรายการขาย",
        text: "ดูรายการที่ปิดแล้วทั้งหมด กดเข้าไปดูรายละเอียดแต่ละบิลได้",
      },
      { text: "กำไร/ต้นทุนแสดงเฉพาะผู้ที่มีสิทธิ์ “ดูต้นทุนและกำไร”" },
    ],
  },
  {
    id: "customers",
    title: "ลูกค้า",
    icon: Users,
    points: [
      {
        text: "บันทึกช่องทางติดต่อลูกค้า (ชื่อ เบอร์ LINE Facebook) พร้อมโน้ต ใช้ผูกกับการจองและการขาย",
      },
      {
        text: "ลบข้อมูลติดต่อลูกค้าได้ทันทีจากหน้า “ลูกค้า” ระบบเก็บเฉพาะประวัติการขายแบบไม่ระบุตัวตน (PDPA)",
      },
    ],
  },
  {
    id: "team",
    title: "ทีมและสิทธิ์",
    icon: ShieldCheck,
    points: [
      {
        text: "เพิ่มบัญชีพนักงานจากเมนู ทีมและสิทธิ์ ตั้งรหัสผ่านชั่วคราวให้",
      },
      {
        text: "กำหนดสิทธิ์รายฟีเจอร์: จัดการสต็อก จองและขาย ดูต้นทุน/กำไร ส่งออกข้อมูล เปิดดูข้อมูลเข้าสู่ระบบ จัดการทีม จัดการแพ็กเกจ จัดการ Discord",
      },
      { text: "เจ้าของร้านรับผิดชอบการกระทำของพนักงานที่เพิ่มเข้ามา" },
    ],
  },
  {
    id: "billing",
    title: "เครดิตและแพ็กเกจ",
    icon: CreditCard,
    points: [
      { term: "1 เครดิต = 1 บาท", text: "ใช้จ่ายค่าแพ็กเกจของร้านนี้เท่านั้น" },
      {
        term: "เติมเครดิต",
        text: "เมนูแพ็กเกจ → ปุ่มเติมเครดิต → สแกน QR พร้อมเพย์หรือโอนตามเลขบัญชีที่แสดง แล้วแนบสลิป ทีมงานตรวจแล้วเครดิตจะเข้าบัญชี",
      },
      {
        text: "ซื้อแพ็กเกจโดยเลือกแพ็ก + รอบ (รายเดือน/รายปี) ระบบตัดจากเครดิตทันที",
      },
      {
        term: "ต่ออายุอัตโนมัติ",
        text: "ระบบหักเครดิตต่อแพ็กเกจเองเมื่อครบรอบ ถ้าเครดิตไม่พอ ร้านเข้าโหมดอ่านอย่างเดียวตามปกติ",
      },
    ],
  },
  {
    id: "discord",
    title: "Discord",
    icon: MessagesSquare,
    points: [
      {
        text: "เชื่อมบอทกับเซิร์ฟเวอร์ร้านจากเมนู Discord เพื่อสั่งงานพื้นฐาน (ค้นหา ดูรายการ จอง ปิดการขาย) ผ่านคำสั่งในแชท",
      },
      {
        text: "ตั้งการแจ้งเตือนรายการขาย การจอง ความเคลื่อนไหวสต็อก และปัญหาระบบ ให้เข้าห้องแยกตามหมวด",
      },
    ],
  },
  {
    id: "security",
    title: "ความปลอดภัย",
    icon: Lock,
    points: [
      { text: "เปิด 2FA ได้จากตั้งค่าบัญชี และนำอุปกรณ์อื่นออกจากระบบได้ทั้งหมด" },
      {
        text: "ข้อมูลเข้าสู่ระบบของไอดีถูกเข้ารหัส ต้องยืนยันตัวตนซ้ำก่อนเปิดดู และสิทธิ์การเปิดดูกำหนดแยกต่างหาก",
      },
    ],
  },
];

export function ManualPanel() {
  return (
    <div className="manual">
      <p className="manual-intro">
        เลือกหัวข้อด้านล่างเพื่อข้ามไปอ่านส่วนที่ต้องการ
        แต่ละการ์ดสรุปสิ่งที่ต้องรู้ของเมนูนั้น
      </p>

      <nav className="manual-quicknav" aria-label="หัวข้อคู่มือ">
        {SECTIONS.map((section, index) => (
          <a key={section.id} href={`#manual-${section.id}`}>
            <span className="manual-quicknav-num">
              {String(index + 1).padStart(2, "0")}
            </span>
            {section.title}
          </a>
        ))}
      </nav>

      <div className="manual-grid">
        {SECTIONS.map((section, index) => {
          const Icon = section.icon;
          return (
            <section
              key={section.id}
              id={`manual-${section.id}`}
              className="manual-card"
            >
              <header className="manual-card-head">
                <span className="manual-card-num">
                  {String(index + 1).padStart(2, "0")}
                </span>
                <span className="manual-card-icon">
                  <Icon size={18} strokeWidth={2} />
                </span>
                <h3>{section.title}</h3>
              </header>
              <ul className="manual-card-points">
                {section.points.map((point, i) => (
                  <li key={i}>
                    {point.term && <b>{point.term}</b>}
                    {point.term ? ` — ${point.text}` : point.text}
                  </li>
                ))}
              </ul>
            </section>
          );
        })}
      </div>
    </div>
  );
}
