import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import QRCode from "qrcode";
import {
  Check,
  CheckCircle2,
  CreditCard,
  FileUp,
  RefreshCw,
  ShieldCheck,
  X,
} from "lucide-react";
import { formatDate } from "../../shared/lib/format";
import { promptPayPayload } from "../../shared/lib/promptpay";
import { promptPayMobile, promptPayName } from "../../config/payment";
import { AsyncError } from "../../shared/ui/async-state";
import type { BillingCycle, Plan, ShopDetails } from "../../types/models";

export function BillingPanel({
  plans,
  shop,
  loading,
  error,
  canManage,
  busy,
  onOpenTopUp,
  onPurchase,
  onAutoRenewChange,
  retry,
}: {
  plans: Plan[];
  shop: ShopDetails | null;
  loading: boolean;
  error: string;
  canManage: boolean;
  busy: boolean;
  onOpenTopUp: () => void;
  onPurchase: (plan: Plan, cycle: BillingCycle) => void;
  onAutoRenewChange: (autoRenew: boolean) => void;
  retry: () => void;
}) {
  const [cycle, setCycle] = useState<BillingCycle>("monthly");
  const [showPlans, setShowPlans] = useState(false);
  const balance = shop?.credit_balance ?? 0;
  const ent = shop?.entitlements;
  const sub = shop?.subscription ?? null;
  const currentCode = ent?.effective_plan.code;
  const currentPlan = plans.find((p) => p.code === currentCode) ?? null;
  const topUp = shop?.latest_top_up ?? null;
  const pendingTopUp =
    topUp?.status === "pending" || topUp?.status === "pending_review";

  const cyc = sub?.billing_cycle === "yearly" ? "yearly" : "monthly";
  const curList =
    cyc === "yearly" ? currentPlan?.price_yearly : currentPlan?.price_monthly;
  const curSale =
    cyc === "yearly"
      ? currentPlan?.sale_price_yearly
      : currentPlan?.sale_price_monthly;
  const curPrice = curSale ?? curList ?? null;

  return (
    <section className="panel management-panel" aria-labelledby="billing-title">
      <div className="panel-head">
        <div>
          <h2 id="billing-title">เครดิตและแพ็กเกจ</h2>
          <small>
            เติมเครดิตก่อน แล้วใช้เครดิตซื้อแพ็กเกจหรือให้ระบบต่ออายุอัตโนมัติ
          </small>
        </div>
      </div>
      {error ? (
        <AsyncError error={error} retry={retry} />
      ) : loading ? (
        <div className="management-loading" aria-live="polite">
          กำลังโหลดข้อมูลเครดิต…
        </div>
      ) : (
        <>
          <section className="credit-hero" aria-label="เครดิตคงเหลือ">
            <div>
              <span>เครดิตคงเหลือ</span>
              <strong>
                {balance.toLocaleString("th-TH")} <small>เครดิต</small>
              </strong>
              <p>1 เครดิต = 1 บาท · เครดิตใช้เฉพาะค่าแพ็กเกจของร้านนี้</p>
            </div>
            {canManage && (
              <button
                type="button"
                className="credit-topup-btn"
                onClick={onOpenTopUp}
                disabled={busy}
              >
                <CreditCard size={17} />
                เติมเครดิต
              </button>
            )}
          </section>

          {pendingTopUp && topUp && (
            <div className="notice" role="status">
              <RefreshCw size={18} />
              <span>
                กำลังตรวจสลิปเติมเครดิต {topUp.credits.toLocaleString("th-TH")}{" "}
                เครดิต — เครดิตจะเข้าบัญชีเมื่อแอดมินอนุมัติ · ดูสถานะได้ที่เมนู
                “ประวัติธุรกรรม”
              </span>
            </div>
          )}
          {topUp?.status === "rejected" && (
            <div className="notice notice-warning" role="status">
              <ShieldCheck size={18} />
              <span>
                สลิปเติมเครดิต {topUp.credits.toLocaleString("th-TH")} เครดิต
                ไม่ผ่านการตรวจสอบ
                {topUp.review_note ? ` — ${topUp.review_note}` : ""}
              </span>
            </div>
          )}
          {!canManage && (
            <div className="notice" role="status">
              <ShieldCheck size={18} />
              <span>
                คุณดูเครดิตและแพ็กเกจได้
                แต่เฉพาะเจ้าของร้านหรือผู้ได้รับสิทธิ์จัดการชำระเงินเท่านั้นที่เติมเครดิตและซื้อแพ็กเกจได้
              </span>
            </div>
          )}

          <section
            className="current-plan-card"
            aria-labelledby="current-plan-title"
          >
            <div className="current-plan-head">
              <div>
                <span className="plan-tag">
                  {PLAN_TAG[currentCode ?? ""] ?? "แพ็กเกจของร้าน"}
                </span>
                <h3 id="current-plan-title">
                  {ent?.effective_plan.name ?? "ทดลองใช้"}
                  <span
                    className={`status-pill ${sub?.status ?? ent?.status ?? "trialing"}`}
                  >
                    {STATUS_LABEL[sub?.status ?? ent?.status ?? "trialing"] ??
                      sub?.status ??
                      "ทดลองใช้"}
                  </span>
                </h3>
              </div>
              <div className="current-plan-price">
                {sub && curPrice != null ? (
                  <>
                    <strong>{curPrice.toLocaleString("th-TH")}</strong>
                    <small> เครดิต / {cyc === "yearly" ? "ปี" : "เดือน"}</small>
                  </>
                ) : (
                  <strong className="muted-text">ทดลองใช้ฟรี</strong>
                )}
              </div>
            </div>
            <p className="current-plan-until">
              {sub?.ends_at
                ? `ใช้ได้ถึง ${formatDate(sub.ends_at)}`
                : shop?.trial_ends_at
                  ? `ทดลองใช้ถึง ${formatDate(shop.trial_ends_at)}`
                  : "—"}
            </p>

            {ent && (
              <ul className="plan-feature-list">
                <li>
                  <Check size={16} /> สต็อกพร้อมขาย{" "}
                  {limitLabel(ent.effective_plan.active_inventory_limit, "รายการ")}
                </li>
                <li>
                  <Check size={16} /> สมาชิกในร้าน{" "}
                  {limitLabel(ent.effective_plan.member_limit, "คน")}
                </li>
                {FEATURE_ORDER.filter(
                  (k) => ent.effective_plan.features[k],
                ).map((k) => (
                  <li key={k}>
                    <Check size={16} /> {FEATURE_LABELS[k]}
                  </li>
                ))}
              </ul>
            )}

            {ent && (
              <div className="plan-usage" aria-label="การใช้งานเทียบโควตา">
                <UsageBar
                  label="สต็อกพร้อมขาย"
                  used={ent.usage.inventory_active}
                  limit={ent.effective_plan.active_inventory_limit}
                />
                <UsageBar
                  label="สมาชิกในร้าน"
                  used={ent.usage.members}
                  limit={ent.effective_plan.member_limit}
                />
              </div>
            )}

            {sub && (
              <div className="auto-renew">
                <div>
                  <strong>ต่ออายุอัตโนมัติ</strong>
                  <span>
                    เมื่อแพ็กเกจหมดอายุ ระบบจะหักเครดิตตามราคาแพ็กเกจปัจจุบัน
                  </span>
                </div>
                <button
                  type="button"
                  className={`switch ${sub.auto_renew ? "is-on" : ""}`}
                  role="switch"
                  aria-checked={sub.auto_renew}
                  disabled={!canManage || busy}
                  onClick={() => onAutoRenewChange(!sub.auto_renew)}
                >
                  <span />
                  <b>{sub.auto_renew ? "เปิด" : "ปิด"}</b>
                </button>
              </div>
            )}

            {canManage && (
              <button
                type="button"
                className="button plan-change-btn"
                onClick={() => setShowPlans((v) => !v)}
              >
                <RefreshCw size={16} />
                {showPlans ? "ซ่อนแพ็กเกจอื่น" : "เปลี่ยนแพ็กเกจ"}
              </button>
            )}
          </section>

          {showPlans && (
            <section className="plan-select" aria-label="เลือกแพ็กเกจ">
              <div className="plan-select-head">
                <h3>เลือกแพ็กเกจ</h3>
                <div
                  className="cycle-toggle"
                  role="tablist"
                  aria-label="รอบชำระ"
                >
                  {(["monthly", "yearly"] as BillingCycle[]).map((c) => (
                    <button
                      key={c}
                      type="button"
                      role="tab"
                      aria-selected={cycle === c}
                      className={cycle === c ? "is-on" : ""}
                      onClick={() => setCycle(c)}
                    >
                      {c === "monthly" ? "รายเดือน" : "รายปี · ประหยัดกว่า"}
                    </button>
                  ))}
                </div>
              </div>
              <div className="plan-grid">
                {plans.map((plan) => (
                  <PlanCard
                    key={plan.id}
                    plan={plan}
                    cycle={cycle}
                    balance={balance}
                    busy={busy}
                    canManage={canManage}
                    isCurrent={currentCode === plan.code}
                    onPurchase={onPurchase}
                  />
                ))}
              </div>
            </section>
          )}
        </>
      )}
    </section>
  );
}

const PLAN_TAG: Record<string, string> = {
  free: "เริ่มต้น",
  starter: "พ่อค้าคนเดียว",
  growth: "ร้านมีทีม",
  pro: "ร้านใหญ่",
};

const STATUS_LABEL: Record<string, string> = {
  trialing: "ทดลองใช้",
  active: "ใช้งาน",
  grace_read_only: "อ่านอย่างเดียว",
  pending_payment: "รอชำระเงิน",
  suspended: "ระงับ",
  cancelled: "ยกเลิก",
  expired: "หมดอายุ",
};

function limitLabel(v: number | null, unit: string): string {
  return v == null ? "ไม่จำกัด" : `${v.toLocaleString("th-TH")} ${unit}`;
}

function PlanCard({
  plan,
  cycle,
  balance,
  busy,
  canManage,
  isCurrent,
  onPurchase,
}: {
  plan: Plan;
  cycle: BillingCycle;
  balance: number;
  busy: boolean;
  canManage: boolean;
  isCurrent: boolean;
  onPurchase: (plan: Plan, cycle: BillingCycle) => void;
}) {
  const listPrice =
    cycle === "yearly" ? plan.price_yearly : plan.price_monthly;
  const rawSale =
    cycle === "yearly" ? plan.sale_price_yearly : plan.sale_price_monthly;
  const saleActive = rawSale != null;
  const price = saleActive ? rawSale : (listPrice ?? 0);
  const unavailable = plan.is_free || listPrice == null;
  const enough = balance >= price;
  const priceText = `${price.toLocaleString("th-TH")} เครดิต`;

  return (
    <article
      className={`plan-card ${isCurrent ? "current" : ""} ${plan.code === "growth" ? "is-top" : ""}`}
    >
      {plan.code === "growth" && <span className="plan-flag">แนะนำ</span>}
      <div className="plan-card-head">
        <div>
          <span className="plan-tag">
            {PLAN_TAG[plan.code] ?? plan.code.toUpperCase()}
          </span>
          <h3>{plan.name}</h3>
        </div>
        {isCurrent && <span className="current-badge">ใช้อยู่</span>}
      </div>
      {plan.is_free ? (
        <strong className="plan-price">
          ฟรี <small>ตลอดการใช้งาน</small>
        </strong>
      ) : listPrice == null ? (
        <strong className="plan-price muted-text">
          <small>ไม่มีรอบรายปี</small>
        </strong>
      ) : (
        <div className="plan-price">
          {saleActive && (
            <span className="plan-price-was">
              <s>{listPrice.toLocaleString("th-TH")}</s>
              {plan.sale_label && (
                <span className="sale-badge">{plan.sale_label}</span>
              )}
            </span>
          )}
          <strong className="plan-price-now">
            {price.toLocaleString("th-TH")}
            <small> เครดิต / {cycle === "yearly" ? "ปี" : "เดือน"}</small>
          </strong>
        </div>
      )}
      <ul className="plan-feature-list">
        <li>
          <Check size={15} /> สต็อกพร้อมขาย{" "}
          {limitLabel(plan.active_inventory_limit, "รายการ")}
        </li>
        <li>
          <Check size={15} /> สมาชิก {limitLabel(plan.member_limit, "คน")}
        </li>
        {FEATURE_ORDER.filter((k) => plan.features[k]).map((k) => (
          <li key={k}>
            <Check size={15} /> {FEATURE_LABELS[k]}
          </li>
        ))}
      </ul>
      {unavailable ? (
        <button className="button plan-upload" disabled>
          {plan.is_free ? "แพ็กเริ่มต้น" : "ไม่เปิดขายรอบนี้"}
        </button>
      ) : !canManage ? (
        <button className="button plan-upload" disabled>
          <ShieldCheck size={17} />
          ไม่มีสิทธิ์ซื้อแพ็กเกจ
        </button>
      ) : (
        <button
          type="button"
          className="button blue plan-upload"
          disabled={busy || !enough}
          aria-describedby={!enough ? `plan-credit-${plan.id}` : undefined}
          onClick={() => onPurchase(plan, cycle)}
        >
          <CreditCard size={17} />
          {!enough
            ? "เครดิตไม่เพียงพอ"
            : isCurrent
              ? `ต่ออายุ · ใช้ ${priceText}`
              : `ใช้ ${priceText}`}
        </button>
      )}
      {!unavailable && !enough && canManage && (
        <small id={`plan-credit-${plan.id}`} className="credit-shortfall">
          ต้องการเพิ่มอีก {(price - balance).toLocaleString("th-TH")} เครดิต
        </small>
      )}
    </article>
  );
}

const FEATURE_ORDER = [
  "storefront",
  "bulk_import",
  "advanced_export",
  "activity_log",
  "discord",
  "analytics",
  "early_access",
  "priority_support",
] as const;

const FEATURE_LABELS: Record<(typeof FEATURE_ORDER)[number], string> = {
  storefront: "หน้าร้านสาธารณะ (แชร์ลิงก์ให้ลูกค้า)",
  bulk_import: "นำเข้า Excel/CSV แบบชุด",
  advanced_export: "ส่งออกยอดขาย/กำไร/ประวัติ",
  activity_log: "บันทึกกิจกรรม",
  discord: "เชื่อมต่อ Discord",
  analytics: "วิเคราะห์ต้นทุน–กำไร",
  early_access: "ได้ใช้ฟีเจอร์ใหม่ก่อนใคร",
  priority_support: "ซัพพอร์ตให้ความสำคัญก่อน",
};

function UsageBar({
  label,
  used,
  limit,
}: {
  label: string;
  used: number;
  limit: number | null;
}) {
  const pct = limit == null ? 0 : Math.min(100, Math.round((used / limit) * 100));
  const over = limit != null && used > limit;
  return (
    <div className={`usage-bar ${over ? "is-over" : ""}`}>
      <span>
        {label}
        <b>
          {used.toLocaleString("th-TH")} /{" "}
          {limit == null ? "ไม่จำกัด" : limit.toLocaleString("th-TH")}
        </b>
      </span>
      {limit != null && (
        <i>
          <span style={{ width: `${pct}%` }} />
        </i>
      )}
    </div>
  );
}
export function PurchasePlanDialog({
  plan,
  cycle,
  balance,
  busy,
  close,
  confirm,
}: {
  plan: Plan;
  cycle: BillingCycle;
  balance: number;
  busy: boolean;
  close: () => void;
  confirm: () => void;
}) {
  const listPrice =
    cycle === "yearly" ? plan.price_yearly : plan.price_monthly;
  const rawSale =
    cycle === "yearly" ? plan.sale_price_yearly : plan.sale_price_monthly;
  const saleActive = rawSale != null;
  const price = saleActive ? rawSale : (listPrice ?? 0);
  const days = cycle === "yearly" ? plan.yearly_days : plan.monthly_days;
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
        aria-labelledby="purchase-plan-title"
      >
        <div className="dialog-head">
          <div>
            <h2 id="purchase-plan-title">
              ยืนยันซื้อแพ็กเกจ {plan.name} ·{" "}
              {cycle === "yearly" ? "รายปี" : "รายเดือน"}
            </h2>
            <p>
              จะหัก {price.toLocaleString("th-TH")} เครดิตจากยอดคงเหลือ{" "}
              {balance.toLocaleString("th-TH")} เครดิตทันที และเริ่มสิทธิ์ {days}{" "}
              วัน
            </p>
          </div>
          <button
            className="icon-button"
            aria-label="ปิด"
            disabled={busy}
            onClick={close}
          >
            <X size={20} />
          </button>
        </div>
        <div className="dialog-actions">
          <button
            type="button"
            className="button"
            autoFocus
            disabled={busy}
            onClick={close}
          >
            ยกเลิก
          </button>
          <button
            type="button"
            className="button primary"
            disabled={busy}
            onClick={confirm}
          >
            <CreditCard size={17} />
            {busy ? "กำลังตัดเครดิต…" : "ยืนยันและตัดเครดิต"}
          </button>
        </div>
      </section>
    </div>
  );
}
export function AutoRenewDialog({
  close,
  confirm,
}: {
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
        aria-labelledby="auto-renew-title"
      >
        <div className="dialog-head">
          <div>
            <h2 id="auto-renew-title">เปิดต่ออายุอัตโนมัติ?</h2>
            <p>
              เมื่อแพ็กเกจหมดอายุ ระบบจะหักเครดิตตามราคาแพ็กเกจ ณ เวลานั้น
              หากเครดิตไม่พอ ร้านจะเข้าสู่โหมดอ่านอย่างเดียวตามรอบปกติ
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
            <RefreshCw size={17} />
            เปิดต่ออายุอัตโนมัติ
          </button>
        </div>
      </section>
    </div>
  );
}

export function TopUpDialog({
  busy,
  close,
  submit,
}: {
  busy: boolean;
  close: () => void;
  submit: (credits: number, file: File) => Promise<boolean>;
}) {
  const [phase, setPhase] = useState<"form" | "done">("form");
  const [credits, setCredits] = useState("");
  const [slip, setSlip] = useState<File | null>(null);
  const [slipPreview, setSlipPreview] = useState("");
  const [qr, setQr] = useState("");
  const amount = Number(credits);
  const validAmount = Number.isInteger(amount) && amount > 0;

  useEffect(() => {
    if (!slip) {
      setSlipPreview("");
      return;
    }
    const url = URL.createObjectURL(slip);
    setSlipPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [slip]);

  useEffect(() => {
    let cancelled = false;
    const payload = promptPayPayload(
      promptPayMobile,
      validAmount ? amount : undefined,
    );
    QRCode.toDataURL(payload, { width: 240, margin: 1 })
      .then((url) => {
        if (!cancelled) setQr(url);
      })
      .catch(() => {
        if (!cancelled) setQr("");
      });
    return () => {
      cancelled = true;
    };
  }, [amount, validAmount]);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!slip || !validAmount) return;
    const ok = await submit(amount, slip);
    if (ok) setPhase("done");
  };

  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) close();
      }}
    >
      <section
        className="dialog topup-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="topup-title"
      >
        <div className="dialog-head">
          <div>
            <h2 id="topup-title">เติมเครดิต</h2>
            <p>
              {phase === "done"
                ? "ทีมงานได้รับสลิปของคุณแล้ว"
                : "โอนตามยอดที่ต้องการ แล้วแนบสลิปให้ทีมงานตรวจสอบ"}
            </p>
          </div>
          <button
            className="icon-button"
            aria-label="ปิด"
            disabled={busy}
            onClick={close}
          >
            <X size={20} />
          </button>
        </div>
        {phase === "done" ? (
          <div className="dialog-body topup-done">
            <span className="topup-done-icon" aria-hidden="true">
              <CheckCircle2 size={38} />
            </span>
            <h3>ส่งสลิปเรียบร้อยแล้ว</h3>
            <p>
              ขอบคุณที่ใช้บริการ 🙏 ทีมงานกำลังตรวจสอบสลิปของคุณ เครดิต{" "}
              {amount.toLocaleString("th-TH")} เครดิต
              จะเข้าบัญชีร้านทันทีที่อนุมัติ (โดยปกติภายในไม่กี่ชั่วโมง) ·
              ติดตามสถานะได้ที่เมนู “ประวัติธุรกรรม”
            </p>
            <div className="dialog-actions">
              <button
                type="button"
                className="button primary"
                onClick={close}
                autoFocus
              >
                ปิด
              </button>
            </div>
          </div>
        ) : (
        <form className="dialog-body topup-body" onSubmit={onSubmit} noValidate>
          <div className="topup-pay">
            <div className="topup-qr">
              {qr ? (
                <img src={qr} alt="QR พร้อมเพย์สำหรับโอนเติมเครดิต" />
              ) : (
                <div className="topup-qr-fallback">กำลังสร้าง QR…</div>
              )}
              <span className="topup-qr-tag">PromptPay</span>
            </div>
            <dl className="topup-pay-detail">
              <div>
                <dt>ชื่อบัญชี</dt>
                <dd>{promptPayName}</dd>
              </div>
              <div>
                <dt>พร้อมเพย์ (เบอร์)</dt>
                <dd>{formatMobile(promptPayMobile)}</dd>
              </div>
              <div>
                <dt>ยอดโอน</dt>
                <dd>
                  {validAmount
                    ? `${amount.toLocaleString("th-TH")} บาท`
                    : "ระบุจำนวนเครดิตก่อน"}
                </dd>
              </div>
            </dl>
            <p className="topup-note">
              1 เครดิต = 1 บาท · สแกน QR แล้วยอดจะถูกกรอกให้อัตโนมัติเมื่อระบุ
              จำนวนเครดิต
            </p>
          </div>
          <div className="topup-form">
            <label className="field">
              <span className="field-label">จำนวนเครดิตที่ต้องการเติม</span>
              <input
                type="number"
                min="1"
                step="1"
                inputMode="numeric"
                value={credits}
                disabled={busy}
                onChange={(event) => setCredits(event.target.value)}
                placeholder="เช่น 500"
                autoFocus
                required
              />
            </label>
            <label className="top-up-file">
              <FileUp size={18} />
              <span>{slip?.name ?? "เลือกสลิป JPEG หรือ PNG"}</span>
              <input
                type="file"
                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                disabled={busy}
                onChange={(event) => setSlip(event.target.files?.[0] ?? null)}
                required
              />
            </label>
            {slipPreview ? (
              <figure className="topup-slip-preview">
                <img src={slipPreview} alt="ตัวอย่างสลิปที่แนบ" />
                <button
                  type="button"
                  className="topup-slip-clear"
                  onClick={() => setSlip(null)}
                  disabled={busy}
                >
                  <X size={14} /> เอาสลิปออก
                </button>
              </figure>
            ) : (
              <p className="topup-slip-hint">
                แนบรูปสลิปโอนเงิน (JPEG/PNG) ระบบจะแสดงตัวอย่างให้ตรวจก่อนส่ง
              </p>
            )}
          </div>
          <div className="dialog-actions topup-actions">
            <button
              type="button"
              className="button"
              disabled={busy}
              onClick={close}
            >
              ยกเลิก
            </button>
            <button
              type="submit"
              className="button primary"
              disabled={busy || !validAmount || !slip}
            >
              <CreditCard size={17} />
              {busy ? "กำลังส่ง…" : "ส่งสลิปเติมเครดิต"}
            </button>
          </div>
        </form>
        )}
      </section>
    </div>
  );
}

function formatMobile(digits: string): string {
  return digits.length === 10
    ? `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6)}`
    : digits;
}
