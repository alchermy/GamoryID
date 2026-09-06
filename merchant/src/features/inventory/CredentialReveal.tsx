import { useEffect, useRef, useState } from "react";
import type { FormEvent } from "react";
import { Check, Copy, Eye, EyeOff, KeyRound } from "lucide-react";
import { apiRequest, shopRequest } from "../../api";
import { DialogHead, Field, PasswordInput } from "../../shared/ui/form-controls";

type Creds = { username: string; password: string; recovery_email: string };

function CopyRow({
  label,
  value,
  secret = false,
}: {
  label: string;
  value: string;
  secret?: boolean;
}) {
  const [shown, setShown] = useState(!secret);
  const [copied, setCopied] = useState(false);
  const display = !value ? "–" : shown ? value : "••••••••••";
  return (
    <div className="credential-row">
      <span className="credential-label">{label}</span>
      <code className="credential-value">{display}</code>
      {secret && value && (
        <button
          type="button"
          className="icon-button"
          aria-label={shown ? "ซ่อน" : "แสดง"}
          onClick={() => setShown((v) => !v)}
        >
          {shown ? <EyeOff size={15} /> : <Eye size={15} />}
        </button>
      )}
      {value && (
        <button
          type="button"
          className="icon-button"
          aria-label={`คัดลอก ${label}`}
          onClick={() => {
            void navigator.clipboard?.writeText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
          }}
        >
          {copied ? <Check size={15} /> : <Copy size={15} />}
        </button>
      )}
    </div>
  );
}

function ReauthModal({
  twoFactorEnabled,
  onClose,
  onDone,
  notify,
}: {
  twoFactorEnabled: boolean;
  onClose: () => void;
  onDone: () => void;
  notify: (message: string) => void;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const firstField = useRef<HTMLInputElement>(null);

  useEffect(() => {
    firstField.current?.focus();
  }, []);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy) return;
    const data = new FormData(event.currentTarget);
    setBusy(true);
    setError("");
    try {
      await apiRequest("/security/reauth", {
        method: "POST",
        body: JSON.stringify({
          password: String(data.get("password") ?? ""),
          ...(twoFactorEnabled
            ? { code: String(data.get("code") ?? "") }
            : {}),
        }),
      });
      onDone();
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ยืนยันตัวตนไม่สำเร็จ",
      );
      notify("ยืนยันตัวตนไม่สำเร็จ");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
      onKeyDown={(e) => {
        if (e.key === "Escape" && !busy) onClose();
      }}
    >
      <form
        className="dialog reauth-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="reauth-title"
        onSubmit={submit}
        noValidate
      >
        <DialogHead
          id="reauth-title"
          title="ยืนยันตัวตนก่อนดูรหัสผ่าน"
          subtitle={
            twoFactorEnabled
              ? "กรอกรหัสผ่านบัญชีและรหัส 6 หลักจากแอป Authenticator"
              : "กรอกรหัสผ่านบัญชีของคุณอีกครั้ง"
          }
          close={onClose}
        />
        <div className="dialog-body">
          {error && (
            <div className="auth-error" role="alert">
              {error}
            </div>
          )}
          <Field label="รหัสผ่านบัญชี">
            <PasswordInput
              name="password"
              required
              autoComplete="current-password"
            />
          </Field>
          {twoFactorEnabled && (
            <Field label="รหัส 6 หลักจากแอป">
              <input
                ref={firstField}
                name="code"
                inputMode="numeric"
                pattern="\d{6}"
                maxLength={6}
                autoComplete="one-time-code"
                required
              />
            </Field>
          )}
        </div>
        <div className="dialog-actions">
          <button
            type="button"
            className="button"
            onClick={onClose}
            disabled={busy}
          >
            ยกเลิก
          </button>
          <button className="button primary" disabled={busy}>
            {busy ? "กำลังยืนยัน…" : "ยืนยัน"}
          </button>
        </div>
      </form>
    </div>
  );
}

export function CredentialReveal({
  itemId,
  shopId,
  hasCredentials,
  twoFactorEnabled,
  notify,
}: {
  itemId: number;
  shopId: number;
  hasCredentials: boolean;
  twoFactorEnabled: boolean;
  notify: (message: string) => void;
}) {
  const [creds, setCreds] = useState<Creds | null>(null);
  const [reauthOpen, setReauthOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  // Never let revealed secrets linger when moving to another item.
  useEffect(() => {
    setCreds(null);
    setReauthOpen(false);
  }, [itemId]);

  if (!hasCredentials) return null;

  const doReveal = async () => {
    if (busy) return;
    setBusy(true);
    try {
      const result = await shopRequest<{ data: Creds }>(
        `/inventory/${itemId}/credentials`,
        shopId,
      );
      setCreds(result.data);
    } catch (reason) {
      const status =
        reason && typeof reason === "object" && "status" in reason
          ? (reason as { status?: number }).status
          : undefined;
      if (status === 428) {
        setReauthOpen(true);
      } else if (status === 403) {
        notify("ไม่มีสิทธิ์ดูข้อมูลลับของไอดี");
      } else {
        notify(
          reason instanceof Error ? reason.message : "เปิดดูข้อมูลลับไม่สำเร็จ",
        );
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      {!creds ? (
        <button
          type="button"
          className="button"
          onClick={() => void doReveal()}
          disabled={busy}
        >
          <KeyRound size={17} />
          {busy ? "กำลังเปิด…" : "ดูรหัสผ่าน"}
        </button>
      ) : (
        <div className="credential-reveal" role="group" aria-label="ข้อมูลเข้าสู่ระบบ">
          <CopyRow label="Username" value={creds.username} />
          <CopyRow label="Password" value={creds.password} secret />
          <CopyRow label="Recovery email" value={creds.recovery_email} />
          <button
            type="button"
            className="button ghost compact"
            onClick={() => setCreds(null)}
          >
            ซ่อน
          </button>
        </div>
      )}

      {reauthOpen && (
        <ReauthModal
          twoFactorEnabled={twoFactorEnabled}
          notify={notify}
          onClose={() => setReauthOpen(false)}
          onDone={() => {
            setReauthOpen(false);
            void doReveal();
          }}
        />
      )}
    </>
  );
}
