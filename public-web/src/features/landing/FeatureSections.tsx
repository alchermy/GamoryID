import { ShieldCheck } from "lucide-react";
import { merchantRegisterUrl } from "../../config/links";
import { features, workflowSteps } from "./content";
import { Mascot } from "./Mascot";
import { Reveal } from "./Motion";
import { CredentialsMock, StepShot } from "./mocks";

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
          <Reveal
            as="article"
            className="feature-card"
            key={title}
            delay={(index % 3) * 70}
          >
            <span className="feature-accent" aria-hidden="true" />
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
    <section className="section band-deep" id="workflow">
      <div className="container">
        <Reveal className="section-head centered">
          <span className="kicker on-deep">
            <span className="kicker-tick" aria-hidden="true" />
            จากไฟล์ สู่ยอดขาย
          </span>
          <h2>เริ่มใช้ได้ใน 3 ขั้นตอน</h2>
        </Reveal>
        <ol className="steps">
          {workflowSteps.map(([title, text], index) => (
            <Reveal as="li" className="wstep" key={title} delay={index * 90}>
              <span className="wstep-num" aria-hidden="true">
                {index + 1}
              </span>
              <h3>{title}</h3>
              <p>{text}</p>
              <StepShot n={(index + 1) as 1 | 2 | 3} />
            </Reveal>
          ))}
        </ol>
        <Mascot
          pose="search"
          alt=""
          width={168}
          className="workflow-mascot"
        />
      </div>
    </section>
  );
}

export function SpotlightSection() {
  return (
    <section className="section container spotlight" id="security">
      <Reveal className="spotlight-copy">
        <span className="kicker">
          <span className="kicker-tick" aria-hidden="true" />
          ปลอดภัยตั้งแต่ชั้นข้อมูล
        </span>
        <h2>
          <ShieldCheck size={26} aria-hidden="true" />
          ข้อมูลเข้าสู่ระบบ แยกและเข้ารหัส
        </h2>
        <p>
          Username และ Password ของไอดีถูกเก็บแยกจากข้อมูลทั่วไปและเข้ารหัสไว้
          พนักงานจะเปิดดูได้ก็ต่อเมื่อได้รับสิทธิ์ และยืนยันตัวตนล่าสุด —
          ทุกครั้งที่เปิดดูถูกบันทึกไว้ในประวัติกิจกรรม
        </p>
        <a className="btn btn-lg" href={merchantRegisterUrl}>
          ลองเปิดร้านดู
        </a>
      </Reveal>
      <Reveal className="spotlight-visual" delay={80}>
        <CredentialsMock />
      </Reveal>
    </section>
  );
}
