import { Link } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import { priceLabel, type ShopListing } from "./api";

export function ListingCard({
  item,
  to,
  shop,
}: {
  item: ShopListing;
  to: string;
  shop?: { name: string | null; slug: string | null; logo_url?: string | null };
}) {
  const meta =
    [
      item.rank,
      item.level != null ? `Lv.${item.level}` : null,
      item.skin_count != null ? `${item.skin_count} สกิน` : null,
    ]
      .filter(Boolean)
      .join(" · ") || "ดูรายละเอียด";

  return (
    <article className="listing-card">
      <Link to={to} className="listing-card-main">
        <div className="listing-thumb">
          {item.image ? (
            <img
              src={item.image}
              alt={item.title ?? item.tag}
              loading="lazy"
              decoding="async"
            />
          ) : (
            <span className="listing-noimg">ไม่มีรูป</span>
          )}
          <span className="listing-badge">พร้อมขาย</span>
        </div>
        <div className="listing-body">
          <span className="listing-tag">{item.tag}</span>
          <p className="listing-meta">{meta}</p>
          <span className="listing-price">{priceLabel(item.list_price)}</span>
          <span className="listing-cta">
            ดูรายละเอียด <ArrowRight size={15} aria-hidden="true" />
          </span>
        </div>
      </Link>
      {shop?.slug && (
        <Link to={`/s/${shop.slug}`} className="listing-shop">
          {shop.logo_url ? (
            <img
              className="listing-shop-logo"
              src={shop.logo_url}
              alt=""
              loading="lazy"
            />
          ) : null}
          <span>โดย {shop.name ?? shop.slug}</span>
        </Link>
      )}
    </article>
  );
}
