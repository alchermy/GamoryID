import { features, workflowSteps } from "./content";
import { Reveal } from "./Motion";

export function FeaturesSection() {
  return (
    <section className="section container" id="features">
      <Reveal className="section-head">
        <span className="kicker">ทำงานไวขึ้นทุกวัน</span>
        <h2>สร้างมาเพื่อเวิร์กโฟลว์ของร้านจริง</h2>
        <p>
          พื้นที่ทำงานที่ชัดเจนสำหรับงานซ้ำทุกวัน ตั้งแต่นำเข้าหลายร้อยรายการ
          จนถึงค้นหาไอดีให้ลูกค้าในไม่กี่วินาที
        </p>
      </Reveal>
      <div className="features">
        {features.map(([Icon, title, text], index) => (
          <Reveal key={title} delay={(index % 3) * 70}>
            <article className="feature">
              <div className="feature-icon">
                <Icon size={21} />
              </div>
              <h3>{title}</h3>
              <p>{text}</p>
            </article>
          </Reveal>
        ))}
      </div>
    </section>
  );
}

export function WorkflowSection() {
  return (
    <section className="section tint" id="workflow">
      <div className="container">
        <Reveal className="section-head centered">
          <span className="kicker">จากไฟล์ สู่ยอดขาย</span>
          <h2>เริ่มใช้ได้ใน 3 ขั้นตอน</h2>
        </Reveal>
        <div className="steps">
          {workflowSteps.map((step, index) => (
            <Reveal key={step.number} delay={index * 90}>
              <article className="step">
                <span className="step-num">{step.number}</span>
                <div className="step-dot" aria-hidden="true" />
                <h3>{step.title}</h3>
                <p>{step.text}</p>
              </article>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}
