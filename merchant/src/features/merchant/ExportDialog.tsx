import { useState } from "react";
import { Download, X } from "lucide-react";
import { downloadShopFile } from "../../api";

type ExportKind = "inventory" | "sales";

export function ExportDialog({
  shopId,
  shopSlug,
  canSalesExport,
  notify,
  close,
}: {
  shopId: number;
  shopSlug: string;
  canSalesExport: boolean;
  notify: (message: string) => void;
  close: () => void;
}) {
  const today = new Date().toISOString().slice(0, 10);
  const [kind, setKind] = useState<ExportKind>("inventory");
  const [from, setFrom] = useState(`${today.slice(0, 8)}01`);
  const [to, setTo] = useState(today);
  const [busy, setBusy] = useState(false);

  const run = async () => {
    setBusy(true);
    try {
      if (kind === "inventory") {
        await downloadShopFile(
          "/export/inventory.csv",
          shopId,
          `gamoryid-${shopSlug}-inventory.csv`,
        );
      } else {
        await downloadShopFile(
          `/export/sales.csv?from=${from}&to=${to}`,
          shopId,
          `gamoryid-${shopSlug}-sales-${from}_${to}.csv`,
        );
      }
      notify("เริ่มดาวน์โหลดไฟล์ส่งออกแล้ว");
      close();
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ส่งออกข้อมูลไม่สำเร็จ",
      );
      setBusy(false);
    }
  };

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
        className="dialog export-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="export-title"
        tabIndex={-1}
      >
        <div className="dialog-head">
          <div>
            <h2 id="export-title">ส่งออกข้อมูล</h2>
            <p>เลือกชุดข้อมูลที่ต้องการดาวน์โหลดเป็นไฟล์ CSV</p>
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
        <div className="dialog-body export-dialog-body">
          <label className="export-option">
            <input
              type="radio"
              name="export-kind"
              checked={kind === "inventory"}
              onChange={() => setKind("inventory")}
            />
            <span>
              <strong>คลังไอดี</strong>
              <small>ไอดีทั้งหมดในร้าน รวมที่เก็บถาวร</small>
            </span>
          </label>
          <label
            className={`export-option ${canSalesExport ? "" : "is-locked"}`}
          >
            <input
              type="radio"
              name="export-kind"
              checked={kind === "sales"}
              disabled={!canSalesExport}
              onChange={() => setKind("sales")}
            />
            <span>
              <strong>รายการขาย</strong>
              <small>
                {canSalesExport
                  ? "ยอดขาย กำไร และลูกค้า ตามช่วงวันที่"
                  : "อัปเกรดเป็นแพ็ก Growth ขึ้นไปเพื่อส่งออกรายงานยอดขาย"}
              </small>
            </span>
          </label>
          {kind === "sales" && canSalesExport && (
            <div className="export-range">
              <label>
                ตั้งแต่
                <input
                  type="date"
                  value={from}
                  max={to}
                  onChange={(event) => setFrom(event.target.value)}
                />
              </label>
              <label>
                ถึง
                <input
                  type="date"
                  value={to}
                  min={from}
                  max={today}
                  onChange={(event) => setTo(event.target.value)}
                />
              </label>
            </div>
          )}
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
          <button
            type="button"
            className="button primary"
            onClick={() => void run()}
            disabled={busy}
          >
            <Download size={17} />
            {busy ? "กำลังเตรียมไฟล์…" : "ดาวน์โหลด CSV"}
          </button>
        </div>
      </section>
    </div>
  );
}
