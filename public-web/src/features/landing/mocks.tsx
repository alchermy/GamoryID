import { Check, FileSpreadsheet, Lock, ShieldCheck } from "lucide-react";

/** Seven-point sales sparkline for the floating "ยอดขาย" card. */
export function Sparkline() {
  const points = [8, 14, 11, 20, 17, 26, 31];
  const max = Math.max(...points);
  const step = 92 / (points.length - 1);
  const coords = points.map((p, i) => {
    const x = 4 + i * step;
    const y = 34 - (p / max) * 28;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  });
  const last = coords[coords.length - 1].split(",");
  return (
    <svg className="spark" viewBox="0 0 100 38" role="img" aria-label="แนวโน้มยอดขาย 7 วัน">
      <polyline
        points={coords.join(" ")}
        fill="none"
        stroke="var(--signal)"
        strokeWidth="2.4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx={last[0]} cy={last[1]} r="3.4" fill="var(--primary)" />
    </svg>
  );
}

/** Small illustrative panels shown under each workflow step. */
export function StepShot({ n }: { n: 1 | 2 | 3 }) {
  if (n === 1) {
    return (
      <div className="shot" aria-hidden="true">
        <div className="shot-file">
          <FileSpreadsheet size={16} />
          <span>stock-มีนาคม.xlsx</span>
          <b>642 แถว</b>
        </div>
        <div className="shot-bars">
          <span style={{ width: "82%" }} />
          <span style={{ width: "64%" }} />
          <span style={{ width: "91%" }} />
        </div>
        <div className="shot-progress">
          <i style={{ width: "72%" }} />
        </div>
      </div>
    );
  }
  if (n === 2) {
    return (
      <div className="shot" aria-hidden="true">
        <div className="shot-query">
          <span className="shot-mono">#23DX5</span>
          <b>1 รายการ</b>
        </div>
        <div className="shot-result">
          <span className="shot-mono">#23DX5</span>
          <i>Valorant · Immortal 2</i>
          <button type="button">จอง</button>
        </div>
      </div>
    );
  }
  return (
    <div className="shot" aria-hidden="true">
      <div className="shot-form">
        <label>
          ลูกค้า<span>คุณเบส · LINE</span>
        </label>
        <label>
          ราคาขาย<span className="shot-mono">฿2,500</span>
        </label>
        <label>
          กำไร<span className="shot-mono shot-plus">+฿700</span>
        </label>
      </div>
      <button className="shot-save" type="button">
        <Check size={14} /> บันทึกการขาย
      </button>
    </div>
  );
}

/** Locked credentials panel for the security spotlight row. */
export function CredentialsMock() {
  return (
    <div className="cred" aria-hidden="true">
      <div className="cred-head">
        <ShieldCheck size={16} />
        ข้อมูลเข้าสู่ระบบของ <span className="shot-mono">#23DX5</span>
      </div>
      <div className="cred-field">
        <span>Username</span>
        <b className="cred-blur">valo_smurf_2451</b>
      </div>
      <div className="cred-field">
        <span>Password</span>
        <b className="cred-blur">••••••••••••</b>
      </div>
      <button className="cred-btn" type="button">
        <Lock size={13} /> ยืนยันตัวตนเพื่อเปิด
      </button>
      <p className="cred-note">เปิดดูได้เมื่อมีสิทธิ์ และยืนยันตัวตนล่าสุดภายใน 10 นาที</p>
    </div>
  );
}
