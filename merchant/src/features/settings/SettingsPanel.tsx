import { useEffect, useRef, useState } from "react";
import type { FormEvent } from "react";
import {
  Copy,
  ExternalLink,
  Image as ImageIcon,
  Link2,
  Phone,
  Save,
  Settings,
  Store,
  Trash2,
  Upload,
} from "lucide-react";
import { AsyncError } from "../../shared/ui/async-state";
import { Field } from "../../shared/ui/form-controls";
import { storefrontUrl } from "../../config/links";
import { writeClipboard } from "../../shared/lib/clipboard";
import type { Shop, ShopDetails } from "../../types/models";

type BrandingTarget = "logo" | "banner";

export function SettingsPanel({
  shop,
  loading,
  error,
  canUseStorefront,
  logoUrl,
  bannerUrl,
  onSubmit,
  onUploadBranding,
  onRemoveBranding,
  retry,
}: {
  shop: ShopDetails | Shop | null;
  loading: boolean;
  error: string;
  canUseStorefront: boolean;
  logoUrl: string | null;
  bannerUrl: string | null;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onUploadBranding: (target: BrandingTarget, file: File) => void;
  onRemoveBranding: (target: BrandingTarget) => void;
  retry: () => void;
}) {
  const [copied, setCopied] = useState(false);
  const logoInput = useRef<HTMLInputElement>(null);
  const bannerInput = useRef<HTMLInputElement>(null);
  // Deep-link support: /settings#branding etc. from the onboarding guide.
  useEffect(() => {
    if (loading) return;
    const id = window.location.hash.slice(1);
    if (!id) return;
    window.requestAnimationFrame(() => {
      document
        .getElementById(id)
        ?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }, [loading]);
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
  const storefrontOn =
    "storefront_enabled" in shop && Boolean(shop.storefront_enabled);
  const shopUrl = shop.slug ? storefrontUrl(shop.slug) : "";
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
        key={`${shop.id}-${shop.name}-${shop.slug ?? ""}-${copyFooter}-${storefrontOn}-${canUseStorefront}`}
        onSubmit={onSubmit}
        noValidate
      >
        <div className="settings-form-body">
          <section
            id="shop-info"
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
            id="contact"
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
            id="copy"
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
          <section
            id="storefront"
            className="settings-section"
            aria-labelledby="shop-storefront-title"
          >
            <div className="settings-section-head">
              <div>
                <h3 id="shop-storefront-title">หน้าร้านสาธารณะ</h3>
                <p>
                  เปิดหน้าร้านเพื่อให้ลูกค้าดูไอดีที่ "พร้อมขาย" และช่องทางติดต่อ
                  ของร้านได้จากลิงก์ ลูกค้าติดต่อซื้อผ่านช่องทางเหล่านั้นโดยตรง
                </p>
              </div>
              <span>04</span>
            </div>
            <label className="settings-storefront-toggle">
              <input
                type="checkbox"
                name="storefront_enabled"
                defaultChecked={storefrontOn && canUseStorefront}
                disabled={!canUseStorefront}
              />
              <span>เปิดหน้าร้านสาธารณะ</span>
            </label>
            {!canUseStorefront && (
              <p className="field-help settings-storefront-lock">
                หน้าร้านสาธารณะใช้ได้ตั้งแต่แพ็ก Starter ขึ้นไป — อัปเกรดได้ที่หน้า
                "แพ็กเกจ"
              </p>
            )}
            <div className="settings-storefront-link">
              <input
                type="text"
                readOnly
                value={shopUrl || "ตั้งค่า Slug ร้านในหัวข้อ 01 ก่อน"}
                aria-label="ลิงก์หน้าร้าน"
              />
              <button
                type="button"
                className="button"
                disabled={!shopUrl}
                onClick={() => {
                  void writeClipboard(shopUrl);
                  setCopied(true);
                  window.setTimeout(() => setCopied(false), 1600);
                }}
              >
                <Copy size={16} />
                {copied ? "คัดลอกแล้ว" : "คัดลอกลิงก์"}
              </button>
              <a
                className="button"
                href={shopUrl || undefined}
                target="_blank"
                rel="noopener noreferrer"
                aria-disabled={!shopUrl}
              >
                <ExternalLink size={16} />
                เปิดดูหน้าร้าน
              </a>
            </div>
            <small className="field-help">
              <Store size={13} /> เปลี่ยน Slug แล้วอย่าลืมกดบันทึกก่อน ลิงก์จึงจะ
              อัปเดต
            </small>
          </section>
          <section
            id="branding"
            className="settings-section"
            aria-labelledby="shop-branding-title"
          >
            <div className="settings-section-head">
              <div>
                <h3 id="shop-branding-title">แบรนด์ร้าน</h3>
                <p>
                  โลโก้แสดงในหน้ารวมทุกร้านและหัวหน้าร้านของคุณ · แบนเนอร์แสดงเป็น
                  ภาพปกด้านบนหน้าร้าน (บันทึกทันทีเมื่อเลือกไฟล์)
                </p>
              </div>
              <span>05</span>
            </div>
            <div className="settings-branding">
              <div className="settings-branding-row">
                <div className="settings-branding-preview settings-branding-logo">
                  {logoUrl ? (
                    <img src={logoUrl} alt="โลโก้ร้าน" />
                  ) : (
                    <ImageIcon size={22} aria-hidden="true" />
                  )}
                </div>
                <div className="settings-branding-actions">
                  <strong>โลโก้</strong>
                  <small className="field-help">
                    รูปสี่เหลี่ยมจัตุรัส PNG/JPG/WebP ไม่เกิน 2 MB
                  </small>
                  <div>
                    <button
                      type="button"
                      className="button"
                      onClick={() => logoInput.current?.click()}
                    >
                      <Upload size={15} /> อัปโหลด
                    </button>
                    {logoUrl && (
                      <button
                        type="button"
                        className="button ghost"
                        onClick={() => onRemoveBranding("logo")}
                      >
                        <Trash2 size={15} /> เอาออก
                      </button>
                    )}
                  </div>
                </div>
                <input
                  ref={logoInput}
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  hidden
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) onUploadBranding("logo", file);
                    event.target.value = "";
                  }}
                />
              </div>
              <div className="settings-branding-row">
                <div className="settings-branding-preview settings-branding-banner">
                  {bannerUrl ? (
                    <img src={bannerUrl} alt="แบนเนอร์ร้าน" />
                  ) : (
                    <ImageIcon size={22} aria-hidden="true" />
                  )}
                </div>
                <div className="settings-branding-actions">
                  <strong>แบนเนอร์</strong>
                  <small className="field-help">
                    แนะนำอัตราส่วน 4:1 PNG/JPG/WebP ไม่เกิน 4 MB
                  </small>
                  <div>
                    <button
                      type="button"
                      className="button"
                      onClick={() => bannerInput.current?.click()}
                    >
                      <Upload size={15} /> อัปโหลด
                    </button>
                    {bannerUrl && (
                      <button
                        type="button"
                        className="button ghost"
                        onClick={() => onRemoveBranding("banner")}
                      >
                        <Trash2 size={15} /> เอาออก
                      </button>
                    )}
                  </div>
                </div>
                <input
                  ref={bannerInput}
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  hidden
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) onUploadBranding("banner", file);
                    event.target.value = "";
                  }}
                />
              </div>
            </div>
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
