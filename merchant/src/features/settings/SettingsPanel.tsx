import type { FormEvent } from "react";
import { Link2, Phone, Save, Settings } from "lucide-react";
import { AsyncError } from "../../shared/ui/async-state";
import { Field } from "../../shared/ui/form-controls";
import type { Shop, ShopDetails } from "../../types/models";

export function SettingsPanel({
  shop,
  loading,
  error,
  onSubmit,
  retry,
}: {
  shop: ShopDetails | Shop | null;
  loading: boolean;
  error: string;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  retry: () => void;
}) {
  if (error)
    return (
      <section className="panel management-panel">
        <AsyncError error={error} retry={retry} />
      </section>
    );
  if (loading && !shop)
    return (
      <section className="panel management-panel">
        <div className="management-loading" aria-live="polite">
          กำลังโหลดตั้งค่าร้าน…
        </div>
      </section>
    );
  if (!shop) return null;
  const copyFooter =
    "inventory_copy_footer" in shop ? (shop.inventory_copy_footer ?? "") : "";
  return (
    <section
      className="panel management-panel settings-panel"
      aria-labelledby="settings-title"
    >
      <div className="panel-head settings-panel-head">
        <span className="settings-panel-icon" aria-hidden="true">
          <Settings size={20} />
        </span>
        <div>
          <h2 id="settings-title">ตั้งค่าร้าน</h2>
          <small>
            จัดการข้อมูลร้าน ช่องทางติดต่อ และข้อความมาตรฐานสำหรับส่งให้ลูกค้า
          </small>
        </div>
      </div>
      <form
        className="settings-form"
        key={`${shop.id}-${shop.name}-${shop.slug ?? ""}-${copyFooter}`}
        onSubmit={onSubmit}
        noValidate
      >
        <div className="settings-form-body">
          <section
            className="settings-section"
            aria-labelledby="shop-info-title"
          >
            <div className="settings-section-head">
              <div>
                <h3 id="shop-info-title">ข้อมูลร้าน</h3>
                <p>ข้อมูลหลักที่ใช้ระบุและแนะนำร้านของคุณ</p>
              </div>
              <span>01</span>
            </div>
            <div className="settings-grid">
              <Field label="ชื่อร้าน">
                <input name="name" defaultValue={shop.name} required />
              </Field>
              <Field label="Slug ร้าน">
                <input
                  name="slug"
                  defaultValue={shop.slug ?? ""}
                  pattern="[A-Za-z0-9_-]+"
                  required
                />
                <small className="field-help">
                  ใช้เป็นชื่ออ้างอิงลิงก์ร้านในอนาคต
                </small>
              </Field>
              <Field label="คำอธิบายร้าน" full>
                <textarea
                  className="resize-none settings-description"
                  name="description"
                  defaultValue={shop.description ?? ""}
                  maxLength={1000}
                  rows={4}
                  placeholder="บอกลูกค้าว่าร้านของคุณมีจุดเด่นอะไร"
                />
              </Field>
            </div>
          </section>
          <section
            className="settings-section"
            aria-labelledby="shop-contact-title"
          >
            <div className="settings-section-head">
              <div>
                <h3 id="shop-contact-title">ช่องทางติดต่อ</h3>
                <p>ช่องทางที่ลูกค้าสามารถใช้ติดต่อร้านได้สะดวก</p>
              </div>
              <span>02</span>
            </div>
            <div className="settings-grid settings-contact-grid">
              <Field label="Facebook URL">
                <span className="input-with-icon">
                  <Link2 size={16} />
                  <input
                    name="facebook_url"
                    type="url"
                    defaultValue={shop.facebook_url ?? ""}
                    placeholder="https://facebook.com/..."
                  />
                </span>
              </Field>
              <Field label="LINE URL">
                <span className="input-with-icon">
                  <Link2 size={16} />
                  <input
                    name="line_url"
                    type="url"
                    defaultValue={shop.line_url ?? ""}
                    placeholder="https://line.me/..."
                  />
                </span>
              </Field>
              <Field label="เบอร์โทร">
                <span className="input-with-icon">
                  <Phone size={16} />
                  <input
                    name="phone"
                    type="tel"
                    defaultValue={shop.phone ?? ""}
                    inputMode="tel"
                    placeholder="เช่น 081-234-5678"
                  />
                </span>
              </Field>
            </div>
          </section>
          <section
            className="settings-section settings-copy-section"
            aria-labelledby="shop-copy-title"
          >
            <div className="settings-section-head">
              <div>
                <h3 id="shop-copy-title">ข้อความสำหรับส่งลูกค้า</h3>
                <p>
                  กำหนดข้อความปิดท้ายสำหรับใช้ร่วมกับปุ่มคัดลอกรายละเอียดไอดี
                </p>
              </div>
              <span>03</span>
            </div>
            <Field label="ข้อความเพิ่มเติมท้ายรายละเอียดไอดี" full>
              <textarea
                className="resize-none settings-copy-textarea"
                name="inventory_copy_footer"
                defaultValue={copyFooter}
                maxLength={2000}
                rows={5}
                placeholder="เช่น สนใจสอบถามได้ทาง LINE รับประกันข้อมูลตรงตามรายละเอียด 7 วัน"
              />
              <small className="field-help">
                ระบบจะต่อข้อความนี้ท้ายรายละเอียดไอดีโดยอัตโนมัติทุกครั้งที่กดคัดลอก
              </small>
            </Field>
          </section>
        </div>
        <div className="settings-form-actions">
          <p>
            <strong>ตรวจสอบข้อมูลให้ครบก่อนบันทึก</strong>
            <span>การตั้งค่าจะมีผลกับร้านนี้ทันที</span>
          </p>
          <button className="button primary">
            <Save size={17} />
            บันทึกการตั้งค่า
          </button>
        </div>
      </form>
    </section>
  );
}
