import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { SiteFooter } from "../landing/SiteFooter";
import { SiteHeader } from "../landing/SiteHeader";
import { ContactChips } from "./ContactChips";
import {
  fetchItem,
  fetchShop,
  HttpError,
  priceLabel,
  type ShopItemDetail,
  type ShopProfile,
} from "./api";

type Status = "loading" | "not-found" | "error" | "ready";

export function StorefrontItemPage() {
  const { shopSlug = "", tag = "" } = useParams<{ shopSlug: string; tag: string }>();
  const [status, setStatus] = useState<Status>("loading");
  const [shop, setShop] = useState<ShopProfile | null>(null);
  const [item, setItem] = useState<ShopItemDetail | null>(null);
  const [active, setActive] = useState(0);

  useEffect(() => {
    window.scrollTo(0, 0);
    const controller = new AbortController();
    setStatus("loading");
    setActive(0);
    Promise.all([
      fetchShop(shopSlug, controller.signal),
      fetchItem(shopSlug, tag, controller.signal),
    ])
      .then(([profile, detail]) => {
        setShop(profile);
        setItem(detail);
        setStatus("ready");
        document.title = `${detail.tag} ${detail.title ?? ""} — ${profile.name}`;
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return;
        setStatus(error instanceof HttpError && error.status === 404 ? "not-found" : "error");
      });
    return () => controller.abort();
  }, [shopSlug, tag]);

  const images = item?.media.length
    ? item.media.map((media) => media.image_url)
    : item?.image
      ? [item.image]
      : [];

  return (
    <div className="site">
      <SiteHeader />
      <main className="storefront">
        {status === "loading" && (
          <div className="storefront-shell">
            <div className="storefront-skeleton-head" />
          </div>
        )}

        {(status === "not-found" || status === "error") && (
          <div className="storefront-shell storefront-empty">
            <h1>{status === "not-found" ? "ไม่พบไอดีนี้" : "โหลดข้อมูลไม่สำเร็จ"}</h1>
            <p>
              {status === "not-found"
                ? "ไอดีนี้อาจถูกขายไปแล้ว หรือลิงก์ไม่ถูกต้อง"
                : "กรุณาลองใหม่อีกครั้งในภายหลัง"}
            </p>
            <Link to={`/s/${shopSlug}`} className="storefront-back">
              <ArrowLeft size={16} /> กลับหน้าร้าน
            </Link>
          </div>
        )}

        {status === "ready" && shop && item && (
          <div className="storefront-shell">
            <Link to={`/s/${shop.slug}`} className="storefront-back">
              <ArrowLeft size={16} /> กลับหน้าร้าน {shop.name}
            </Link>

            <div className="storefront-detail">
              <div className="storefront-gallery">
                <div className="storefront-gallery-main">
                  {images[active] ? (
                    <img src={images[active]} alt={item.title ?? item.tag} decoding="async" />
                  ) : (
                    <span className="listing-noimg">ไม่มีรูป</span>
                  )}
                </div>
                {images.length > 1 && (
                  <div className="storefront-gallery-thumbs">
                    {images.map((src, index) => (
                      <button
                        type="button"
                        key={src}
                        className={index === active ? "is-active" : ""}
                        onClick={() => setActive(index)}
                        aria-label={`รูปที่ ${index + 1}`}
                      >
                        <img src={src} alt="" loading="lazy" decoding="async" />
                      </button>
                    ))}
                  </div>
                )}
              </div>

              <div className="storefront-detail-info">
                <span className="listing-badge">พร้อมขาย</span>
                <h1>{item.title || item.tag}</h1>
                <span className="storefront-detail-tag">{item.tag}</span>
                <dl className="storefront-spec">
                  {item.rank && (
                    <div>
                      <dt>แรงก์</dt>
                      <dd>{item.rank}</dd>
                    </div>
                  )}
                  {item.level != null && (
                    <div>
                      <dt>เลเวล</dt>
                      <dd>{item.level.toLocaleString("th-TH")}</dd>
                    </div>
                  )}
                  {item.skin_count != null && (
                    <div>
                      <dt>จำนวนสกิน</dt>
                      <dd>{item.skin_count.toLocaleString("th-TH")}</dd>
                    </div>
                  )}
                  {item.battlepass_level != null && (
                    <div>
                      <dt>Battle Pass</dt>
                      <dd>Lv.{item.battlepass_level}</dd>
                    </div>
                  )}
                </dl>
                <span className="storefront-detail-price">{priceLabel(item.list_price)}</span>
                {item.description && (
                  <p className="storefront-detail-desc">{item.description}</p>
                )}

                <div className="storefront-buy">
                  <strong>สนใจไอดีนี้ ติดต่อร้านได้เลย</strong>
                  <p>แจ้งรหัส {item.tag} กับร้านเพื่อสอบถามและสั่งซื้อ</p>
                  <ContactChips shop={shop} />
                </div>
                {shop.inventory_copy_footer && (
                  <p className="storefront-footer-note">{shop.inventory_copy_footer}</p>
                )}
              </div>
            </div>
          </div>
        )}
      </main>
      <SiteFooter />
    </div>
  );
}
