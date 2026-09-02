import { features, workflowSteps } from "./content";
import { Reveal } from "./Motion";

export function FeaturesSection() {
  return (
    <section className="section container" id="features">
      <Reveal className="section-head">
        <span className="kicker">
          <span className="kicker-tick" aria-hidden="true" />
          ทำงานไวขึ้นทุกวัน
        </span>
        <h2>สร้างมาเพื่อเวิร์กโฟลว์ของร้านจริง</h2>
        <p>
          พื้นที่ทำงานที่ชัดเจนสำหรับงานซ้ำทุกวัน ตั้งแต่นำเข้าหลายร้อยรายการ
          จนถึงค้นหาไอดีให้ลูกค้าในไม่กี่วินาที
        </p>
      </Reveal>
      <div className="features">
        {features.map(([Icon, title, text], index) => (
          <Reveal as="article" className="feature" key={title} delay={(index % 3) * 70}>
            <span className="feature-corner" aria-hidden="true" />
            <div className="feature-icon">
              <Icon size={20} />
            </div>
            <h3>{title}</h3>
            <p>{text}</p>
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
          <span className="kicker">
            <span className="kicker-tick" aria-hidden="true" />
            จากไฟล์ สู่ยอดขาย
          </span>
          <h2>เริ่มใช้ได้ใน 3 ขั้นตอน</h2>
        </Reveal>
        <ol className="steps">
          {workflowSteps.map(([title, text], index) => (
            <Reveal as="li" className="step" key={title} delay={index * 90}>
              <span className="step-node" aria-hidden="true">
                <span className="step-num">{index + 1}</span>
              </span>
              <h3>{title}</h3>
              <p>{text}</p>
            </Reveal>
          ))}
        </ol>
      </div>
    </section>
  );
}
