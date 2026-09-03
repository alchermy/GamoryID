import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { SiteFooter } from "../landing/SiteFooter";
import { SiteHeader } from "../landing/SiteHeader";
import { ContactChips } from "./ContactChips";
import { ListingCard } from "./ListingCard";
import {
  fetchInventory,
  fetchShop,
  HttpError,
  type ShopListing,
  type ShopProfile,
} from "./api";

type Status = "loading" | "not-found" | "error" | "ready";

export function StorefrontPage() {
  const { shopSlug = "" } = useParams<{ shopSlug: string }>();
  const [status, setStatus] = useState<Status>("loading");
  const [shop, setShop] = useState<ShopProfile | null>(null);
  const [items, setItems] = useState<ShopListing[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loadingMore, setLoadingMore] = useState(false);

  useEffect(() => {
    window.scrollTo(0, 0);
    const controller = new AbortController();
    setStatus("loading");
    Promise.all([
      fetchShop(shopSlug, controller.signal),
      fetchInventory(shopSlug, 1, controller.signal),
    ])
      .then(([profile, inventory]) => {
        setShop(profile);
        setItems(inventory.data);
        setPage(inventory.meta.current_page);
        setLastPage(inventory.meta.last_page);
        setStatus("ready");
        document.title = `${profile.name} — ร้านค้าบน GamoryID`;
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return;
        setStatus(error instanceof HttpError && error.status === 404 ? "not-found" : "error");
      });
    return () => controller.abort();
  }, [shopSlug]);

  const loadMore = () => {
    const controller = new AbortController();
    setLoadingMore(true);
    fetchInventory(shopSlug, page + 1, controller.signal)
      .then((inventory) => {
        setItems((current) => [...current, ...inventory.data]);
        setPage(inventory.meta.current_page);
        setLastPage(inventory.meta.last_page);
      })
      .catch(() => undefined)
      .finally(() => setLoadingMore(false));
  };

  return (
    <div className="site">
      <SiteHeader />
      <main className="storefront">
        {status === "loading" && (
          <div className="storefront-shell">
            <div className="storefront-skeleton-head" />
            <div className="shop-grid">
              {Array.from({ length: 6 }).map((_, index) => (
                <div className="storefront-skeleton-card" key={index} />
              ))}
            </div>
          </div>
        )}

        {(status === "not-found" || status === "error") && (
          <div className="storefront-shell storefront-empty">
            <h1>{status === "not-found" ? "ไม่พบร้านนี้" : "โหลดหน้าร้านไม่สำเร็จ"}</h1>
            <p>
              {status === "not-found"
                ? "ลิงก์อาจไม่ถูกต้อง หรือร้านยังไม่ได้เปิดหน้าร้านสาธารณะ"
                : "กรุณาลองใหม่อีกครั้งในภายหลัง"}
            </p>
            <Link to="/" className="storefront-back">
              <ArrowLeft size={16} /> กลับหน้าแรก GamoryID
            </Link>
          </div>
        )}

        {status === "ready" && shop && (
          <div className="storefront-shell">
            {shop.banner_url && (
              <div className="storefront-banner">
                <img src={shop.banner_url} alt={`แบนเนอร์ร้าน ${shop.name}`} />
              </div>
            )}
            <header
              className={`storefront-head${shop.logo_url ? " has-logo" : ""}`}
            >
              {shop.logo_url && (
                <img
                  className="storefront-logo"
                  src={shop.logo_url}
                  alt={`โลโก้ร้าน ${shop.name}`}
                />
              )}
              <div className="storefront-head-text">
                <span className="storefront-eyebrow">ร้านค้าบน GamoryID</span>
                <h1>{shop.name}</h1>
                {shop.description && (
                  <p className="storefront-desc">{shop.description}</p>
                )}
                <ContactChips shop={shop} />
              </div>
            </header>

            {items.length === 0 ? (
              <div className="storefront-empty">
                <p>ตอนนี้ร้านยังไม่มีไอดีที่พร้อมขาย</p>
              </div>
            ) : (
              <>
                <div className="shop-grid">
                  {items.map((item) => (
                    <ListingCard
                      key={item.tag}
                      item={item}
                      to={`/s/${shop.slug}/${encodeURIComponent(item.tag.replace(/^#/, ""))}`}
                    />
                  ))}
                </div>
                {page < lastPage && (
                  <div className="storefront-more">
                    <button type="button" className="btn blue" onClick={loadMore} disabled={loadingMore}>
                      {loadingMore ? "กำลังโหลด…" : "โหลดเพิ่ม"}
                    </button>
                  </div>
                )}
              </>
            )}

            {shop.inventory_copy_footer && (
              <p className="storefront-footer-note">{shop.inventory_copy_footer}</p>
            )}
          </div>
        )}
      </main>
      <SiteFooter />
    </div>
  );
}
