import { useCallback, useEffect, useState } from "react";
import type { FormEvent } from "react";
import { Check } from "lucide-react";
import { Outlet } from "react-router-dom";
import { apiRequest, prepareCsrf } from "../../api";
import { formatDate } from "../../shared/lib/format";
import { Field, PasswordInput } from "../../shared/ui/form-controls";
import type { SessionUser } from "../../types/models";

export function AuthScreen({ mode }: { mode: "login" | "register" }) {
  const [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  useEffect(() => {
    document.title = `${mode === "login" ? "เข้าสู่ระบบ" : "สมัครใช้งาน"} — GamoryID`;
  }, [mode]);
  const submit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    const d = new FormData(e.currentTarget);
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
  return (
    <main className="auth-page">
      <section className="auth-panel">
        <a className="auth-brand" href="/">
          <img src="/mascot/gammy-main.png" alt="Gammy" />
          Gamory<span>ID</span>
        </a>
        <h1>{mode === "login" ? "ยินดีต้อนรับกลับ" : "สร้างร้านของคุณ"}</h1>
        <p>
          {mode === "login"
            ? "เข้าสู่ระบบเพื่อจัดการสต็อกและงานขาย"
            : "ทดลองใช้ฟรี 30 วัน ตั้งค่าร้านและเริ่มนำเข้าได้เลย"}
        </p>
        {error && (
          <div className="auth-error" role="alert">
            {error}
          </div>
        )}
        <form onSubmit={submit} noValidate>
          {mode === "register" && (
            <>
              <Field label="ชื่อของคุณ">
                <input name="name" required autoComplete="name" />
              </Field>
              <Field label="ชื่อร้าน">
                <input name="shop_name" required />
              </Field>
            </>
          )}
          <Field label="อีเมล">
            <input name="email" type="email" required autoComplete="email" />
          </Field>
          <Field label="รหัสผ่าน">
            <PasswordInput
              name="password"
              minLength={8}
              required
              autoComplete={
                mode === "login" ? "current-password" : "new-password"
              }
            />
          </Field>
          <button className="button primary auth-submit" disabled={busy}>
            {busy
              ? "กำลังดำเนินการ…"
              : mode === "login"
                ? "เข้าสู่ระบบ"
                : "สร้างร้านและเริ่มทดลอง"}
          </button>
        </form>
        <p className="auth-switch">
          {mode === "login" ? "ยังไม่มีบัญชี?" : "มีบัญชีแล้ว?"}{" "}
          <a href={mode === "login" ? "/register" : "/login"}>
            {mode === "login" ? "เริ่มใช้ฟรี" : "เข้าสู่ระบบ"}
          </a>
        </p>
      </section>
      <aside className="auth-art">
        <div>
          <span className="eyebrow">GAMMY OPS CONSOLE</span>
          <h2>
            จัดสต็อกเป็นระบบ
            <br />
            หาไอดีได้ในไม่กี่วินาที
          </h2>
          <ul>
            <li>
              <Check />
              แท็กเฉพาะ 5 ตัว
            </li>
            <li>
              <Check />
              นำเข้าหลายรายการด้วย CSV
            </li>
            <li>
              <Check />
              สิทธิ์แยกสำหรับทีมร้าน
            </li>
          </ul>
        </div>
        <img src="/mascot/gammy-secure.png" alt="Gammy ดูแลข้อมูล" />
      </aside>
    </main>
  );
}

export function InviteScreen({ token }: { token: string }) {
  const [invite, setInvite] = useState<{
      shop_name: string;
      email: string;
      expires_at: string;
    } | null>(null),
    [loading, setLoading] = useState(true),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  useEffect(() => {
    let active = true;
    void apiRequest<{
      data: { shop_name: string; email: string; expires_at: string };
    }>(`/team-invitations/${encodeURIComponent(token)}`)
      .then((result) => {
        if (active) setInvite(result.data);
      })
      .catch((reason) => {
        if (active)
          setError(
            reason instanceof Error
              ? reason.message
              : "ลิงก์คำเชิญไม่พร้อมใช้งาน",
          );
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [token]);
  useEffect(() => {
    document.title = "เข้าร่วมร้าน — GamoryID";
  }, []);
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    const data = new FormData(event.currentTarget);
    try {
      const csrf = await prepareCsrf();
      await apiRequest(
        `/team-invitations/${encodeURIComponent(token)}/accept`,
        {
          method: "POST",
          headers: csrf,
          body: JSON.stringify({
            name: data.get("name"),
            password: data.get("password"),
            password_confirmation: data.get("password_confirmation"),
          }),
        },
      );
      window.location.href = "/";
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ไม่สามารถเข้าร่วมร้านได้",
      );
    } finally {
      setBusy(false);
    }
  };
  return (
    <main className="auth-page">
      <section className="auth-panel">
        <a className="auth-brand" href="/">
          <img src="/mascot/gammy-main.png" alt="Gammy" />
          Gamory<span>ID</span>
        </a>
        {loading ? (
          <div className="management-loading" aria-live="polite">
            กำลังตรวจสอบคำเชิญ…
          </div>
        ) : error && !invite ? (
          <>
            <h1>ลิงก์คำเชิญใช้ไม่ได้</h1>
            <p>{error}</p>
            <a className="button" href="/login">
              กลับไปเข้าสู่ระบบ
            </a>
          </>
        ) : (
          <>
            <h1>เข้าร่วม {invite?.shop_name}</h1>
            <p>
              คำเชิญนี้ส่งถึง {invite?.email} และหมดอายุ{" "}
              {invite ? formatDate(invite.expires_at) : "–"}
            </p>
            {error && (
              <div className="auth-error" role="alert">
                {error}
              </div>
            )}
            <form onSubmit={submit} noValidate>
              <Field label="ชื่อที่แสดง">
                <input name="name" required autoComplete="name" autoFocus />
              </Field>
              <Field label="รหัสผ่าน">
                <PasswordInput
                  name="password"
                  minLength={8}
                  required
                  autoComplete="new-password"
                />
              </Field>
              <Field label="ยืนยันรหัสผ่าน">
                <PasswordInput
                  name="password_confirmation"
                  minLength={8}
                  required
                  autoComplete="new-password"
                />
              </Field>
              <p className="field-help">
                หากมีบัญชี GamoryID ด้วยอีเมลนี้แล้ว
                ให้ใช้รหัสผ่านเดิมเพื่อเข้าร่วมร้าน
              </p>
              <button className="button primary auth-submit" disabled={busy}>
                {busy ? "กำลังเข้าร่วม…" : "เข้าร่วมร้าน"}
              </button>
            </form>
          </>
        )}
      </section>
      <aside className="auth-art">
        <div>
          <span className="eyebrow">GAMMY TEAM ACCESS</span>
          <h2>
            เริ่มช่วยร้าน
            <br />
            อย่างเป็นระบบ
          </h2>
          <ul>
            <li>
              <Check />
              สิทธิ์ตามที่เจ้าของร้านกำหนด
            </li>
            <li>
              <Check />
              เข้าร่วมผ่านลิงก์ครั้งเดียว
            </li>
            <li>
              <Check />
              เริ่มงานได้ตามสิทธิ์ที่ได้รับ
            </li>
          </ul>
        </div>
        <img src="/mascot/gammy-secure.png" alt="Gammy ช่วยจัดการทีม" />
      </aside>
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
    if (import.meta.env.MODE !== "test") void checkSession();
  }, [checkSession]);
  useEffect(() => {
    document.title = checking
      ? "กำลังตรวจสอบสิทธิ์ — GamoryID"
      : "เชื่อมต่อระบบไม่สำเร็จ — GamoryID";
  }, [checking]);
  if (import.meta.env.MODE === "test") return <Outlet context={undefined} />;
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
  if (session) return <Outlet context={session} />;
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
