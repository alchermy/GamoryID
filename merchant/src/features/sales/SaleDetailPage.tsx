import { useEffect, useState } from "react";
import type { ReactNode } from "react";
import { Link } from "react-router-dom";
import {
  ArrowLeft,
  BadgeCheck,
  CalendarClock,
  ExternalLink,
  MessageSquareText,
  ReceiptText,
  ShieldCheck,
  ShoppingBag,
  UserRound,
} from "lucide-react";
import { formatDate, money } from "../../shared/lib/format";
import { AsyncError } from "../../shared/ui/async-state";
import type { SaleRecord } from "../../types/models";
import { loadSaleDetail } from "./sales-api";

const dateOnly = new Intl.DateTimeFormat("th-TH", {
  dateStyle: "long",
});

function DetailValue({ label, value }: { label: string; value: ReactNode }) {
  const displayedValue =
    value === null || value === undefined || value === "" ? "–" : value;

  return (
    <div className="sale-detail-value">
      <span>{label}</span>
      <strong>{displayedValue}</strong>
    </div>
  );
}

export function SaleDetailPage({
  shopId,
  saleId,
  canViewProfit,
}: {
  shopId: number;
  saleId: number;
  canViewProfit: boolean;
}) {
  const [record, setRecord] = useState<SaleRecord | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    void loadSaleDetail(shopId, saleId, controller.signal)
      .then(setRecord)
      .catch((reason: unknown) => {
        if (reason instanceof Error && reason.name === "AbortError") return;
        setError(
          reason instanceof Error
            ? reason.message
            : "ไม่สามารถโหลดรายละเอียดการขายได้",
        );
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [revision, saleId, shopId]);

  const retry = () => {
    setRecord(null);
    setError("");
    setLoading(true);
    setRevision((value) => value + 1);
  };

  if (loading) {
    return (
      <section className="panel sale-detail-state" aria-live="polite">
        กำลังโหลดรายละเอียดการขาย…
      </section>
    );
  }

  if (error || !record) {
    return (
      <div className="sale-detail">
        <Link className="button ghost detail-back" to="/sales">
          <ArrowLeft size={17} /> กลับรายการขาย
        </Link>
        <section className="panel sale-detail-state">
          <AsyncError error={error || "ไม่พบรายการขายนี้"} retry={retry} />
        </section>
      </div>
    );
  }

  const inventory = record.inventory_item;
  const customer = record.customer;
  const facebookUrl = customer?.facebook_url?.match(/^https?:\/\//i)
    ? customer.facebook_url
    : null;

  return (
    <article className="sale-detail" aria-labelledby="sale-detail-title">
      <Link className="button ghost detail-back" to="/sales">
        <ArrowLeft size={17} /> กลับรายการขาย
      </Link>

      <header className="sale-detail-header">
        <div>
          <div className="sale-detail-kicker">
            <span className="status sold">
              <BadgeCheck size={14} /> ขายแล้ว
            </span>
            <span>รายการขาย #{record.id}</span>
          </div>
          <h2 id="sale-detail-title">
            {inventory ? `#${inventory.tag}` : `รายการขาย #${record.id}`}
          </h2>
          <p>
            {inventory?.riot_id || inventory?.title || "ข้อมูลไอดีถูกลบ"} ·
            ขายเมื่อ {formatDate(record.sold_at)}
          </p>
        </div>
        {inventory ? (
          <Link className="button blue" to={`/inventory?item=${inventory.id}`}>
            <ShoppingBag size={17} /> ดูข้อมูลไอดี
          </Link>
        ) : null}
      </header>

      <div className="sale-detail-layout">
        <div className="sale-detail-main">
          <section
            className="panel sale-detail-card"
            aria-labelledby="sale-item-title"
          >
            <div className="sale-detail-section-head">
              <span className="sale-detail-section-icon" aria-hidden="true">
                <ReceiptText size={19} />
              </span>
              <div>
                <span className="eyebrow">ข้อมูลรายการ</span>
                <h3 id="sale-item-title">ไอดีที่ขาย</h3>
              </div>
            </div>
            <div className="sale-detail-values">
              <DetailValue
                label="แท็ก"
                value={inventory ? `#${inventory.tag}` : "–"}
              />
              <DetailValue
                label="Riot ID"
                value={inventory?.riot_id || inventory?.title}
              />
              <DetailValue label="แรงก์" value={inventory?.rank} />
              <DetailValue
                label="เลเวล"
                value={inventory?.level?.toLocaleString("th-TH")}
              />
            </div>
          </section>

          <section
            className="panel sale-detail-card"
            aria-labelledby="sale-customer-title"
          >
            <div className="sale-detail-section-head">
              <span className="sale-detail-section-icon" aria-hidden="true">
                <UserRound size={19} />
              </span>
              <div>
                <span className="eyebrow">ผู้ซื้อ</span>
                <h3 id="sale-customer-title">ข้อมูลลูกค้า</h3>
              </div>
            </div>
            <div className="sale-customer-name">
              <strong>{customer?.name || "ไม่ระบุชื่อลูกค้า"}</strong>
              <span>ข้อมูลติดต่อที่บันทึกไว้กับรายการขายนี้</span>
            </div>
            <div className="sale-contact-list">
              <DetailValue label="LINE" value={customer?.line_id} />
              <DetailValue
                label="เบอร์โทร"
                value={
                  customer?.phone ? (
                    <a href={`tel:${customer.phone}`}>{customer.phone}</a>
                  ) : null
                }
              />
              <DetailValue
                label="Facebook"
                value={
                  facebookUrl ? (
                    <a href={facebookUrl} target="_blank" rel="noreferrer">
                      เปิดโปรไฟล์ <ExternalLink size={14} />
                    </a>
                  ) : (
                    customer?.facebook_url
                  )
                }
              />
            </div>
          </section>

          {record.notes ? (
            <section
              className="panel sale-detail-card"
              aria-labelledby="sale-note-title"
            >
              <div className="sale-detail-section-head">
                <span className="sale-detail-section-icon" aria-hidden="true">
                  <MessageSquareText size={19} />
                </span>
                <div>
                  <span className="eyebrow">บันทึกภายใน</span>
                  <h3 id="sale-note-title">รายละเอียดเพิ่มเติม</h3>
                </div>
              </div>
              <p className="sale-detail-note">{record.notes}</p>
            </section>
          ) : null}
        </div>

        <aside
          className="panel sale-detail-summary"
          aria-labelledby="sale-summary-title"
        >
          <div className="sale-detail-section-head">
            <span className="sale-detail-section-icon" aria-hidden="true">
              <ReceiptText size={19} />
            </span>
            <div>
              <span className="eyebrow">สรุปการขาย</span>
              <h3 id="sale-summary-title">ยอดและผู้ทำรายการ</h3>
            </div>
          </div>
          <div className="sale-price-block">
            <span>ราคาขายสุทธิ</span>
            <strong>{money.format(Number(record.sold_price))}</strong>
          </div>
          {canViewProfit ? (
            <div className="sale-profit-grid">
              <DetailValue
                label="ต้นทุน"
                value={money.format(Number(record.cost_snapshot ?? 0))}
              />
              <DetailValue
                label="กำไร"
                value={money.format(Number(record.profit ?? 0))}
              />
            </div>
          ) : null}
          <div className="sale-summary-list">
            <div>
              <UserRound size={17} />
              <span>ผู้ขาย</span>
              <strong>{record.creator?.name || "ไม่ระบุ"}</strong>
            </div>
            <div>
              <CalendarClock size={17} />
              <span>วันเวลาขาย</span>
              <strong>{formatDate(record.sold_at)}</strong>
            </div>
            <div>
              <ShieldCheck size={17} />
              <span>การรับประกัน</span>
              <strong>
                {record.has_warranty
                  ? record.warranty_ends_at
                    ? `ถึง ${dateOnly.format(new Date(record.warranty_ends_at))}`
                    : "มีประกัน"
                  : "ไม่มีประกัน"}
              </strong>
            </div>
          </div>
        </aside>
      </div>
    </article>
  );
}
