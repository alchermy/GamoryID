import { useEffect, useState } from "react";
import { SiteFooter } from "../landing/SiteFooter";
import { SiteHeader } from "../landing/SiteHeader";
import { applyPageMeta } from "../../shared/head";
import { ListingCard } from "./ListingCard";
import {
  fetchListings,
  type BrowseListing,
  type ListingSort,
} from "./api";

const SORTS: { key: ListingSort; label: string }[] = [
  { key: "newest", label: "ใหม่ล่าสุด" },
  { key: "price_asc", label: "ราคาต่ำ-สูง" },
  { key: "price_desc", label: "ราคาสูง-ต่ำ" },
  { key: "popular", label: "ความนิยม" },
];

export function BrowsePage() {
  const [sort, setSort] = useState<ListingSort>("newest");
  const [items, setItems] = useState<BrowseListing[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [status, setStatus] = useState<"loading" | "error" | "ready">("loading");
  const [loadingMore, setLoadingMore] = useState(false);

  useEffect(() => {
    window.scrollTo(0, 0);
    applyPageMeta({
      title: "ไอดีทั้งหมด — GamoryID",
      description:
        "รวมไอดีเกมพร้อมขายจากทุกร้านบน GamoryID เลือกดูตามราคาหรือความนิยม แล้วติดต่อร้านได้โดยตรง",
      canonical: `${window.location.origin}/browse`,
    });
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    setStatus("loading");
    fetchListings(sort, 1, controller.signal)
      .then((result) => {
        setItems(result.data);
        setPage(result.meta.current_page);
        setLastPage(result.meta.last_page);
        setStatus("ready");
      })
      .catch((error: unknown) => {
        if (!controller.signal.aborted) setStatus("error");
        void error;
      });
    return () => controller.abort();
  }, [sort]);

  const loadMore = () => {
    const controller = new AbortController();
    setLoadingMore(true);
    fetchListings(sort, page + 1, controller.signal)
      .then((result) => {
        setItems((current) => [...current, ...result.data]);
        setPage(result.meta.current_page);
        setLastPage(result.meta.last_page);
      })
      .catch(() => undefined)
      .finally(() => setLoadingMore(false));
  };

  return (
    <div className="site">
      <SiteHeader />
      <main className="storefront">
        <div className="storefront-shell">
          <header className="storefront-head">
            <span className="storefront-eyebrow">รวมทุกร้าน</span>
            <h1>ไอดีทั้งหมด</h1>
            <p className="storefront-disclaim">
              GamoryID เป็นเครื่องมือจัดการร้าน ไม่ได้เป็นตัวกลางการซื้อขาย
              รายการทั้งหมดเป็นของร้านค้าที่ลงเอง โปรดตรวจสอบร้านและสินค้าก่อนโอนเงิน
            </p>
            <div className="browse-sort" role="tablist" aria-label="เรียงลำดับ">
              {SORTS.map((option) => (
                <button
                  key={option.key}
                  type="button"
                  role="tab"
                  aria-selected={sort === option.key}
                  className={sort === option.key ? "is-on" : ""}
                  onClick={() => setSort(option.key)}
                >
                  {option.label}
                </button>
              ))}
            </div>
          </header>

          {status === "loading" && (
            <div className="shop-grid">
              {Array.from({ length: 8 }).map((_, index) => (
                <div className="storefront-skeleton-card" key={index} />
              ))}
            </div>
          )}

          {status === "error" && (
            <div className="storefront-empty">
              <p>โหลดรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง</p>
            </div>
          )}

          {status === "ready" &&
            (items.length === 0 ? (
              <div className="storefront-empty">
                <p>ยังไม่มีไอดีที่พร้อมขายในตอนนี้</p>
              </div>
            ) : (
              <>
                <div className="shop-grid">
                  {items.map((item) => (
                    <ListingCard
                      key={`${item.shop.slug}-${item.tag}`}
                      item={item}
                      shop={item.shop}
                      to={`/s/${item.shop.slug}/${encodeURIComponent(item.tag.replace(/^#/, ""))}`}
                    />
                  ))}
                </div>
                {page < lastPage && (
                  <div className="storefront-more">
                    <button
                      type="button"
                      className="btn blue"
                      onClick={loadMore}
                      disabled={loadingMore}
                    >
                      {loadingMore ? "กำลังโหลด…" : "โหลดเพิ่ม"}
                    </button>
                  </div>
                )}
              </>
            ))}
        </div>
      </main>
      <SiteFooter />
    </div>
  );
}
