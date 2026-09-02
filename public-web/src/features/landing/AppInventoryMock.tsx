import {
  Boxes,
  CalendarClock,
  LayoutDashboard,
  ScrollText,
  Search,
  Settings,
  Users,
} from "lucide-react";

/**
 * High-fidelity mock of the merchant inventory screen — real Thai copy, real
 * status colours, real numbers. Rendered inside the hero's tilted app frame so
 * the page reads as a product screenshot, not a wireframe.
 */

type Row = {
  tag: string;
  game: string;
  rank: string;
  price: string;
  status: "ready" | "hold" | "sold";
  note?: string;
};

const ROWS: Row[] = [
  {
    tag: "#23DX5",
    game: "Valorant",
    rank: "Immortal 2",
    price: "฿2,500",
    status: "hold",
    note: "จองอยู่ · 12:04",
  },
  { tag: "#7K9PM", game: "RoV", rank: "Conqueror", price: "฿1,800", status: "ready" },
  { tag: "#Q4RTX", game: "Genshin", rank: "AR 58", price: "฿3,200", status: "ready" },
  {
    tag: "#M2WZ8",
    game: "Valorant",
    rank: "Diamond 1",
    price: "฿1,200",
    status: "sold",
    note: "ขายแล้ว",
  },
  { tag: "#PLX07", game: "RoV", rank: "Diamond", price: "฿990", status: "ready" },
];

const STATUS_LABEL: Record<Row["status"], string> = {
  ready: "พร้อมขาย",
  hold: "ถูกจอง",
  sold: "ขายแล้ว",
};

export function AppInventoryMock() {
  return (
    <div className="mock" aria-hidden="true">
      <aside className="mock-side">
        <span className="mock-side-brand">
          <span className="mock-side-dot" />
          GamoryID
        </span>
        <nav className="mock-nav">
          <span>
            <LayoutDashboard size={15} /> ภาพรวม
          </span>
          <span className="is-active">
            <Boxes size={15} /> คลังไอดี
          </span>
          <span>
            <CalendarClock size={15} /> การจอง
          </span>
          <span>
            <Users size={15} /> ลูกค้า
          </span>
          <span>
            <ScrollText size={15} /> บันทึกกิจกรรม
          </span>
          <span>
            <Settings size={15} /> ตั้งค่า
          </span>
        </nav>
      </aside>

      <div className="mock-main">
        <div className="mock-topbar">
          <span className="mock-title">คลังไอดี</span>
          <span className="mock-search">
            <Search size={13} />
            <span className="mock-search-typed" />
            <span className="mock-caret" />
          </span>
        </div>

        <div className="mock-kpis">
          <span>
            <b>1,284</b>พร้อมขาย
          </span>
          <span>
            <b>37</b>ถูกจอง
          </span>
          <span>
            <b>฿48,900</b>ขายวันนี้
          </span>
        </div>

        <div className="mock-table">
          <div className="mock-tr mock-th">
            <span>แท็ก</span>
            <span>เกม / แรงก์</span>
            <span>ราคา</span>
            <span>สถานะ</span>
          </div>
          {ROWS.map((row) => (
            <div
              className={`mock-tr${row.status === "hold" ? " is-focus" : ""}`}
              key={row.tag}
            >
              <span className="mock-tag">{row.tag}</span>
              <span className="mock-meta">
                <b>{row.game}</b>
                <i>{row.rank}</i>
              </span>
              <span className="mock-price">{row.price}</span>
              <span className={`mock-badge is-${row.status}`}>
                {row.note ?? STATUS_LABEL[row.status]}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
