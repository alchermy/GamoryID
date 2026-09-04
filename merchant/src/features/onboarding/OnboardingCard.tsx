import { ArrowRight, Rocket, X } from "lucide-react";
import type { OnboardingStep } from "./steps";

type Props = {
  steps: OnboardingStep[];
  onOpen: () => void;
  onDismiss: () => void;
};

export function OnboardingCard({ steps, onOpen, onDismiss }: Props) {
  const done = steps.filter((step) => step.done).length;
  const next = steps.filter((step) => !step.done && !step.locked).slice(0, 3);
  const pct = Math.round((done / steps.length) * 100);

  return (
    <section className="onboarding-card" aria-label="ตั้งค่าร้าน">
      <span className="onboarding-card-icon">
        <Rocket size={16} />
      </span>
      <div className="onboarding-card-text">
        <strong>
          ตั้งค่าร้านให้พร้อมขาย · {done.toLocaleString("th-TH")}/
          {steps.length.toLocaleString("th-TH")} ข้อ
        </strong>
        {next.length > 0 && (
          <span>ถัดไป: {next.map((step) => step.title).join(" · ")}</span>
        )}
      </div>
      <div className="onboarding-card-bar" aria-hidden="true">
        <i style={{ width: `${pct}%` }} />
      </div>
      <button type="button" className="button ghost" onClick={onOpen}>
        ดูทั้งหมด
        <ArrowRight size={15} />
      </button>
      <button
        type="button"
        className="onboarding-card-close"
        aria-label="ซ่อนการ์ดตั้งค่าร้าน"
        onClick={onDismiss}
      >
        <X size={15} />
      </button>
    </section>
  );
}
