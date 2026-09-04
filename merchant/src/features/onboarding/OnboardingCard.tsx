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

  return (
    <section className="onboarding-card" aria-label="ตั้งค่าร้าน">
      <button
        type="button"
        className="onboarding-card-close"
        aria-label="ซ่อนการ์ดตั้งค่าร้าน"
        onClick={onDismiss}
      >
        <X size={16} />
      </button>
      <div className="onboarding-card-head">
        <span className="onboarding-card-icon">
          <Rocket size={18} />
        </span>
        <div>
          <strong>ตั้งค่าร้านให้พร้อมขาย</strong>
          <span>
            เสร็จแล้ว {done.toLocaleString("th-TH")}/
            {steps.length.toLocaleString("th-TH")} ข้อ
          </span>
        </div>
      </div>
      <div className="onboarding-card-bar">
        <i style={{ width: `${Math.round((done / steps.length) * 100)}%` }} />
      </div>
      <ul className="onboarding-card-next">
        {next.map((step) => (
          <li key={step.id}>{step.title}</li>
        ))}
      </ul>
      <button type="button" className="button ghost" onClick={onOpen}>
        ดูทั้งหมด
        <ArrowRight size={15} />
      </button>
    </section>
  );
}
