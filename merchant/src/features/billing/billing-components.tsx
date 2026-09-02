import { useState } from "react";
import type { FormEvent } from "react";
import {
  CreditCard,
  FileUp,
  RefreshCw,
  ShieldCheck,
  WalletCards,
  X,
} from "lucide-react";
import { formatDate } from "../../shared/lib/format";
import { AsyncError } from "../../shared/ui/async-state";
import type { BillingCycle, Plan, ShopDetails } from "../../types/models";

export function BillingPanel({
  plans,
  shop,
  loading,
  error,
  canManage,
  busy,
  onTopUp,
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
  onTopUp: (credits: number, file: File) => void;
  onPurchase: (plan: Plan, cycle: BillingCycle) => void;
  onAutoRenewChange: (autoRenew: boolean) => void;
  retry: () => void;
}) {
  const [credits, setCredits] = useState(""),
    [slip, setSlip] = useState<File | null>(null);
  const [cycle, setCycle] = useState<BillingCycle>("monthly");
  const balance = shop?.credit_balance ?? 0;
  const ent = shop?.entitlements;
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!slip) {
      return;
    }
    onTopUp(Number(credits), slip);
  };
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
            <WalletCards size={38} aria-hidden="true" />
          </section>
          <div className="billing-summary">
            <div>
              <span>สถานะปัจจุบัน</span>
              <strong>{shop?.subscription?.status ?? "trialing"}</strong>
            </div>
            <div>
              <span>แพ็กเกจ</span>
              <strong>{shop?.subscription?.plan?.name ?? "ทดลองใช้"}</strong>
            </div>
            <div>
              <span>สิทธิ์เขียนข้อมูลถึง</span>
              <strong>
                {shop?.subscription?.ends_at
                  ? formatDate(shop.subscription.ends_at)
                  : shop?.trial_ends_at
                    ? formatDate(shop.trial_ends_at)
                    : "–"}
              </strong>
            </div>
          </div>
          {shop?.subscription && (
            <section className="auto-renew">
              <div>
                <strong>ต่ออายุอัตโนมัติ</strong>
                <span>
                  เมื่อแพ็กเกจหมดอายุ ระบบจะหักเครดิตตามราคาแพ็กเกจปัจจุบัน
                </span>
              </div>
              <button
                type="button"
                className={`switch ${shop.subscription.auto_renew ? "is-on" : ""}`}
                role="switch"
                aria-checked={shop.subscription.auto_renew}
                disabled={!canManage || busy}
                onClick={() =>
                  onAutoRenewChange(!shop.subscription!.auto_renew)
                }
              >
                <span />
                <b>{shop.subscription.auto_renew ? "เปิด" : "ปิด"}</b>
              </button>
            </section>
          )}
          <form className="top-up-form" onSubmit={submit} noValidate>
            <div>
              <span className="eyebrow">เติมเครดิต</span>
              <h3>แนบสลิปเพื่อเพิ่มเครดิต</h3>
              <p>ระบุยอดให้ตรงกับจำนวนเครดิตที่ต้องการเติม</p>
            </div>
            <label className="field">
              <span className="field-label">จำนวนเครดิต</span>
              <input
                type="number"
                min="1"
                step="1"
                inputMode="numeric"
                value={credits}
                disabled={!canManage || busy}
                onChange={(event) => setCredits(event.target.value)}
                placeholder="เช่น 500"
                required
              />
            </label>
            <label className="top-up-file">
              <FileUp size={18} />
              <span>{slip?.name ?? "เลือกสลิป JPEG หรือ PNG"}</span>
              <input
                type="file"
                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                disabled={!canManage || busy}
                onChange={(event) => setSlip(event.target.files?.[0] ?? null)}
                required
              />
            </label>
            {canManage ? (
              <button
                className="button primary"
                disabled={busy || !credits || !slip}
              >
                <CreditCard size={17} />
                {busy ? "กำลังส่ง…" : "ส่งสลิปเติมเครดิต"}
              </button>
            ) : (
              <button className="button" disabled>
                <ShieldCheck size={17} />
                ไม่มีสิทธิ์เติมเครดิต
              </button>
            )}
          </form>
          {!canManage && (
            <div className="notice" role="status">
              <ShieldCheck size={18} />
              <span>
                คุณดูเครดิตและแพ็กเกจได้
                แต่เฉพาะเจ้าของร้านหรือผู้ได้รับสิทธิ์จัดการชำระเงินเท่านั้นที่เติมเครดิตและซื้อแพ็กเกจได้
              </span>
            </div>
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
          <div className="cycle-toggle" role="tablist" aria-label="รอบชำระ">
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
          <div className="plan-grid">
            {plans.map((plan) => {
              const listPrice =
                cycle === "yearly" ? plan.price_yearly : plan.price_monthly;
              const rawSale =
                cycle === "yearly"
                  ? plan.sale_price_yearly
                  : plan.sale_price_monthly;
              // The API only sends a sale price while the sale is running.
              const saleActive = rawSale != null;
              const price = saleActive ? rawSale : (listPrice ?? 0);
              const isCurrent = ent?.effective_plan.code === plan.code;
              const unavailable = plan.is_free || listPrice == null;
              const enough = balance >= price;
              return (
                <article
                  className={`plan-card ${isCurrent ? "current" : ""} ${plan.code === "growth" ? "is-top" : ""}`}
                  key={plan.id}
                >
                  <div className="plan-card-head">
                    <div>
                      <span className="eyebrow">{plan.code.toUpperCase()}</span>
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
                    <strong className="plan-price">
                      {saleActive && (
                        <s className="muted-text">
                          {listPrice.toLocaleString("th-TH")}
                        </s>
                      )}{" "}
                      {price.toLocaleString("th-TH")}{" "}
                      <small>
                        เครดิต / {cycle === "yearly" ? "ปี" : "เดือน"}
                      </small>
                      {saleActive && plan.sale_label && (
                        <span className="sale-badge">{plan.sale_label}</span>
                      )}
                    </strong>
                  )}
                  <ul>
                    <li>
                      สต็อกพร้อมขาย{" "}
                      {plan.active_inventory_limit == null
                        ? "ไม่จำกัด"
                        : `${plan.active_inventory_limit.toLocaleString("th-TH")} รายการ`}
                    </li>
                    <li>
                      สมาชิก{" "}
                      {plan.member_limit == null
                        ? "ไม่จำกัด"
                        : `${plan.member_limit} คน`}
                    </li>
                    {FEATURE_ORDER.map((key) => (
                      <li
                        key={key}
                        className={plan.features[key] ? "has" : "hasnt"}
                      >
                        {plan.features[key] ? "✓" : "—"} {FEATURE_LABELS[key]}
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
                      aria-describedby={
                        !enough ? `plan-credit-${plan.id}` : undefined
                      }
                      onClick={() => onPurchase(plan, cycle)}
                    >
                      <CreditCard size={17} />
                      {enough
                        ? `ใช้ ${price.toLocaleString("th-TH")} เครดิต`
                        : "เครดิตไม่เพียงพอ"}
                    </button>
                  )}
                  {!unavailable && !enough && canManage && (
                    <small
                      id={`plan-credit-${plan.id}`}
                      className="credit-shortfall"
                    >
                      ต้องการเพิ่มอีก{" "}
                      {(price - balance).toLocaleString("th-TH")} เครดิต
                    </small>
                  )}
                </article>
              );
            })}
          </div>
        </>
      )}
    </section>
  );
}

const FEATURE_ORDER = [
  "bulk_import",
  "advanced_export",
  "activity_log",
  "discord",
  "analytics",
  "priority_support",
] as const;

const FEATURE_LABELS: Record<(typeof FEATURE_ORDER)[number], string> = {
  bulk_import: "นำเข้า Excel/CSV แบบชุด",
  advanced_export: "ส่งออกยอดขาย/กำไร/ประวัติ",
  activity_log: "บันทึกกิจกรรม",
  discord: "เชื่อมต่อ Discord",
  analytics: "วิเคราะห์ต้นทุน–กำไร",
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
