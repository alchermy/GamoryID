import { ArrowRight, Check, Lock, PartyPopper } from "lucide-react";
import type { OnboardingCtaAction, OnboardingStep } from "./steps";

const KIND_TAG: Record<OnboardingStep["kind"], string | null> = {
  required: "ต้องทำ",
  recommended: "แนะนำ",
  optional: "ข้ามได้",
  info: null,
};

type Props = {
  steps: OnboardingStep[];
  dismissed: boolean;
  onCta: (action: OnboardingCtaAction) => void;
  onDismiss: () => void;
};

export function OnboardingPanel({
  steps,
  dismissed,
  onCta,
  onDismiss,
}: Props) {
  const done = steps.filter((step) => step.done).length;
  const requiredLeft = steps.filter(
    (step) => step.kind === "required" && !step.done,
  ).length;
  const allRequiredDone = requiredLeft === 0;

  return (
    <div className="onboarding">
      <p className="onboarding-intro">
        ทำตามลำดับด้านล่างเพื่อให้ร้านพร้อมขาย — ข้อ “ต้องทำ” คือขั้นต่ำที่ควรมี
        ส่วนข้ออื่นช่วยให้ร้านสมบูรณ์ขึ้น
      </p>

      <div className="onboarding-progress">
        <div className="onboarding-progress-bar">
          <i style={{ width: `${Math.round((done / steps.length) * 100)}%` }} />
        </div>
        <span>
          เสร็จแล้ว {done.toLocaleString("th-TH")}/
          {steps.length.toLocaleString("th-TH")} ข้อ
        </span>
      </div>

      {allRequiredDone && (
        <div className="onboarding-banner" role="status">
          <PartyPopper size={20} />
          <div>
            <strong>ตั้งค่าครบแล้ว ร้านพร้อมขาย</strong>
            <span>ข้อที่เหลือเป็นตัวเลือกเสริม ทำเมื่อไรก็ได้</span>
          </div>
        </div>
      )}

      <ol className="onboarding-steps">
        {steps.map((step, index) => {
          const tag = KIND_TAG[step.kind];
          return (
            <li
              key={step.id}
              className={`onboarding-step${step.done ? " is-done" : ""}${
                step.locked ? " is-locked" : ""
              }`}
            >
              <span className="onboarding-step-mark" aria-hidden="true">
                {step.done ? (
                  <Check size={16} strokeWidth={3} />
                ) : step.locked ? (
                  <Lock size={14} />
                ) : (
                  index + 1
                )}
              </span>
              <div className="onboarding-step-body">
                <div className="onboarding-step-head">
                  <h3>{step.title}</h3>
                  {step.done ? (
                    <span className="onboarding-step-tag is-ok">เสร็จแล้ว</span>
                  ) : tag ? (
                    <span className="onboarding-step-tag">{tag}</span>
                  ) : null}
                </div>
                <p>{step.description}</p>
                {!step.done && (
                  <div className="onboarding-step-actions">
                    {step.locked && step.lockedNote && (
                      <span className="onboarding-step-note">
                        {step.lockedNote}
                      </span>
                    )}
                    {step.ctas.map((cta) => (
                      <button
                        key={cta.action}
                        type="button"
                        className="button ghost"
                        onClick={() => onCta(cta.action)}
                      >
                        {cta.label}
                        <ArrowRight size={15} />
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </li>
          );
        })}
      </ol>

      <div className="onboarding-foot">
        <button type="button" className="button ghost" onClick={onDismiss}>
          {dismissed ? "ซ่อนไกด์นี้แล้ว" : "ซ่อนไกด์นี้"}
        </button>
        <span>
          ไกด์นี้เปิดกลับมาดูได้เสมอจากเมนู “เริ่มต้นใช้งาน” ด้านซ้าย
        </span>
      </div>
    </div>
  );
}
