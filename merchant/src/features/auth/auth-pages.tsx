import { useCallback, useEffect, useState } from "react";
import type { FormEvent } from "react";
import { ArrowRight } from "lucide-react";
import {
  Navigate,
  Outlet,
  useLocation,
  useOutletContext,
} from "react-router-dom";
import { apiRequest, prepareCsrf } from "../../api";
import { Field, PasswordInput } from "../../shared/ui/form-controls";
import type { SessionUser } from "../../types/models";
import { RegistrationJourney } from "./registration-journey";
import { LoginWorkspaceContext } from "./login-workspace-context";

type RegistrationField = "name" | "shop_name" | "email" | "password";

function validateLogin(data: FormData) {
  const errors: Partial<Record<RegistrationField, string>> = {};
  const email = String(data.get("email") ?? "").trim();
  const password = String(data.get("password") ?? "");

  if (!email) errors.email = "กรอกอีเมลสำหรับเข้าใช้งาน";
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
    errors.email = "กรอกอีเมลให้ถูกต้อง เช่น name@example.com";
  if (!password) errors.password = "กรอกรหัสผ่าน";

  return errors;
}

function validateRegistration(data: FormData) {
  const errors: Partial<Record<RegistrationField, string>> = {};
  const name = String(data.get("name") ?? "").trim();
  const shopName = String(data.get("shop_name") ?? "").trim();
  const email = String(data.get("email") ?? "").trim();
  const password = String(data.get("password") ?? "");

  if (!name) errors.name = "กรอกชื่อของคุณ";
  if (!shopName) errors.shop_name = "กรอกชื่อร้าน";
  if (!email) errors.email = "กรอกอีเมลสำหรับเข้าใช้งาน";
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
    errors.email = "กรอกอีเมลให้ถูกต้อง เช่น name@example.com";
  if (password.length < 10)
    errors.password = "รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร";

  return errors;
}

export function AuthScreen({ mode }: { mode: "login" | "register" }) {
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [fieldErrors, setFieldErrors] = useState<
      Partial<Record<RegistrationField, string>>
    >({});
  useEffect(() => {
    document.title = `${mode === "login" ? "เข้าสู่ระบบ" : "สมัครใช้งาน"} — GamoryID`;
  }, [mode]);
  const submit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    const d = new FormData(e.currentTarget);
    const nextErrors =
      mode === "register" ? validateRegistration(d) : validateLogin(d);
    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors(nextErrors);
      const firstField = Object.keys(nextErrors)[0] as RegistrationField;
      const target = e.currentTarget.elements.namedItem(firstField);
      if (target instanceof HTMLElement) target.focus();
      setBusy(false);
      return;
    }
    try {
      const csrf = await prepareCsrf();
      const payload =
        mode === "login"
          ? { email: d.get("email"), password: d.get("password") }
          : {
              name: d.get("name"),
              email: d.get("email"),
              password: d.get("password"),
              password_confirmation: d.get("password"),
              shop_name: d.get("shop_name"),
            };
      await apiRequest(`/auth/${mode}`, {
        method: "POST",
        headers: csrf,
        body: JSON.stringify(payload),
      });
      window.location.href = "/";
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ไม่สามารถเข้าสู่ระบบได้",
      );
    } finally {
      setBusy(false);
    }
  };
  const clearFieldError = (field: RegistrationField) => {
    if (!fieldErrors[field] && !error) return;
    setFieldErrors((current) => ({ ...current, [field]: undefined }));
    setError("");
  };
  const isRegister = mode === "register";
  return (
    <main className={`auth-page auth-page-${mode}`}>
      <div className="merchant-auth-shell">
        <section className="auth-panel" aria-labelledby={`${mode}-title`}>
          <a className="auth-brand" href="/" aria-label="GamoryID หน้าหลัก">
            <img src="/mascot/gammy-main.png" alt="" />
            Gamory<span>ID</span>
            <small>MERCHANT</small>
          </a>
          <h1 id={`${mode}-title`}>
            {mode === "login" ? "เข้าสู่ระบบร้านค้า" : "เปิดร้านบน GamoryID"}
          </h1>
          <p>
            {mode === "login"
              ? "กลับมาจัดการคลังไอดี การจอง และงานขายของร้านคุณ"
              : "สร้างพื้นที่จัดการสต็อกของร้านคุณ และเริ่มทดลองใช้ได้ทันที"}
          </p>
          {error && (
            <div className="auth-error" role="alert">
              {error}
            </div>
          )}
          <form onSubmit={submit} noValidate>
            {isRegister && (
              <div className="auth-form-grid">
                <Field label="ชื่อของคุณ" htmlFor="register-name">
                  <input
                    id="register-name"
                    name="name"
                    required
                    autoComplete="name"
                    placeholder="เช่น พีท"
                    aria-invalid={Boolean(fieldErrors.name) || undefined}
                    aria-describedby={
                      fieldErrors.name ? "register-name-error" : undefined
                    }
                    onChange={() => clearFieldError("name")}
                  />
                  {fieldErrors.name ? (
                    <span className="field-error" id="register-name-error">
                      {fieldErrors.name}
                    </span>
                  ) : null}
                </Field>
                <Field label="ชื่อร้าน" htmlFor="register-shop-name">
                  <input
                    id="register-shop-name"
                    name="shop_name"
                    required
                    autoComplete="organization"
                    placeholder="เช่น Nexus Store"
                    aria-invalid={Boolean(fieldErrors.shop_name) || undefined}
                    aria-describedby={
                      fieldErrors.shop_name
                        ? "register-shop-name-error"
                        : undefined
                    }
                    onChange={() => clearFieldError("shop_name")}
                  />
                  {fieldErrors.shop_name ? (
                    <span className="field-error" id="register-shop-name-error">
                      {fieldErrors.shop_name}
                    </span>
                  ) : null}
                </Field>
              </div>
            )}
            <Field label="อีเมล" htmlFor={`${mode}-email`}>
              <input
                id={`${mode}-email`}
                name="email"
                type="email"
                required
                autoComplete="email"
                inputMode="email"
                placeholder={isRegister ? "name@example.com" : undefined}
                aria-invalid={Boolean(fieldErrors.email) || undefined}
                aria-describedby={
                  fieldErrors.email ? `${mode}-email-error` : undefined
                }
                onChange={() => clearFieldError("email")}
              />
              {fieldErrors.email ? (
                <span className="field-error" id={`${mode}-email-error`}>
                  {fieldErrors.email}
                </span>
              ) : null}
            </Field>
            <Field label="รหัสผ่าน" htmlFor={`${mode}-password`}>
              <PasswordInput
                id={`${mode}-password`}
                name="password"
                minLength={mode === "register" ? 10 : undefined}
                required
                placeholder={isRegister ? "สร้างรหัสผ่านของคุณ" : undefined}
                autoComplete={
                  mode === "login" ? "current-password" : "new-password"
                }
                ariaInvalid={Boolean(fieldErrors.password)}
                ariaDescribedBy={
                  isRegister
                    ? fieldErrors.password
                      ? "register-password-hint register-password-error"
                      : "register-password-hint"
                    : fieldErrors.password
                      ? "login-password-error"
                      : undefined
                }
                onChange={() => clearFieldError("password")}
              />
              {fieldErrors.password ? (
                <span className="field-error" id={`${mode}-password-error`}>
                  {fieldErrors.password}
                </span>
              ) : null}
            </Field>
            {isRegister ? (
              <p className="field-help" id="register-password-hint">
                ใช้รหัสผ่านอย่างน้อย 10 ตัวอักษร
              </p>
            ) : null}
            <button className="button primary auth-submit" disabled={busy}>
              {busy
                ? "กำลังดำเนินการ…"
                : mode === "login"
                  ? "เข้าสู่ระบบ"
                  : "สร้างร้านและเริ่มทดลอง"}
              {!busy && isRegister ? (
                <ArrowRight size={18} aria-hidden="true" />
              ) : null}
            </button>
          </form>
          <p className="auth-switch">
            {mode === "login" ? "ยังไม่มีร้าน?" : "มีบัญชีแล้ว?"}{" "}
            <a href={mode === "login" ? "/register" : "/login"}>
              {mode === "login" ? "เปิดร้านใช้ฟรี" : "เข้าสู่ระบบ"}
            </a>
          </p>
        </section>
        {isRegister ? <RegistrationJourney /> : <LoginWorkspaceContext />}
      </div>
    </main>
  );
}

export function StatePage({
  code,
  title,
  text,
}: {
  code: string;
  title: string;
  text: string;
}) {
  useEffect(() => {
    document.title = `${title} — GamoryID`;
  }, [title]);
  return (
    <main className="state-page">
      <img src="/mascot/gammy-search.png" alt="Gammy" />
      <span>{code}</span>
      <h1>{title}</h1>
      <p>{text}</p>
      <a className="button blue" href="/">
        กลับหน้าภาพรวม
      </a>
    </main>
  );
}
export function AuthGate() {
  const [session, setSession] = useState<SessionUser | null>(null);
  const [checking, setChecking] = useState(import.meta.env.MODE !== "test");
  const [error, setError] = useState("");
  const location = useLocation();
  const justVerified =
    location.pathname === "/verify-email" &&
    new URLSearchParams(location.search).get("verified") === "1";
  const checkSession = useCallback(async () => {
    setChecking(true);
    setError("");
    try {
      const result = await apiRequest<{ user: SessionUser }>("/auth/me");
      setSession(result.user);
    } catch (reason) {
      const status =
        typeof reason === "object" && reason !== null && "status" in reason
          ? (reason as { status?: number }).status
          : undefined;
      if (status === 401 || status === 419) {
        window.location.replace("/login");
        return;
      }
      setError(
        reason instanceof Error
          ? reason.message
          : "ไม่สามารถตรวจสอบการเข้าสู่ระบบได้",
      );
    } finally {
      setChecking(false);
    }
  }, []);
  useEffect(() => {
    if (import.meta.env.MODE !== "test" && !justVerified) void checkSession();
  }, [checkSession, justVerified]);
  useEffect(() => {
    if (justVerified) return;
    document.title = checking
      ? "กำลังตรวจสอบสิทธิ์ — GamoryID"
      : "เชื่อมต่อระบบไม่สำเร็จ — GamoryID";
  }, [checking, justVerified]);
  if (import.meta.env.MODE === "test") return <Outlet context={undefined} />;
  if (justVerified) return <EmailVerifiedScreen />;
  if (checking)
    return (
      <main className="auth-gate" aria-busy="true" aria-live="polite">
        <section>
          <img src="/mascot/gammy-secure.png" alt="Gammy" />
          <p className="eyebrow">GAMORYID SECURE ACCESS</p>
          <h1>กำลังตรวจสอบสิทธิ์ของคุณ…</h1>
          <p>โปรดรอสักครู่</p>
        </section>
      </main>
    );
  if (session) {
    if (!session.email_verified_at && location.pathname !== "/verify-email") {
      return <Navigate to="/verify-email" replace />;
    }
    if (session.email_verified_at && location.pathname === "/verify-email") {
      return <Navigate to="/" replace />;
    }
    return <Outlet context={session} />;
  }
  return (
    <main className="auth-gate">
      <section>
        <img src="/mascot/gammy-secure.png" alt="Gammy" />
        <p className="eyebrow">GAMORYID SECURE ACCESS</p>
        <h1>เชื่อมต่อระบบไม่สำเร็จ</h1>
        <p>{error || "กรุณาลองใหม่อีกครั้ง"}</p>
        <div className="auth-gate-actions">
          <button className="button blue" onClick={() => void checkSession()}>
            ลองใหม่
          </button>
          <a className="button" href="/login">
            ไปหน้าเข้าสู่ระบบ
          </a>
        </div>
      </section>
    </main>
  );
}

function EmailVerifiedScreen() {
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    document.title = "ยืนยันอีเมลสำเร็จ — GamoryID";
  }, []);

  const goToLogin = async () => {
    setBusy(true);
    try {
      const csrf = await prepareCsrf();
      await apiRequest("/auth/logout", { method: "POST", headers: csrf });
    } catch {
      // No active session to end (e.g. link opened on another device) — carry on.
    }
    window.location.href = "/login";
  };

  return (
    <main className="auth-gate">
      <section>
        <img src="/mascot/gammy-sold.png" alt="Gammy" />
        <p className="eyebrow">ยืนยันอีเมลสำเร็จ</p>
        <h1>ยืนยันอีเมลเรียบร้อยแล้ว</h1>
        <p>
          บัญชีร้านของคุณพร้อมใช้งานแล้ว
          <br />
          กรุณาเข้าสู่ระบบอีกครั้งเพื่อเริ่มต้นใช้งาน
        </p>
        <div className="auth-gate-actions">
          <button
            className="button blue"
            type="button"
            disabled={busy}
            onClick={() => void goToLogin()}
          >
            {busy ? "กำลังพาไปหน้าเข้าสู่ระบบ…" : "เข้าสู่ระบบ"}
          </button>
        </div>
      </section>
    </main>
  );
}

export function VerifyEmailScreen() {
  const session = useOutletContext<SessionUser>();
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    document.title = "ยืนยันอีเมล — GamoryID";
  }, []);

  const resend = async () => {
    setBusy(true);
    setMessage("");
    setError("");
    try {
      const csrf = await prepareCsrf();
      const result = await apiRequest<{ message: string }>(
        "/email/verification-notification",
        { method: "POST", headers: csrf },
      );
      setMessage(result.message);
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ไม่สามารถส่งอีเมลยืนยันได้",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="auth-gate">
      <section>
        <img src="/mascot/gammy-secure.png" alt="Gammy" />
        <p className="eyebrow">ยืนยันเจ้าของร้าน</p>
        <h1>ตรวจสอบอีเมลของคุณ</h1>
        <p>
          เราส่งลิงก์ยืนยันไปที่ <strong>{session.email}</strong>
          <br />
          เปิดลิงก์ในอีเมลเพื่อยืนยัน จากนั้นเข้าสู่ระบบอีกครั้ง
        </p>
        {message ? (
          <div className="notice" role="status">
            {message}
          </div>
        ) : null}
        {error ? (
          <div className="auth-error" role="alert">
            {error}
          </div>
        ) : null}
        <div className="auth-gate-actions">
          <button
            className="button blue"
            type="button"
            disabled={busy}
            onClick={() => void resend()}
          >
            {busy ? "กำลังส่ง…" : "ส่งอีเมลยืนยันอีกครั้ง"}
          </button>
          <a className="button" href="/login">
            กลับหน้าเข้าสู่ระบบ
          </a>
        </div>
      </section>
    </main>
  );
}
