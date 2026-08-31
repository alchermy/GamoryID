import { features, workflowSteps } from "./content";

export function FeaturesSection() {
  return (
    <section className="section container" id="features">
      <div className="section-head">
        <span className="kicker">ทำงานไวขึ้นทุกวัน</span>
        <h2>สร้างมาเพื่อเวิร์กโฟลว์ของร้านจริง</h2>
        <p>
          พื้นที่ทำงานที่ชัดเจนสำหรับงานซ้ำทุกวัน ตั้งแต่นำเข้าหลายร้อยรายการ
          จนถึงค้นหาไอดีให้ลูกค้าในไม่กี่วินาที
        </p>
      </div>
      <div className="features">
        {features.map(([Icon, title, text]) => (
          <article className="feature" key={title}>
            <div className="feature-icon">
              <Icon size={21} />
            </div>
            <h3>{title}</h3>
            <p>{text}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

export function WorkflowSection() {
  return (
    <section className="section tint" id="workflow">
      <div className="container">
        <div className="section-head">
          <span className="kicker">จากไฟล์ สู่ยอดขาย</span>
          <h2>เริ่มใช้ได้ใน 3 ขั้นตอน</h2>
        </div>
        <div className="steps">
          {workflowSteps.map((step) => (
            <article className="step" key={step.number}>
              <span className="step-num">STEP {step.number}</span>
              <h3>{step.title}</h3>
              <p>{step.text}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
