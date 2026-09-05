import { useState } from "react";
import type { FormEvent } from "react";
import {
  Archive,
  ArrowLeft,
  CalendarDays,
  Check,
  ChevronRight,
  Clock3,
  Copy,
  Eye,
  Link2,
  MessageSquareText,
  PackagePlus,
  Pencil,
  Phone,
  ReceiptText,
  Save,
  ShieldCheck,
  ShoppingBag,
  Tag,
  UserRound,
  X,
} from "lucide-react";
import { money, statusLabel } from "../../shared/lib/format";
import {
  DialogHead,
  Field,
  PasswordInput,
} from "../../shared/ui/form-controls";
import type {
  InventoryItem,
  InventoryStatus,
  SalePayload,
} from "../../types/models";
import {
  InventoryMediaFields,
  InventoryMediaGallery,
} from "./InventoryMediaFields";
import {
  createEmptyMediaDraft,
  type InventoryMediaDraft,
} from "./inventory-media-model";

// Valorant's fixed competitive ladder — merchants pick, they don't type.
const VALORANT_RANKS = [
  "Iron 1",
  "Iron 2",
  "Iron 3",
  "Bronze 1",
  "Bronze 2",
  "Bronze 3",
  "Silver 1",
  "Silver 2",
  "Silver 3",
  "Gold 1",
  "Gold 2",
  "Gold 3",
  "Platinum 1",
  "Platinum 2",
  "Platinum 3",
  "Diamond 1",
  "Diamond 2",
  "Diamond 3",
  "Ascendant 1",
  "Ascendant 2",
  "Ascendant 3",
  "Immortal 1",
  "Immortal 2",
  "Immortal 3",
  "Radiant",
] as const;

function RankSelect({ defaultValue }: { defaultValue?: string }) {
  const current = (defaultValue ?? "").trim();
  const isKnown = (VALORANT_RANKS as readonly string[]).includes(current);
  return (
    <select name="rank" defaultValue={current}>
      <option value="">ไม่ระบุ</option>
      {VALORANT_RANKS.map((rank) => (
        <option key={rank} value={rank}>
          {rank}
        </option>
      ))}
      {current !== "" && !isKnown && (
        <option value={current}>{current} (เดิม)</option>
      )}
    </select>
  );
}

function InventoryStatusControl({
  item,
  canSell,
  busy,
  onChange,
}: {
  item: InventoryItem;
  canSell: boolean;
  busy: boolean;
  onChange: (item: InventoryItem, status: InventoryStatus) => void;
}) {
  const locked = item.status === "sold" || item.status === "archived";
  return (
    <span
      className={`status-picker ${item.status} ${locked ? "is-locked" : ""}`}
    >
      <span className="status-dot" aria-hidden="true" />
      <select
        aria-label={`เปลี่ยนสถานะ ${item.tag}`}
        value={item.status}
        disabled={!canSell || busy || locked}
        onChange={(event) =>
          onChange(item, event.target.value as InventoryStatus)
        }
      >
        <option value="available">พร้อมขาย</option>
        <option value="reserved">ถูกจอง</option>
        <option value="sold">ขายแล้ว</option>
        {item.status === "archived" && (
          <option value="archived">เก็บถาวร</option>
        )}
      </select>
    </span>
  );
}

export function InventoryPanel({
  items,
  query,
  status,
  setInventoryStatus,
  canSell,
  canNote,
  canViewAnalytics,
  busy,
  onStatusChange,
  onSelect,
  onReserve,
  onSell,
  onCopyTag,
  onCopyDetails,
  onNote,
}: {
  items: InventoryItem[];
  query: string;
  status: "all" | InventoryStatus;
  setInventoryStatus: (v: "all" | InventoryStatus) => void;
  canSell: boolean;
  canNote: boolean;
  canViewAnalytics: boolean;
  busy: boolean;
  onStatusChange: (item: InventoryItem, status: InventoryStatus) => void;
  onSelect: (v: InventoryItem) => void;
  onReserve: (v: InventoryItem) => void;
  onSell: (v: InventoryItem) => void;
  onCopyTag: (v: InventoryItem) => void;
  onCopyDetails: (v: InventoryItem) => void;
  onNote: (v: InventoryItem) => void;
}) {
  return (
    <section className="panel inventory-list-panel">
      <div className="panel-head">
        <div>
          <h2>{query ? `ผลการค้นหา “${query}”` : "ไอดีล่าสุด"}</h2>
          <small>{items.length} รายการที่แสดง</small>
        </div>
        <div className="filters">
          <select
            aria-label="กรองสถานะ"
            value={status}
            onChange={(e) =>
              setInventoryStatus(e.target.value as "all" | InventoryStatus)
            }
          >
            <option value="all">ทุกสถานะ</option>
            <option value="available">พร้อมขาย</option>
            <option value="reserved">ถูกจอง</option>
            <option value="sold">ขายแล้ว</option>
            <option value="archived">เก็บถาวร</option>
          </select>
        </div>
      </div>
      {items.length === 0 ? (
        <div className="empty">
          <img src="/mascot/gammy-search.png" alt="Gammy กำลังค้นหา" />
          <strong>ยังไม่พบไอดีที่ตรงกัน</strong>
          <p>ลองค้นด้วยแท็ก 5 ตัว, Username หรือ Riot ID</p>
        </div>
      ) : (
        <>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>แท็ก</th>
                  <th>Username</th>
                  <th>Riot ID</th>
                  <th>แรงก์</th>
                  <th>ราคาขาย</th>
                  <th>สถานะ</th>
                  {canViewAnalytics && <th>เข้าชม</th>}
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {items.map((i) => (
                  <tr key={i.id}>
                    <td>
                      <button
                        className="button ghost tag"
                        aria-label={`คัดลอกแท็ก ${i.tag}`}
                        onClick={() => onCopyTag(i)}
                      >
                        {i.tag}
                      </button>
                    </td>
                    <td className="title-cell">{i.username}</td>
                    <td className="title-cell">
                      <span>{i.riotId}</span>
                      {i.notes && canNote && (
                        <button
                          type="button"
                          className="inventory-note-preview"
                          title={i.notes}
                          aria-label={`เปิดโน้ตช่วยจำ ${i.tag}: ${i.notes}`}
                          onClick={() => onNote(i)}
                        >
                          <MessageSquareText size={13} aria-hidden="true" />
                          <span>{i.notes}</span>
                        </button>
                      )}
                      {i.notes && !canNote && (
                        <span
                          className="inventory-note-preview is-readonly"
                          title={i.notes}
                        >
                          <MessageSquareText size={13} aria-hidden="true" />
                          <span>{i.notes}</span>
                        </span>
                      )}
                    </td>
                    <td>{i.rank}</td>
                    <td>
                      <strong>{money.format(i.price)}</strong>
                    </td>
                    <td>
                      <InventoryStatusControl
                        item={i}
                        canSell={canSell}
                        busy={busy}
                        onChange={onStatusChange}
                      />
                    </td>
                    {canViewAnalytics && (
                      <td className="inventory-views-cell">
                        <Eye size={14} aria-hidden="true" />
                        {(i.viewCount ?? 0).toLocaleString("th-TH")}
                      </td>
                    )}
                    <td>
                      <div className="row-actions">
                        {canNote && (
                          <button
                            className={`icon-button note-action ${i.notes ? "has-note" : ""}`}
                            aria-label={`${i.notes ? "แก้ไข" : "เพิ่ม"}โน้ตช่วยจำ ${i.tag}`}
                            title={
                              i.notes ? "แก้ไขโน้ตช่วยจำ" : "เพิ่มโน้ตช่วยจำ"
                            }
                            onClick={() => onNote(i)}
                          >
                            <MessageSquareText size={16} />
                          </button>
                        )}
                        <button
                          className="icon-button"
                          aria-label={`ดูรายละเอียด ${i.tag}`}
                          title="ดูรายละเอียด"
                          onClick={() => onSelect(i)}
                        >
                          <Eye size={16} />
                        </button>
                        <button
                          className="icon-button copy-action"
                          aria-label={`คัดลอกรายละเอียด ${i.tag}`}
                          title="คัดลอกรายละเอียด"
                          onClick={() => void onCopyDetails(i)}
                        >
                          <Copy size={16} />
                        </button>
                        {canSell && i.status === "available" && (
                          <button
                            className="icon-button"
                            aria-label={`จอง ${i.tag}`}
                            title="จอง"
                            onClick={() => onReserve(i)}
                          >
                            <Clock3 size={16} />
                          </button>
                        )}
                        {canSell &&
                          ["available", "reserved"].includes(i.status) && (
                            <button
                              className="icon-button sell-action"
                              aria-label={`ปิดการขาย ${i.tag}`}
                              title="ปิดการขาย"
                              onClick={() => onSell(i)}
                            >
                              <Tag size={16} />
                            </button>
                          )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="mobile-records">
            {items.map((i) => (
              <article className="mobile-record" key={i.id}>
                <div className="mobile-record-top">
                  <button
                    className="button ghost tag"
                    aria-label={`คัดลอกแท็ก ${i.tag}`}
                    onClick={() => onCopyTag(i)}
                  >
                    {i.tag}
                  </button>
                  <InventoryStatusControl
                    item={i}
                    canSell={canSell}
                    busy={busy}
                    onChange={onStatusChange}
                  />
                </div>
                <button
                  className="mobile-record-link"
                  onClick={() => onSelect(i)}
                >
                  <h3>{i.riotId}</h3>
                  <div className="mobile-meta">
                    <span>{i.username}</span>
                    <span>{i.rank}</span>
                  </div>
                  <div className="mobile-record-bottom">
                    <span className="mobile-price">
                      {money.format(i.price)}
                    </span>
                    {canViewAnalytics && (
                      <span className="mobile-views">
                        <Eye size={14} aria-hidden="true" />
                        {(i.viewCount ?? 0).toLocaleString("th-TH")}
                      </span>
                    )}
                    <span>
                      ดูรายละเอียด <ChevronRight size={18} />
                    </span>
                  </div>
                </button>
                {i.notes && canNote && (
                  <button
                    type="button"
                    className="mobile-inventory-note"
                    onClick={() => onNote(i)}
                    aria-label={`เปิดโน้ตช่วยจำ ${i.tag}: ${i.notes}`}
                  >
                    <MessageSquareText size={15} aria-hidden="true" />
                    <span>{i.notes}</span>
                  </button>
                )}
                {i.notes && !canNote && (
                  <div className="mobile-inventory-note is-readonly">
                    <MessageSquareText size={15} aria-hidden="true" />
                    <span>{i.notes}</span>
                  </div>
                )}
                <div className="mobile-row-actions">
                  {canNote && (
                    <button className="button" onClick={() => onNote(i)}>
                      <MessageSquareText size={16} />
                      {i.notes ? "แก้ไขโน้ต" : "เพิ่มโน้ต"}
                    </button>
                  )}
                  <button
                    className="button"
                    onClick={() => void onCopyDetails(i)}
                  >
                    <Copy size={16} />
                    คัดลอกรายละเอียด
                  </button>
                  {canSell && ["available", "reserved"].includes(i.status) && (
                    <button className="button blue" onClick={() => onSell(i)}>
                      <Tag size={16} />
                      ปิดการขาย
                    </button>
                  )}
                </div>
              </article>
            ))}
          </div>
        </>
      )}
      <div className="pagination">
        <span>
          แสดง 1–{items.length} จาก {items.length} รายการ
        </span>
        <span>หน้า 1 / 1</span>
      </div>
    </section>
  );
}

export function InventoryNoteDialog({
  item,
  busy,
  close,
  submit,
}: {
  item: InventoryItem;
  busy: boolean;
  close: () => void;
  submit: (notes: string) => Promise<string | null>;
}) {
  const [notes, setNotes] = useState(item.notes ?? ""),
    [error, setError] = useState("");

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy) return;
    setError("");
    const result = await submit(notes.trim());
    if (result) setError(result);
  };

  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) close();
      }}
    >
      <form
        className="dialog inventory-note-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="inventory-note-title"
        onSubmit={handleSubmit}
        noValidate
      >
        <DialogHead
          id="inventory-note-title"
          title={`โน้ตช่วยจำ ${item.tag}`}
          subtitle="แปะสถานะการคุย ชื่อลูกค้า หรือสิ่งที่ทีมต้องติดตามไว้กับไอดีนี้"
          close={close}
        />
        <div className="dialog-body inventory-note-dialog-body">
          {error && (
            <div className="auth-error" role="alert">
              {error}
            </div>
          )}
          <Field label="โน้ตภายในร้าน">
            <textarea
              className="resize-none inventory-note-input"
              value={notes}
              maxLength={5000}
              autoFocus
              data-dialog-initial-focus
              placeholder="เช่น คุณเอกจองถึง 18:00 น. · คุยแล้ว รอตัดสินใจ · รอขายให้ลูกค้า LINE @example"
              onChange={(event) => setNotes(event.target.value)}
            />
          </Field>
          <div className="inventory-note-help">
            <span>ใช้ภายในร้านเท่านั้น ไม่รวมในข้อความที่คัดลอกส่งลูกค้า</span>
            <span>{notes.length.toLocaleString("th-TH")}/5,000</span>
          </div>
        </div>
        <div className="dialog-actions inventory-note-actions">
          <div>
            {item.notes && (
              <button
                type="button"
                className="button ghost"
                disabled={busy || notes.length === 0}
                onClick={() => setNotes("")}
              >
                ล้างโน้ต
              </button>
            )}
          </div>
          <div>
            <button
              type="button"
              className="button"
              onClick={close}
              disabled={busy}
            >
              ยกเลิก
            </button>
            <button className="button blue" disabled={busy}>
              <Save size={17} />
              {busy ? "กำลังบันทึก…" : "บันทึกโน้ต"}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
}

export function AddDialog({
  close,
  submit,
  busy,
}: {
  close: () => void;
  submit: (e: FormEvent<HTMLFormElement>, media: InventoryMediaDraft) => void;
  busy: boolean;
}) {
  const [media, setMedia] = useState(createEmptyMediaDraft);

  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget && !busy) close();
      }}
      onKeyDown={(e) => {
        if (e.key === "Escape" && !busy) close();
      }}
    >
      <form
        className="dialog inventory-form-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-title"
        onSubmit={(event) => submit(event, media)}
        noValidate
      >
        <DialogHead
          id="add-title"
          title="เพิ่มไอดีใหม่"
          subtitle="ระบบจะสร้างแท็ก 5 ตัวให้อัตโนมัติหลังบันทึก"
          close={close}
        />
        <div className="dialog-body">
          <div className="form-grid">
            <Field label="Riot ID" full>
              <input
                name="riot_id"
                required
                autoFocus
                placeholder="เช่น Gammy#TH01"
              />
            </Field>
            <Field label="Username">
              <input name="username" required autoComplete="username" />
            </Field>
            <Field label="Email">
              <input name="email" type="email" autoComplete="email" />
            </Field>
            <Field label="Password">
              <PasswordInput
                name="password"
                required
                autoComplete="new-password"
              />
            </Field>
            <Field label="รายละเอียดไอดี" full>
              <textarea
                className="resize-none"
                name="description"
                placeholder="ระบุสกิน, battlepass หรือข้อมูลประกอบการขาย"
              />
            </Field>
            <Field label="แรงก์">
              <RankSelect />
            </Field>
            <Field label="เลเวล">
              <input name="level" type="number" min="0" defaultValue="1" />
            </Field>
            <Field label="ต้นทุน">
              <input name="cost" type="number" min="0" required />
            </Field>
            <Field label="ราคาตั้งขาย">
              <input name="price" type="number" min="0" required />
            </Field>
          </div>
          <InventoryMediaFields value={media} onChange={setMedia} />
        </div>
        <div className="dialog-actions">
          <button
            type="button"
            className="button"
            onClick={close}
            disabled={busy}
          >
            ยกเลิก
          </button>
          <button className="button primary" disabled={busy}>
            <PackagePlus size={17} />
            {busy ? "กำลังบันทึก…" : "เพิ่มเข้าคลัง"}
          </button>
        </div>
      </form>
    </div>
  );
}
export function EditDialog({
  item,
  close,
  submit,
  busy,
}: {
  item: InventoryItem;
  close: () => void;
  submit: (e: FormEvent<HTMLFormElement>, media: InventoryMediaDraft) => void;
  busy: boolean;
}) {
  const [media, setMedia] = useState(createEmptyMediaDraft);
  // rank/username fall back to "–" for display when empty (list & detail
  // views) — that sentinel must not leak into an editable field's starting
  // value, or saving without touching the field writes the literal "–" (or
  // "–" + whatever the merchant types next to it) into the database.
  const editableValue = (value: string) => (value === "–" ? "" : value);

  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget && !busy) close();
      }}
      onKeyDown={(e) => {
        if (e.key === "Escape" && !busy) close();
      }}
    >
      <form
        className="dialog inventory-form-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="edit-title"
        onSubmit={(event) => submit(event, media)}
        noValidate
      >
        <DialogHead
          id="edit-title"
          title={`แก้ไข ${item.tag}`}
          subtitle="แก้ไขข้อมูลสินค้าและข้อมูลเข้าสู่ระบบของรายการนี้"
          close={close}
        />
        <div className="dialog-body">
          <div className="form-grid">
            <Field label="Riot ID" full>
              <input
                name="riot_id"
                required
                autoFocus
                defaultValue={item.riotId}
              />
            </Field>
            <Field label="Username">
              <input
                name="username"
                required
                autoComplete="username"
                defaultValue={editableValue(item.username)}
              />
            </Field>
            <Field label="Email">
              <input
                name="email"
                type="email"
                autoComplete="email"
                defaultValue={editableValue(item.email)}
              />
            </Field>
            <Field label="Password">
              <PasswordInput
                name="password"
                autoComplete="new-password"
                placeholder="เว้นว่างหากไม่เปลี่ยน"
              />
            </Field>
            <Field label="รายละเอียดไอดี" full>
              <textarea
                className="resize-none"
                name="description"
                defaultValue={item.description ?? item.title}
                placeholder="ระบุสกิน, battlepass หรือข้อมูลประกอบการขาย"
              />
            </Field>
            <Field label="แรงก์">
              <RankSelect defaultValue={editableValue(item.rank)} />
            </Field>
            <Field label="เลเวล">
              <input
                name="level"
                type="number"
                min="0"
                defaultValue={item.level}
              />
            </Field>
            <Field label="ต้นทุน">
              <input
                name="cost"
                type="number"
                min="0"
                required
                defaultValue={item.cost}
              />
            </Field>
            <Field label="ราคาตั้งขาย">
              <input
                name="price"
                type="number"
                min="0"
                required
                defaultValue={item.price}
              />
            </Field>
          </div>
          <InventoryMediaFields
            existing={item.media}
            value={media}
            onChange={setMedia}
          />
        </div>
        <div className="dialog-actions">
          <button
            type="button"
            className="button"
            onClick={close}
            disabled={busy}
          >
            ยกเลิก
          </button>
          <button className="button primary" disabled={busy}>
            <Save size={17} />
            {busy ? "กำลังบันทึก…" : "บันทึกการแก้ไข"}
          </button>
        </div>
      </form>
    </div>
  );
}
export function SellDialog({
  item,
  close,
  submit,
}: {
  item: InventoryItem;
  close: () => void;
  submit: (payload: SalePayload) => Promise<string | null>;
}) {
  const [hasWarranty, setHasWarranty] = useState(false),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Bangkok",
  }).format(new Date());
  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy) return;
    const form = event.currentTarget,
      data = new FormData(form),
      name = String(data.get("customer_name") ?? "").trim(),
      soldPrice = Number(data.get("sold_price")),
      warrantyEndsAt = String(data.get("warranty_ends_at") ?? "");
    let message = "";
    if (!name) message = "กรุณากรอกชื่อ-นามสกุลลูกค้า";
    else if (!Number.isFinite(soldPrice) || soldPrice < 0)
      message = "กรุณาระบุราคาขายเป็นตัวเลขตั้งแต่ 0 บาทขึ้นไป";
    else if (hasWarranty && !warrantyEndsAt)
      message = "กรุณาเลือกวันที่หมดประกัน";
    if (message) {
      setError(message);
      const firstInvalid = form.querySelector<HTMLInputElement>(
        !name
          ? '[name="customer_name"]'
          : hasWarranty && !warrantyEndsAt
            ? '[name="warranty_ends_at"]'
            : '[name="sold_price"]',
      );
      firstInvalid?.focus();
      return;
    }
    setBusy(true);
    setError("");
    const result = await submit({
      customer: {
        name,
        facebook_url: String(data.get("facebook_url") ?? "").trim() || null,
        line_id: String(data.get("line_id") ?? "").trim() || null,
        phone: String(data.get("phone") ?? "").trim() || null,
      },
      sold_price: soldPrice,
      has_warranty: hasWarranty,
      warranty_ends_at: hasWarranty ? warrantyEndsAt : null,
      notes: String(data.get("notes") ?? "").trim() || null,
    });
    if (result) setError(result);
    setBusy(false);
  };
  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) close();
      }}
    >
      <form
        className="dialog sale-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sell-title"
        onSubmit={handleSubmit}
        noValidate
      >
        <DialogHead
          id="sell-title"
          title={`ปิดการขาย ${item.tag}`}
          subtitle="บันทึกข้อมูลลูกค้าและเงื่อนไขหลังการขาย"
          close={() => {
            if (!busy) close();
          }}
        />
        <div className="dialog-body sale-dialog-body">
          <section
            className="sale-item-preview"
            aria-labelledby="sale-preview-title"
          >
            <div>
              <span className="sale-preview-icon">
                <ShoppingBag size={19} />
              </span>
              <div>
                <small id="sale-preview-title">ไอดีที่กำลังปิดการขาย</small>
                <strong>
                  {item.tag} · {item.riotId}
                </strong>
              </div>
            </div>
            <dl>
              <div>
                <dt>Rank</dt>
                <dd>{item.rank}</dd>
              </div>
              <div>
                <dt>Level</dt>
                <dd>{item.level.toLocaleString("th-TH")}</dd>
              </div>
              <div>
                <dt>ราคาตั้งขาย</dt>
                <dd>{money.format(item.price)}</dd>
              </div>
            </dl>
          </section>
          {error && (
            <div className="auth-error sale-form-error" role="alert">
              {error}
            </div>
          )}
          <section
            className="sale-form-section"
            aria-labelledby="customer-section-title"
          >
            <div className="sale-section-title">
              <span>
                <UserRound size={17} />
              </span>
              <div>
                <h3 id="customer-section-title">ข้อมูลลูกค้า</h3>
                <p>ชื่อใช้สำหรับค้นหาและอ้างอิงรายการขายภายหลัง</p>
              </div>
            </div>
            <div className="form-grid sale-form-grid">
              <Field label="ชื่อ-นามสกุลลูกค้า *" full>
                <input
                  name="customer_name"
                  autoComplete="name"
                  autoFocus
                  data-dialog-initial-focus
                  aria-invalid={Boolean(
                    error && error.includes("ชื่อ-นามสกุล"),
                  )}
                />
              </Field>
              <Field label="Facebook">
                <span className="input-with-icon">
                  <Link2 size={16} />
                  <input
                    name="facebook_url"
                    type="url"
                    placeholder="https://facebook.com/..."
                    autoComplete="url"
                  />
                </span>
              </Field>
              <Field label="LINE">
                <input name="line_id" placeholder="LINE ID หรือชื่อบัญชี" />
              </Field>
              <Field label="เบอร์โทร">
                <span className="input-with-icon">
                  <Phone size={16} />
                  <input
                    name="phone"
                    type="tel"
                    inputMode="tel"
                    placeholder="เช่น 081-234-5678"
                    autoComplete="tel"
                  />
                </span>
              </Field>
            </div>
          </section>
          <section
            className="sale-form-section"
            aria-labelledby="sale-detail-title"
          >
            <div className="sale-section-title">
              <span>
                <ReceiptText size={17} />
              </span>
              <div>
                <h3 id="sale-detail-title">รายละเอียดการขาย</h3>
                <p>ยอดขายและเงื่อนไขรับประกันของรายการนี้</p>
              </div>
            </div>
            <div className="form-grid sale-form-grid">
              <Field label="ราคาขาย *">
                <input
                  name="sold_price"
                  type="number"
                  min="0"
                  step="0.01"
                  inputMode="decimal"
                  defaultValue={item.price}
                  aria-invalid={Boolean(error && error.includes("ราคาขาย"))}
                />
              </Field>
              <label className="warranty-check">
                <input
                  type="checkbox"
                  checked={hasWarranty}
                  onChange={(event) => {
                    setHasWarranty(event.target.checked);
                    setError("");
                  }}
                />
                <span>
                  <strong>มีประกันหลังการขาย</strong>
                  <small>ระบุวันสุดท้ายที่ร้านรับประกันรายการนี้</small>
                </span>
              </label>
              {hasWarranty && (
                <Field label="วันที่หมดประกัน *" full>
                  <span className="input-with-icon">
                    <CalendarDays size={16} />
                    <input
                      name="warranty_ends_at"
                      type="date"
                      min={today}
                      aria-invalid={Boolean(
                        error && error.includes("วันที่หมดประกัน"),
                      )}
                    />
                  </span>
                </Field>
              )}
              <Field label="รายละเอียดเพิ่มเติม" full>
                <textarea
                  className="resize-none sale-notes"
                  name="notes"
                  maxLength={2000}
                  placeholder="เช่น เงื่อนไขการรับประกัน ข้อมูลที่ตกลงกับลูกค้า หรือหมายเหตุสำหรับทีมร้าน"
                />
              </Field>
            </div>
          </section>
        </div>
        <div className="dialog-actions sale-dialog-actions">
          <p>
            <ShieldCheck size={16} />
            <span>เมื่อบันทึกแล้ว สถานะไอดีจะเปลี่ยนเป็น “ขายแล้ว”</span>
          </p>
          <div>
            <button
              type="button"
              className="button"
              onClick={close}
              disabled={busy}
            >
              ยกเลิก
            </button>
            <button className="button blue" disabled={busy}>
              <Check size={17} />
              {busy ? "กำลังบันทึก…" : "บันทึกการขาย"}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
}

export function ArchiveDialog({
  item,
  close,
  confirm,
  busy,
}: {
  item: InventoryItem;
  close: () => void;
  confirm: () => void;
  busy: boolean;
}) {
  return (
    <div
      className="dialog-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) close();
      }}
      onKeyDown={(event) => {
        if (event.key === "Escape" && !busy) close();
      }}
    >
      <section
        className="dialog archive-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="archive-title"
        aria-describedby="archive-description"
        tabIndex={-1}
      >
        <div className="dialog-head">
          <div>
            <h2 id="archive-title">เก็บรายการถาวร</h2>
            <p id="archive-description">
              {item.tag} จะถูกซ่อนจากคลังที่ใช้งานอยู่
              แต่ประวัติการทำรายการยังคงอยู่
            </p>
          </div>
          <button
            type="button"
            className="icon-button"
            aria-label="ปิด"
            onClick={close}
            disabled={busy}
          >
            <X size={20} />
          </button>
        </div>
        <div className="dialog-actions">
          <button
            type="button"
            className="button"
            autoFocus
            onClick={close}
            disabled={busy}
          >
            ยกเลิก
          </button>
          <button
            type="button"
            className="button danger"
            onClick={confirm}
            disabled={busy}
          >
            <Archive size={17} />
            {busy ? "กำลังดำเนินการ…" : "เก็บถาวร"}
          </button>
        </div>
      </section>
    </div>
  );
}
export function InventoryDetailPage({
  item,
  canManage,
  canSell,
  canNote,
  canViewAnalytics,
  onBack,
  onEdit,
  onCopyDetails,
  onReserve,
  onSell,
  onArchive,
  onEditNote,
}: {
  item: InventoryItem;
  canManage: boolean;
  canSell: boolean;
  canNote: boolean;
  canViewAnalytics: boolean;
  onBack: () => void;
  onEdit: () => void;
  onCopyDetails: () => void;
  onReserve: () => void;
  onSell: () => void;
  onArchive: () => void;
  onEditNote: () => void;
}) {
  return (
    <section
      className="inventory-detail"
      aria-labelledby="inventory-detail-title"
    >
      <button className="button ghost detail-back" onClick={onBack}>
        <ArrowLeft size={17} />
        กลับคลังไอดี
      </button>
      <header className="inventory-detail-header">
        <div>
          <div className="inventory-detail-tag-row">
            <span className="drawer-tag">{item.tag}</span>
            <span className={`status ${item.status}`}>
              {statusLabel[item.status]}
            </span>
          </div>
          <h2 id="inventory-detail-title">{item.riotId}</h2>
          <p>อัปเดตล่าสุด {item.updated}</p>
        </div>
        <div className="inventory-detail-actions">
          <button className="button blue" onClick={onCopyDetails}>
            <Copy size={17} />
            คัดลอกรายละเอียด
          </button>
          {canManage && (
            <button className="button" onClick={onEdit}>
              <Pencil size={17} />
              แก้ไขข้อมูล
            </button>
          )}
        </div>
      </header>
      <div className="inventory-detail-layout">
        <div className="inventory-detail-main">
          <InventoryMediaGallery itemTag={item.tag} media={item.media} />
          <section className="panel inventory-detail-card">
            <div className="detail-section-head">
              <div>
                <span className="eyebrow">ข้อมูลสินค้า</span>
                <h3>รายละเอียดไอดี</h3>
              </div>
            </div>
            <div className="inventory-detail-stats">
              <Data label="Username" value={item.username} />
              <Data label="Email" value={item.email} />
              <Data label="แรงก์" value={item.rank} />
              <Data label="เลเวล" value={item.level.toLocaleString("th-TH")} />
              <Data label="ต้นทุน" value={money.format(item.cost)} />
              <Data label="ราคาขาย" value={money.format(item.price)} />
              <Data
                label="จำนวนสกิน"
                value={`${item.skins.toLocaleString("th-TH")} รายการ`}
              />
              <Data
                label="ยอดเข้าชมหน้าร้าน"
                value={
                  canViewAnalytics
                    ? `${(item.viewCount ?? 0).toLocaleString("th-TH")} ครั้ง`
                    : "อัปเกรดเพื่อดู"
                }
              />
            </div>
            <div className="inventory-description">
              <span>รายละเอียด</span>
              <p>
                {item.description?.trim() || item.title || "ยังไม่มีรายละเอียด"}
              </p>
            </div>
            {(item.notes || canNote) && (
              <div className="inventory-description private-note">
                <div className="private-note-head">
                  <span>
                    <MessageSquareText size={15} aria-hidden="true" />
                    โน้ตช่วยจำภายในร้าน
                  </span>
                  {canNote && (
                    <button
                      type="button"
                      className="button ghost compact"
                      onClick={onEditNote}
                    >
                      {item.notes ? "แก้ไขโน้ต" : "เพิ่มโน้ต"}
                    </button>
                  )}
                </div>
                <p>{item.notes || "ยังไม่มีโน้ตช่วยจำสำหรับไอดีนี้"}</p>
              </div>
            )}
          </section>
        </div>
        <aside className="panel inventory-detail-side">
          <div>
            <span className="eyebrow">ดำเนินการ</span>
            <h3>จัดการรายการ</h3>
            <p>เลือกขั้นตอนถัดไปสำหรับไอดี {item.tag}</p>
          </div>
          <div className="inventory-detail-side-actions">
            <button className="button blue" onClick={onCopyDetails}>
              <Copy size={17} />
              คัดลอกข้อความส่งลูกค้า
            </button>
            {canSell && item.status === "available" && (
              <button className="button" onClick={onReserve}>
                <Clock3 size={17} />
                จองไอดี
              </button>
            )}
            {canSell && ["available", "reserved"].includes(item.status) && (
              <button className="button primary" onClick={onSell}>
                <Tag size={17} />
                ปิดการขาย
              </button>
            )}
            {canNote && (
              <button className="button" onClick={onEditNote}>
                <MessageSquareText size={17} />
                {item.notes ? "แก้ไขโน้ตช่วยจำ" : "เพิ่มโน้ตช่วยจำ"}
              </button>
            )}
            {canManage && item.status !== "archived" && (
              <button
                className="button danger subtle-danger"
                onClick={onArchive}
              >
                <Archive size={17} />
                เก็บรายการถาวร
              </button>
            )}
          </div>
          <div className="copy-safety-note">
            <ShieldCheck size={18} />
            <span>ข้อความที่คัดลอกไม่มี Username และ Password</span>
          </div>
        </aside>
      </div>
    </section>
  );
}
function Data({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}
