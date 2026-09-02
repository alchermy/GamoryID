import type { FormEvent } from "react";
import {
  Crown,
  KeyRound,
  MessagesSquare,
  Pencil,
  ShieldCheck,
  Trash2,
  UserPlus,
  X,
} from "lucide-react";
import { permissionOptions } from "../../config/navigation";
import { DialogHead, Field, PasswordInput } from "../../shared/ui/form-controls";
import type { TeamMember } from "../../types/models";

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

function MemberPermissions({
  member,
  canManage,
  onPermissionsChange,
}: {
  member: TeamMember;
  canManage: boolean;
  onPermissionsChange: (member: TeamMember, permissions: string[]) => void;
}) {
  return (
    <>
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
    </>
  );
}

function MemberRowActions({
  member,
  canManage,
  onEdit,
  onResetPassword,
  onRemove,
  mobile = false,
}: {
  member: TeamMember;
  canManage: boolean;
  onEdit: (member: TeamMember) => void;
  onResetPassword: (member: TeamMember) => void;
  onRemove: (member: TeamMember) => void;
  mobile?: boolean;
}) {
  if (!canManage || member.role !== "staff") return null;
  if (mobile) {
    return (
      <div className="member-mobile-actions">
        <button className="button" onClick={() => onEdit(member)}>
          <Pencil size={16} />
          แก้ไข
        </button>
        <button className="button" onClick={() => onResetPassword(member)}>
          <KeyRound size={16} />
          รีเซ็ตรหัสผ่าน
        </button>
        <button
          className="button danger member-remove-button"
          onClick={() => onRemove(member)}
        >
          <Trash2 size={16} />
          นำออกจากร้าน
        </button>
      </div>
    );
  }
  return (
    <div className="member-row-actions">
      <button
        className="icon-button"
        aria-label={`แก้ไข ${member.user.name}`}
        onClick={() => onEdit(member)}
      >
        <Pencil size={17} />
      </button>
      <button
        className="icon-button"
        aria-label={`รีเซ็ตรหัสผ่านของ ${member.user.name}`}
        onClick={() => onResetPassword(member)}
      >
        <KeyRound size={17} />
      </button>
      <button
        className="icon-button danger-icon"
        aria-label={`นำ ${member.user.name} ออกจากร้าน`}
        onClick={() => onRemove(member)}
      >
        <Trash2 size={17} />
      </button>
    </div>
  );
}

export function TeamPanel({
  members,
  loading,
  error,
  canManage,
  createStaff,
  onPermissionsChange,
  onEdit,
  onResetPassword,
  onRemove,
  retry,
}: {
  members: TeamMember[];
  loading: boolean;
  error: string;
  canManage: boolean;
  createStaff: () => void;
  onPermissionsChange: (member: TeamMember, permissions: string[]) => void;
  onEdit: (member: TeamMember) => void;
  onResetPassword: (member: TeamMember) => void;
  onRemove: (member: TeamMember) => void;
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
          <button className="button primary" onClick={createStaff}>
            <UserPlus size={17} />
            เพิ่มพนักงาน
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
                        <MemberPermissions
                          member={member}
                          canManage={canManage}
                          onPermissionsChange={onPermissionsChange}
                        />
                      </div>
                    </td>
                    <td>
                      <MemberRowActions
                        member={member}
                        canManage={canManage}
                        onEdit={onEdit}
                        onResetPassword={onResetPassword}
                        onRemove={onRemove}
                      />
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
                  <MemberPermissions
                    member={member}
                    canManage={canManage}
                    onPermissionsChange={onPermissionsChange}
                  />
                </fieldset>
                <MemberRowActions
                  member={member}
                  canManage={canManage}
                  onEdit={onEdit}
                  onResetPassword={onResetPassword}
                  onRemove={onRemove}
                  mobile
                />
              </article>
            ))}
          </div>
          {members.length === 0 && (
            <div className="empty">
              <strong>ยังไม่มีพนักงานในร้าน</strong>
              <p>เพิ่มพนักงานเพื่อช่วยจัดการคลังและรายการขาย</p>
            </div>
          )}
        </>
      )}
    </section>
  );
}

export function CreateStaffDialog({
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
        aria-labelledby="create-staff-title"
        onSubmit={submit}
        noValidate
      >
        <DialogHead
          id="create-staff-title"
          title="เพิ่มพนักงาน"
          subtitle="กำหนดอีเมลและรหัสผ่านให้พนักงานใช้เข้าสู่ระบบ"
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
            <Field label="รหัสผ่าน" htmlFor="create-staff-password">
              <PasswordInput
                id="create-staff-password"
                name="password"
                minLength={10}
                required
                autoComplete="new-password"
              />
            </Field>
            <Field label="ยืนยันรหัสผ่าน" htmlFor="create-staff-password-confirmation">
              <PasswordInput
                id="create-staff-password-confirmation"
                name="password_confirmation"
                minLength={10}
                required
                autoComplete="new-password"
              />
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
          <p className="field-help">
            แจ้งอีเมลและรหัสผ่านนี้ให้พนักงานเพื่อเข้าสู่ระบบ Merchant
          </p>
        </div>
        <div className="dialog-actions">
          <button type="button" className="button" onClick={close}>
            ยกเลิก
          </button>
          <button className="button primary">
            <UserPlus size={17} />
            เพิ่มพนักงาน
          </button>
        </div>
      </form>
    </div>
  );
}

export function EditStaffDialog({
  member,
  close,
  submit,
}: {
  member: TeamMember;
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
        aria-labelledby="edit-staff-title"
        onSubmit={submit}
        noValidate
      >
        <DialogHead
          id="edit-staff-title"
          title={`แก้ไข ${member.user.name}`}
          subtitle="เปลี่ยนชื่อและสิทธิ์การใช้งาน"
          close={close}
        />
        <div className="dialog-body">
          <Field label="ชื่อพนักงาน">
            <input name="name" required autoFocus defaultValue={member.user.name} />
          </Field>
          <fieldset className="permission-fieldset">
            <legend>สิทธิ์การใช้งาน</legend>
            {permissionOptions.map(([key, label, description]) => (
              <label key={key} className="permission-item">
                <input
                  type="checkbox"
                  name={`permission-${key}`}
                  defaultChecked={(member.permissions ?? []).includes(key)}
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
            <ShieldCheck size={17} />
            บันทึก
          </button>
        </div>
      </form>
    </div>
  );
}

export function ResetPasswordDialog({
  member,
  close,
  submit,
}: {
  member: TeamMember;
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
        aria-labelledby="reset-password-title"
        onSubmit={submit}
        noValidate
      >
        <DialogHead
          id="reset-password-title"
          title={`รีเซ็ตรหัสผ่านของ ${member.user.name}`}
          subtitle="พนักงานจะถูกออกจากระบบทุกอุปกรณ์และต้องใช้รหัสผ่านใหม่"
          close={close}
        />
        <div className="dialog-body">
          <Field label="รหัสผ่านใหม่" htmlFor="reset-password-new">
            <PasswordInput
              id="reset-password-new"
              name="password"
              minLength={10}
              required
              autoComplete="new-password"
            />
          </Field>
          <Field label="ยืนยันรหัสผ่านใหม่" htmlFor="reset-password-confirmation">
            <PasswordInput
              id="reset-password-confirmation"
              name="password_confirmation"
              minLength={10}
              required
              autoComplete="new-password"
            />
          </Field>
        </div>
        <div className="dialog-actions">
          <button type="button" className="button" onClick={close}>
            ยกเลิก
          </button>
          <button className="button primary">
            <KeyRound size={17} />
            ตั้งรหัสผ่านใหม่
          </button>
        </div>
      </form>
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
