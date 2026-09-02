import {
  BadgeCheck,
  BarChart3,
  Boxes,
  CalendarClock,
  Check,
  FileSpreadsheet,
  MessagesSquare,
  ScrollText,
  ShieldCheck,
  Users,
} from "lucide-react";
import { Reveal } from "./Motion";

const CAPABILITIES = [
  [
    Boxes,
    "คลังไอดีที่ค้นเจอจริง",
    "ทุกไอดีมีแท็ก 5 ตัว (เช่น #23DX5) พร้อมเกม แรงก์ เลเวล สกิน ฟิลด์กำหนดเอง และรูปภาพ ค้นด้วยแท็ก ชื่อ หรือแรงก์ได้ทันที",
  ],
  [
    CalendarClock,
    "จองและขาย ไม่มีทางชนกัน",
    "ล็อกรายการในจังหวะขาย พนักงานสองคนกดขายใบเดียวกัน สำเร็จได้แค่คนเดียว การจองมีเวลาหมดอายุและปลดล็อกคืนสต็อกให้อัตโนมัติ",
  ],
  [
    FileSpreadsheet,
    "ย้ายจาก Spreadsheet ใน 5 นาที",
    "อัปโหลด Excel/CSV จับคู่คอลัมน์ ดูตัวอย่าง ตรวจ error รายแถวก่อนนำเข้าจริง ระบบประมวลผลเบื้องหลังไม่ต้องรอ",
  ],
  [
    ShieldCheck,
    "ข้อมูลเข้าสู่ระบบ แยกและเข้ารหัส",
    "Username/Password ของไอดีเก็บแยกและเข้ารหัส เปิดดูได้เมื่อมีสิทธิ์และยืนยันตัวตนล่าสุด ทุกครั้งที่เปิดถูกบันทึกไว้",
  ],
  [
    Users,
    "ทีมงานพร้อมการแบ่งสิทธิ์รายคน",
    "เจ้าของสร้างบัญชีพนักงานเอง แล้วเลือกให้แต่ละคนจัดการสต็อก ขาย ดูต้นทุน–กำไร เปิด credentials ส่งออกข้อมูล หรือจัดการบิลได้",
  ],
  [
    ScrollText,
    "ตรวจย้อนหลังได้ทุกการกระทำ",
    "บันทึกกิจกรรมทั้งของเจ้าของและพนักงาน ใครทำอะไร เมื่อไร จากที่ไหน กรองตามคน เหตุการณ์ และช่วงเวลาได้",
  ],
  [
    MessagesSquare,
    "เชื่อมต่อ Discord ของร้าน",
    "แจ้งเตือนรายการขาย การจอง ความเคลื่อนไหวสต็อก และปัญหาระบบ เข้าห้องแยกตามหมวด สั่งงานพื้นฐานผ่านบอตได้",
  ],
  [
    BarChart3,
    "รายงานกำไรและส่งออกข้อมูล",
    "สรุปยอดขาย–กำไรรายวัน เห็นต้นทุนต่อใบ ส่งออก CSV และดาวน์โหลดข้อมูลของร้านได้ทุกเมื่อ ไม่ผูกขาด",
  ],
] as const;

const REASONS = [
  "ค้นเจอไอดีในไม่กี่วินาที ไม่ต้องเลื่อนหาในชีต",
  "พนักงานหลายคนทำงานพร้อมกันได้ โดยไม่ขายซ้ำ",
  "ทุกอย่างโปร่งใส ตรวจย้อนหลังได้ ลดข้อพิพาทในทีม",
  "credentials ปลอดภัย เข้ารหัส + ต้องยืนยันตัวตน",
  "ข้อมูลเป็นของคุณ ส่งออกได้ตลอด ย้ายออกเมื่อไรก็ได้",
  "เริ่มฟรี ทดลองครบทุกฟีเจอร์ 14 วัน แล้วมีแพ็กเริ่มต้นให้ต่อ",
];

export function AboutSection() {
  return (
    <section className="section container about" id="about">
      <Reveal className="section-head">
        <span className="kicker">
          <span className="kicker-tick" aria-hidden="true" />
          รู้จัก GamoryID
        </span>
        <h2>ระบบเดียว จบทุกงานหลังร้านไอดีเกม</h2>
        <p>
          GamoryID สร้างมาเพื่อพ่อค้าไอดีเกมโดยเฉพาะ รวมคลังสินค้า การจอง ลูกค้า
          ทีมงาน และประวัติการขายไว้ในที่เดียว แทนที่ชีตที่พลาดง่ายและงานซ้ำ ๆ
          ด้วยเวิร์กโฟลว์ที่ออกแบบมาให้ทำงานเป็นทีมได้จริง
        </p>
      </Reveal>

      <div className="capability-grid">
        {CAPABILITIES.map(([Icon, title, text], index) => (
          <Reveal
            as="article"
            className="capability"
            key={title}
            delay={(index % 4) * 60}
          >
            <div className="capability-icon">
              <Icon size={20} />
            </div>
            <h3>{title}</h3>
            <p>{text}</p>
          </Reveal>
        ))}
      </div>

      <Reveal className="why-strip">
        <div className="why-head">
          <BadgeCheck size={20} aria-hidden="true" />
          <h3>ทำไมร้านถึงเลือก GamoryID</h3>
        </div>
        <ul>
          {REASONS.map((reason) => (
            <li key={reason}>
              <Check size={16} aria-hidden="true" />
              {reason}
            </li>
          ))}
        </ul>
      </Reveal>
    </section>
  );
}
