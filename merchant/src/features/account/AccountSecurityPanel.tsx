import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { Check, Copy, ShieldCheck, ShieldOff } from "lucide-react";
import QRCode from "qrcode";
import { apiRequest } from "../../api";
import { Field, PasswordInput } from "../../shared/ui/form-controls";
import type { SessionUser } from "../../types/models";

type Phase = "idle" | "begin" | "confirm" | "disable";

export function AccountSecurityPanel({
  session,
  notify,
}: {
  session: SessionUser | null;
  notify: (message: string) => void;
}) {
  const [enabled, setEnabled] = useState(session?.two_factor_enabled ?? false);
  const [phase, setPhase] = useState<Phase>("idle");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [secret, setSecret] = useState("");
  const [qr, setQr] = useState("");
  const [otpauthUri, setOtpauthUri] = useState("");

  useEffect(() => {
    if (!otpauthUri) return;
    let cancelled = false;
    QRCode.toDataURL(otpauthUri, { width: 220, margin: 1 })
      .then((url) => !cancelled && setQr(url))
      .catch(() => !cancelled && setQr(""));
    return () => {
      cancelled = true;
    };
  }, [otpauthUri]);

  const reset = () => {
    setPhase("idle");
    setError("");
    setSecret("");
    setQr("");
    setOtpauthUri("");
  };

  const fail = (reason: unknown) =>
    setError(reason instanceof Error ? reason.message : "ดำเนินการไม่สำเร็จ");

  const begin = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy) return;
    const password = String(
      new FormData(event.currentTarget).get("password") ?? "",
    );
    setBusy(true);
    setError("");
    try {
      const result = await apiRequest<{ secret: string; otpauth_uri: string }>(
        "/security/2fa/begin",
        { method: "POST", body: JSON.stringify({ password }) },
      );
      setSecret(result.secret);
      setOtpauthUri(result.otpauth_uri);
      setPhase("confirm");
    } catch (reason) {
      fail(reason);
    } finally {
      setBusy(false);
    }
  };

  const confirm = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy) return;
    const code = String(new FormData(event.currentTarget).get("code") ?? "");
    setBusy(true);
    setError("");
    try {
      await apiRequest("/security/2fa/confirm", {
        method: "POST",
        body: JSON.stringify({ code }),
      });
      setEnabled(true);
      reset();
      notify("เปิด 2FA แล้ว");
    } catch (reason) {
      fail(reason);
    } finally {
      setBusy(false);
    }
  };

  const disable = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy) return;
    const data = new FormData(event.currentTarget);
    setBusy(true);
    setError("");
    try {
      await apiRequest("/security/2fa/disable", {
        method: "POST",
        body: JSON.stringify({
          password: String(data.get("password") ?? ""),
          code: String(data.get("code") ?? ""),
        }),
      });
      setEnabled(false);
      reset();
      notify("ปิด 2FA แล้ว");
    } catch (reason) {
      fail(reason);
    } finally {
      setBusy(false);
    }
  };

  return (
    <section
      className="panel management-panel account-security-panel"
      aria-labelledby="account-security-title"
    >
      <div className="panel-head">
        <div>
          <h2 id="account-security-title">บัญชีและความปลอดภัย</h2>
          <p>{session?.email}</p>
        </div>
      </div>

      <section className="account-section">
        <div className="account-section-head">
          <div>
            <h3>ยืนยันตัวตนสองชั้น (2FA)</h3>
            <p>
              เพิ่มด่านก่อนดูรหัสผ่านไอดี — ถ้าเปิด ต้องกรอกรหัสจากแอป
              Authenticator (เช่น Google Authenticator) ทุกครั้งที่ยืนยันตัวตน
              ยืนยันครั้งเดียวใช้ได้ 30 นาที
            </p>
          </div>
          <span className={`status-pill ${enabled ? "active" : "expired"}`}>
            {enabled ? "เปิดอยู่" : "ปิดอยู่"}
          </span>
        </div>

        {error && (
          <div className="auth-error" role="alert">
            {error}
          </div>
        )}

        {!enabled && phase === "idle" && (
          <button
            type="button"
            className="button primary"
            onClick={() => {
              setError("");
              setPhase("begin");
            }}
          >
            <ShieldCheck size={16} /> เปิด 2FA
          </button>
        )}

        {!enabled && phase === "begin" && (
          <form className="account-form" onSubmit={begin}>
            <Field label="ยืนยันรหัสผ่านบัญชี">
              <PasswordInput
                name="password"
                required
                autoComplete="current-password"
              />
            </Field>
            <div className="account-form-actions">
              <button
                type="button"
                className="button"
                onClick={reset}
                disabled={busy}
              >
                ยกเลิก
              </button>
              <button className="button primary" disabled={busy}>
                {busy ? "กำลังตรวจสอบ…" : "ต่อไป"}
              </button>
            </div>
          </form>
        )}

        {!enabled && phase === "confirm" && (
          <div className="account-2fa-setup">
            <ol className="account-2fa-steps">
              <li>เปิดแอป Authenticator แล้วสแกน QR นี้</li>
            </ol>
            {qr ? (
              <img className="account-2fa-qr" src={qr} alt="QR สำหรับตั้งค่า 2FA" />
            ) : (
              <div className="account-2fa-qr placeholder" />
            )}
            <button
              type="button"
              className="button ghost compact account-2fa-secret"
              onClick={() => {
                void navigator.clipboard?.writeText(secret);
                notify("คัดลอกรหัสลับแล้ว");
              }}
            >
              <Copy size={14} /> {secret}
            </button>
            <form className="account-form" onSubmit={confirm}>
              <Field label="กรอกรหัส 6 หลักจากแอป">
                <input
                  name="code"
                  inputMode="numeric"
                  pattern="\d{6}"
                  maxLength={6}
                  autoComplete="one-time-code"
                  required
                  autoFocus
                />
              </Field>
              <div className="account-form-actions">
                <button
                  type="button"
                  className="button"
                  onClick={reset}
                  disabled={busy}
                >
                  ยกเลิก
                </button>
                <button className="button primary" disabled={busy}>
                  {busy ? "กำลังยืนยัน…" : "ยืนยันและเปิดใช้"}
                </button>
              </div>
            </form>
          </div>
        )}

        {enabled && phase === "idle" && (
          <div className="account-2fa-on">
            <p className="account-2fa-on-note">
              <Check size={15} /> บัญชีนี้ต้องใช้รหัส 2FA เมื่อยืนยันตัวตน
            </p>
            <button
              type="button"
              className="button danger subtle-danger"
              onClick={() => {
                setError("");
                setPhase("disable");
              }}
            >
              <ShieldOff size={16} /> ปิด 2FA
            </button>
          </div>
        )}

        {enabled && phase === "disable" && (
          <form className="account-form" onSubmit={disable}>
            <Field label="รหัสผ่านบัญชี">
              <PasswordInput
                name="password"
                required
                autoComplete="current-password"
              />
            </Field>
            <Field label="รหัส 6 หลักจากแอป">
              <input
                name="code"
                inputMode="numeric"
                pattern="\d{6}"
                maxLength={6}
                autoComplete="one-time-code"
                required
              />
            </Field>
            <div className="account-form-actions">
              <button
                type="button"
                className="button"
                onClick={reset}
                disabled={busy}
              >
                ยกเลิก
              </button>
              <button
                className="button danger subtle-danger"
                disabled={busy}
              >
                {busy ? "กำลังปิด…" : "ปิด 2FA"}
              </button>
            </div>
          </form>
        )}
      </section>
    </section>
  );
}
