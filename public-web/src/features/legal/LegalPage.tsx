import { useEffect } from "react";
import { Link } from "react-router-dom";
import { AlertTriangle, ArrowLeft } from "lucide-react";
import { SiteFooter } from "../landing/SiteFooter";
import { SiteHeader } from "../landing/SiteHeader";
import {
  EFFECTIVE_DATE,
  LEGAL_DRAFT,
  PRIVACY_VERSION,
  TERMS_VERSION,
} from "./meta";
import { PRIVACY_DOC } from "./privacy";
import { TERMS_DOC } from "./terms";

export function LegalPage({ kind }: { kind: "terms" | "privacy" }) {
  const doc = kind === "terms" ? TERMS_DOC : PRIVACY_DOC;
  const version = kind === "terms" ? TERMS_VERSION : PRIVACY_VERSION;

  useEffect(() => {
    window.scrollTo(0, 0);
    document.title = `${doc.title} — GamoryID`;
  }, [doc.title]);

  return (
    <div className="site">
      <SiteHeader />
      <main className="legal-page">
        <article className="legal">
          <Link to="/" className="legal-back">
            <ArrowLeft size={16} /> กลับหน้าแรก
          </Link>
          <h1>{doc.title}</h1>
          <p className="legal-meta">
            มีผลบังคับใช้ {EFFECTIVE_DATE} · ฉบับ {version}
          </p>

          {LEGAL_DRAFT && (
            <p className="legal-draft-note" role="note">
              <AlertTriangle size={16} aria-hidden="true" />
              เอกสารฉบับนี้อยู่ระหว่างการตรวจทานโดยที่ปรึกษากฎหมาย
              อาจมีการปรับปรุงถ้อยคำก่อนเปิดให้บริการวงกว้าง
            </p>
          )}

          <nav className="legal-toc" aria-label="สารบัญ">
            <ol>
              {doc.sections.map((section, index) => (
                <li key={section.id}>
                  <a href={`#${section.id}`}>
                    {index + 1}. {section.heading}
                  </a>
                </li>
              ))}
            </ol>
          </nav>

          {doc.sections.map((section, index) => (
            <section key={section.id} id={section.id}>
              <h2>
                {index + 1}. {section.heading}
              </h2>
              {section.body}
            </section>
          ))}

          <p className="legal-links">
            {kind === "terms" ? (
              <Link to="/privacy">อ่านนโยบายความเป็นส่วนตัว →</Link>
            ) : (
              <Link to="/terms">อ่านข้อกำหนดการใช้บริการ →</Link>
            )}
          </p>
        </article>
      </main>
      <SiteFooter />
    </div>
  );
}
