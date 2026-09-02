import { CalendarCheck, Search, Users } from "lucide-react";

export function LoginWorkspaceContext() {
  return (
    <aside className="login-workspace" aria-label="ภาพรวมการทำงานในร้าน">
      <div className="login-workspace-content">
        <p className="context-eyebrow">ระบบจัดการร้าน</p>
        <h2>
          ทุกงานขายของร้าน
          <br />
          อยู่ในที่เดียว
        </h2>
        <ul className="context-list">
          <li>
            <span aria-hidden="true">
              <Search size={17} strokeWidth={2.25} />
            </span>
            ค้นหาไอดีด้วยแท็กเฉพาะ
          </li>
          <li>
            <span aria-hidden="true">
              <CalendarCheck size={17} strokeWidth={2.25} />
            </span>
            จัดการการจองและปิดการขาย
          </li>
          <li>
            <span aria-hidden="true">
              <Users size={17} strokeWidth={2.25} />
            </span>
            ทำงานร่วมกับทีมของร้าน
          </li>
        </ul>
        <img
          src="/mascot/gammy-main.png"
          alt=""
          className="login-workspace-mascot"
        />
      </div>
    </aside>
  );
}
