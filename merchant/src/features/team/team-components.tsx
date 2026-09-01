import type { FormEvent } from "react";
import {
  Copy,
  Crown,
  MessagesSquare,
  ShieldCheck,
  Trash2,
  UserPlus,
  X,
} from "lucide-react";
import { permissionOptions } from "../../config/navigation";
import { formatDate } from "../../shared/lib/format";
import { DialogHead, Field } from "../../shared/ui/form-controls";
import type { TeamInvitation, TeamMember } from "../../types/models";

function ManagementError({
  error,
  retry,
}: {
  error: string;
  retry: () => void;
}) {
  return (
    <div className="empty" role="alert">
      <strong>โหลดข้อมูลไม่สำเร็จ</strong>
      <p>{error}</p>
      <button className="button" onClick={retry}>
        ลองใหม่
      </button>
    </div>
  );
}
export function TeamPanel({
  members,
  invitations,
  loading,
  error,
  canManage,
  invite,
  onPermissionsChange,
  onRemove,
  onRevokeInvitation,
  retry,
}: {
  members: TeamMember[];
  invitations: TeamInvitation[];
  loading: boolean;
  error: string;
  canManage: boolean;
  invite: () => void;
  onPermissionsChange: (member: TeamMember, permissions: string[]) => void;
  onRemove: (member: TeamMember) => void;
  onRevokeInvitation: (invitation: TeamInvitation) => void;
  retry: () => void;
}) {
  return (
    <section className="panel management-panel" aria-labelledby="team-title">
      <div className="panel-head">
        <div>
          <h2 id="team-title">ทีมและสิทธิ์</h2>
          <small>
            กำหนดสิทธิ์ครั้งเดียว ใช้ร่วมกันทั้งหน้าเว็บและคำสั่ง Discord
          </small>
        </div>
        {canManage && (
          <button className="button primary" onClick={invite}>
            <UserPlus size={17} />
            เชิญพนักงาน
          </button>
        )}
      </div>
      <div className="permission-context" role="note">
        <MessagesSquare size={19} aria-hidden="true" />
        <div>
          <strong>สิทธิ์คำสั่ง Discord ของพนักงาน</strong>
          <span>
            พนักงานต้องเชื่อมบัญชี Discord ของตนเองก่อน
            จากนั้นบอทจะตรวจสิทธิ์ล่าสุดทุกครั้ง: จัดการสต็อกใช้เพิ่มไอดี
            และจองและขายใช้จองหรือปิดการขาย
          </span>
        </div>
      </div>
      {error ? (
        <ManagementError error={error} retry={retry} />
      ) : loading ? (
        <div className="management-loading" aria-live="polite">
          กำลังโหลดสมาชิก…
        </div>
      ) : (
        <>
          <div className="table-wrap">
            <table className="member-table">
              <thead>
                <tr>
                  <th>สมาชิก</th>
                  <th>บทบาท</th>
                  <th>สิทธิ์</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {members.map((member) => (
                  <tr key={member.id}>
                    <td>
                      <strong>{member.user.name}</strong>
                      <small>{member.user.email}</small>
                    </td>
                    <td>
                      {member.role === "owner" ? (
                        <span className="role-badge">
                          <Crown size={14} />
                          เจ้าของร้าน
                        </span>
                      ) : (
                        <span className="role-badge">พนักงาน</span>
                      )}
                    </td>
                    <td>
                      <div className="permission-list">
                        {permissionOptions.map(([key, label]) => (
                          <label key={key} className="permission-item">
                            <input
                              type="checkbox"
                              checked={
                                member.role === "owner" ||
                                (member.permissions ?? []).includes(key)
                              }
                              disabled={!canManage || member.role === "owner"}
                              onChange={(event) => {
                                const next = new Set(member.permissions ?? []);
                                if (event.target.checked) {
                                  next.add(key);
                                } else {
                                  next.delete(key);
                                }
                                onPermissionsChange(member, [...next]);
                              }}
                            />
                            <span>{label}</span>
                          </label>
                        ))}
                      </div>
                    </td>
                    <td>
                      {canManage && member.role === "staff" && (
                        <button
                          className="icon-button danger-icon"
                          aria-label={`นำ ${member.user.name} ออกจากร้าน`}
                          onClick={() => onRemove(member)}
                        >
                          <Trash2 size={17} />
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="member-mobile-list">
            {members.map((member) => (
              <article className="member-mobile-card" key={member.id}>
                <header>
                  <div>
                    <strong>{member.user.name}</strong>
                    <small>{member.user.email}</small>
                  </div>
                  {member.role === "owner" ? (
                    <span className="role-badge">
                      <Crown size={14} />
                      เจ้าของร้าน
                    </span>
                  ) : (
                    <span className="role-badge">พนักงาน</span>
                  )}
                </header>
                <fieldset className="member-mobile-permissions">
                  <legend>สิทธิ์การใช้งาน</legend>
                  {permissionOptions.map(([key, label]) => (
                    <label key={key} className="permission-item">
                      <input
                        type="checkbox"
                        checked={
                          member.role === "owner" ||
                          (member.permissions ?? []).includes(key)
                        }
                        disabled={!canManage || member.role === "owner"}
                        onChange={(event) => {
                          const next = new Set(member.permissions ?? []);
                          if (event.target.checked) {
                            next.add(key);
                          } else {
                            next.delete(key);
                          }
                          onPermissionsChange(member, [...next]);
                        }}
                      />
                      <span>{label}</span>
                    </label>
                  ))}
                </fieldset>
                {canManage && member.role === "staff" && (
                  <button
                    className="button danger member-remove-button"
                    onClick={() => onRemove(member)}
                  >
                    <Trash2 size={16} />
                    นำออกจากร้าน
                  </button>
                )}
              </article>
            ))}
          </div>
          {members.length === 0 && (
            <div className="empty">
              <strong>ยังไม่มีพนักงานในร้าน</strong>
              <p>สร้างคำเชิญเพื่อเพิ่มผู้ช่วยจัดการคลังและรายการขาย</p>
            </div>
          )}
          {invitations.length > 0 && (
            <section
              className="pending-invitations"
              aria-labelledby="pending-invitations-title"
            >
              <h3 id="pending-invitations-title">คำเชิญที่รอรับ</h3>
              {invitations.map((invitation) => (
                <div className="pending-invitation" key={invitation.id}>
                  <div>
                    <strong>{invitation.name}</strong>
                    <small>
                      {invitation.email} · หมดอายุ{" "}
                      {formatDate(invitation.expires_at)}
                    </small>
                  </div>
                  {canManage && (
                    <button
                      className="button danger"
                      onClick={() => onRevokeInvitation(invitation)}
                    >
                      <Trash2 size={16} />
                      ยกเลิกคำเชิญ
                    </button>
                  )}
                </div>
              ))}
            </section>
          )}
        </>
      )}
    </section>
  );
}
export function InviteDialog({
  close,
  submit,
}: {
  close: () => void;
  submit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) close();
      }}
    >
      <form
        className="dialog management-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="invite-title"
        onSubmit={submit}
        noValidate
      >
        <DialogHead
          id="invite-title"
          title="เชิญพนักงาน"
          subtitle="สมาชิกใหม่จะเริ่มต้นด้วยสิทธิ์ที่คุณเลือก"
          close={close}
        />
        <div className="dialog-body">
          <div className="form-grid">
            <Field label="ชื่อพนักงาน">
              <input name="name" required autoFocus />
            </Field>
            <Field label="อีเมล">
              <input name="email" type="email" required autoComplete="email" />
            </Field>
          </div>
          <fieldset className="permission-fieldset">
            <legend>สิทธิ์การใช้งาน</legend>
            {permissionOptions.map(([key, label, description], index) => (
              <label key={key} className="permission-item">
                <input
                  type="checkbox"
                  name={`permission-${key}`}
                  defaultChecked={index < 2}
                />
                <span className="permission-copy">
                  <strong>{label}</strong>
                  <small>{description}</small>
                </span>
              </label>
            ))}
          </fieldset>
        </div>
        <div className="dialog-actions">
          <button type="button" className="button" onClick={close}>
            ยกเลิก
          </button>
          <button className="button primary">
            <UserPlus size={17} />
            ส่งคำเชิญ
          </button>
        </div>
      </form>
    </div>
  );
}
export function InviteLinkDialog({
  url,
  close,
  notify,
}: {
  url: string;
  close: () => void;
  notify: (message: string) => void;
}) {
  const copyLink = async () => {
    try {
      await navigator.clipboard?.writeText(url);
      notify("คัดลอกลิงก์คำเชิญแล้ว");
    } catch {
      notify("คัดลอกลิงก์ไม่สำเร็จ");
    }
  };
  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) close();
      }}
    >
      <section
        className="dialog management-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="invite-link-title"
      >
        <DialogHead
          id="invite-link-title"
          title="ส่งคำเชิญให้พนักงาน"
          subtitle="ลิงก์นี้ใช้ได้ 7 วันและใช้ได้เพียงครั้งเดียว"
          close={close}
        />
        <div className="dialog-body">
          <label className="field">
            <span className="field-label">ลิงก์คำเชิญ</span>
            <span className="invite-link">
              <input readOnly value={url} aria-label="ลิงก์คำเชิญ" />
              <button
                type="button"
                className="button"
                onClick={() => void copyLink()}
              >
                <Copy size={17} />
                คัดลอก
              </button>
            </span>
          </label>
        </div>
        <div className="dialog-actions">
          <button
            type="button"
            className="button primary"
            autoFocus
            onClick={close}
          >
            เสร็จสิ้น
          </button>
        </div>
      </section>
    </div>
  );
}
export function PermissionDialog({
  member,
  permissions,
  close,
  confirm,
}: {
  member: TeamMember;
  permissions: string[];
  close: () => void;
  confirm: () => void;
}) {
  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) close();
      }}
    >
      <section
        className="dialog archive-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="permission-title"
      >
        <div className="dialog-head">
          <div>
            <h2 id="permission-title">ยืนยันการเปลี่ยนสิทธิ์</h2>
            <p>
              สิทธิ์ใหม่ของ {member.user.name} จะมีผลทันที:{" "}
              {permissions.length
                ? permissionOptions
                    .filter(([key]) => permissions.includes(key))
                    .map(([, label]) => label)
                    .join(", ")
                : "ไม่มีสิทธิ์"}
            </p>
          </div>
          <button className="icon-button" aria-label="ปิด" onClick={close}>
            <X size={20} />
          </button>
        </div>
        <div className="dialog-actions">
          <button type="button" className="button" autoFocus onClick={close}>
            ยกเลิก
          </button>
          <button type="button" className="button primary" onClick={confirm}>
            <ShieldCheck size={17} />
            ยืนยันสิทธิ์
          </button>
        </div>
      </section>
    </div>
  );
}
export function RemoveMemberDialog({
  member,
  close,
  confirm,
}: {
  member: TeamMember;
  close: () => void;
  confirm: () => void;
}) {
  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) close();
      }}
    >
      <section
        className="dialog archive-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="remove-member-title"
      >
        <div className="dialog-head">
          <div>
            <h2 id="remove-member-title">นำสมาชิกออกจากร้าน?</h2>
            <p>
              {member.user.name} จะไม่สามารถเข้าถึงข้อมูลร้านนี้ได้อีก
              และประวัติการทำรายการจะยังคงอยู่
            </p>
          </div>
          <button className="icon-button" aria-label="ปิด" onClick={close}>
            <X size={20} />
          </button>
        </div>
        <div className="dialog-actions">
          <button type="button" className="button" autoFocus onClick={close}>
            ยกเลิก
          </button>
          <button type="button" className="button danger" onClick={confirm}>
            <Trash2 size={17} />
            นำออกจากร้าน
          </button>
        </div>
      </section>
    </div>
  );
}
