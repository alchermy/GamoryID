import { useCallback, useEffect, useRef, useState } from "react";
import {
  CheckCircle2,
  Download,
  FileSpreadsheet,
  FileText,
  RefreshCw,
  UploadCloud,
} from "lucide-react";
import { downloadShopFile, prepareCsrf, shopRequest } from "../../api";
import { Field } from "../../shared/ui/form-controls";

type ImportPreview = {
  id: number;
  headers: string[];
  rows: Array<Record<string, string | null>>;
  total_rows: number;
};

type ImportState = {
  status: "preview" | "queued" | "processing" | "completed" | "failed";
  processed_rows: number;
  imported_rows: number;
  failed_rows: number;
};

type ImportErrorRow = {
  id: number;
  row_number: number;
  message: string;
};

const MAX_FILE_BYTES = 5 * 1024 * 1024;
const ACCEPTED_EXTENSIONS = new Set(["xlsx", "csv"]);
const statusLabels: Record<ImportState["status"], string> = {
  preview: "ตรวจตัวอย่างแล้ว",
  queued: "รอประมวลผล",
  processing: "กำลังนำเข้า",
  completed: "นำเข้าสำเร็จ",
  failed: "ต้องแก้ไขไฟล์",
};

function fileSize(bytes: number) {
  return bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toLocaleString("th-TH", { maximumFractionDigits: 1 })} MB`
    : `${Math.max(1, Math.round(bytes / 1024)).toLocaleString("th-TH")} KB`;
}

export function ImportPanel({
  shopId,
  onComplete,
}: {
  shopId?: number;
  onComplete: () => void;
}) {
  const [preview, setPreview] = useState<ImportPreview | null>(null),
    [mapping, setMapping] = useState<Record<string, string>>({}),
    [job, setJob] = useState<ImportState | null>(null),
    [importErrors, setImportErrors] = useState<ImportErrorRow[]>([]),
    [selectedFile, setSelectedFile] = useState<File | null>(null),
    [busy, setBusy] = useState(false),
    [downloading, setDownloading] = useState(false),
    [isDragOver, setIsDragOver] = useState(false),
    [error, setError] = useState("");
  const onCompleteRef = useRef(onComplete);

  useEffect(() => {
    onCompleteRef.current = onComplete;
  }, [onComplete]);

  const fields: [string, string][] = [
    ["riot_id", "Riot ID"],
    ["username", "Username"],
    ["password", "Password"],
    ["description", "รายละเอียดไอดี"],
    ["rank", "Rank"],
    ["level", "Level"],
    ["cost", "ต้นทุน"],
    ["list_price", "ราคาขาย"],
    ["notes", "โน้ตช่วยจำ"],
    ["title", "ชื่อรายการ (ไฟล์เดิม)"],
  ];

  const refreshJob = useCallback(
    async (signal?: AbortSignal) => {
      if (!shopId || !preview) return;
      try {
        const result = await shopRequest<{
          data: ImportState;
          errors: ImportErrorRow[];
        }>(`/imports/${preview.id}`, shopId, { signal });
        setJob(result.data);
        setImportErrors(result.errors ?? []);
        if (result.data.status === "completed") onCompleteRef.current();
      } catch (reason) {
        if (signal?.aborted) return;
        setError(
          reason instanceof Error ? reason.message : "ตรวจสอบสถานะนำเข้าไม่ได้",
        );
      }
    },
    [preview, shopId],
  );

  useEffect(() => {
    if (!job || !["queued", "processing"].includes(job.status)) return;
    const controller = new AbortController();
    const timer = window.setTimeout(
      () => void refreshJob(controller.signal),
      900,
    );
    return () => {
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [job, refreshJob]);

  const validateFile = (file: File): string | null => {
    const extension = file.name.split(".").pop()?.toLowerCase() ?? "";
    if (!ACCEPTED_EXTENSIONS.has(extension)) {
      return "รองรับเฉพาะไฟล์ Excel (.xlsx) หรือ CSV (.csv)";
    }
    if (file.size === 0) return "ไฟล์ว่างเปล่า กรุณาเลือกไฟล์ที่มีข้อมูล";
    if (file.size > MAX_FILE_BYTES) return "ไฟล์ต้องมีขนาดไม่เกิน 5 MB";
    return null;
  };

  const upload = async (file: File) => {
    if (!shopId) return;
    const validationError = validateFile(file);
    setSelectedFile(file);
    setPreview(null);
    setJob(null);
    setImportErrors([]);
    setError(validationError ?? "");
    if (validationError) return;

    setBusy(true);
    try {
      const form = new FormData();
      form.append("file", file);
      const csrf = await prepareCsrf();
      const result = await shopRequest<{ data: ImportPreview }>(
        "/imports/preview",
        shopId,
        { method: "POST", headers: csrf, body: form },
      );
      setPreview(result.data);
      setMapping(
        Object.fromEntries(
          fields.map(([key]) => [
            key,
            result.data.headers.find(
              (header) => header.trim().toLowerCase() === key,
            ) ?? "",
          ]),
        ),
      );
    } catch (reason) {
      setError(
        reason instanceof Error
          ? reason.message
          : "ไม่สามารถอ่านไฟล์ Excel หรือ CSV ได้",
      );
    } finally {
      setBusy(false);
    }
  };

  const downloadTemplate = async () => {
    if (!shopId || downloading) return;
    setDownloading(true);
    setError("");
    try {
      await downloadShopFile(
        "/imports/template",
        shopId,
        "GamoryID-inventory-import-template.xlsx",
      );
    } catch (reason) {
      setError(
        reason instanceof Error
          ? reason.message
          : "ไม่สามารถดาวน์โหลดไฟล์ตัวอย่างได้",
      );
    } finally {
      setDownloading(false);
    }
  };

  const startImport = async () => {
    if (!shopId || !preview) return;
    const selected = Object.fromEntries(
      Object.entries(mapping).filter(([, source]) => source),
    );
    if ((!selected.title && !selected.riot_id) || !selected.list_price) {
      setError("ต้องเลือกคอลัมน์ Riot ID หรือชื่อรายการ และราคาขาย");
      return;
    }
    setBusy(true);
    setError("");
    try {
      const result = await shopRequest<{ data: ImportState }>(
        `/imports/${preview.id}/confirm`,
        shopId,
        { method: "POST", body: JSON.stringify({ mapping: selected }) },
      );
      setJob(result.data);
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ไม่สามารถเริ่มนำเข้าได้",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="panel import-panel" aria-labelledby="import-title">
      <div className="panel-head import-panel-head">
        <div>
          <span className="eyebrow">Bulk inventory</span>
          <h2 id="import-title">นำเข้าข้อมูลหลายรายการ</h2>
          <small>
            ใช้ Excel หรือ CSV ขนาดไม่เกิน 5 MB ระบบจะตรวจทุกแถวก่อนบันทึก
          </small>
        </div>
      </div>
      <div className="import-body">
        {!shopId ? (
          <div className="empty">
            <strong>กรุณาเข้าสู่ระบบก่อนนำเข้าข้อมูล</strong>
          </div>
        ) : (
          <>
            <ol className="import-steps" aria-label="ขั้นตอนนำเข้าข้อมูล">
              <li>
                <span>1</span>
                <strong>ดาวน์โหลดไฟล์ตัวอย่าง</strong>
              </li>
              <li>
                <span>2</span>
                <strong>กรอกและอัปโหลดไฟล์</strong>
              </li>
              <li>
                <span>3</span>
                <strong>ตรวจตัวอย่างแล้วนำเข้า</strong>
              </li>
            </ol>

            <div className="import-start-grid">
              <section
                className="import-template-card"
                aria-labelledby="template-title"
              >
                <div className="import-card-icon" aria-hidden="true">
                  <FileSpreadsheet size={22} />
                </div>
                <div>
                  <h3 id="template-title">ไฟล์ Excel ตัวอย่าง</h3>
                  <p>
                    มีหัวคอลัมน์และคู่มือพร้อมใช้ ลบแถวตัวอย่างก่อนใส่ข้อมูลจริง
                  </p>
                </div>
                <button
                  type="button"
                  className="button blue import-download"
                  disabled={downloading}
                  onClick={() => void downloadTemplate()}
                >
                  <Download size={17} />
                  {downloading ? "กำลังดาวน์โหลด…" : "ดาวน์โหลด Excel ตัวอย่าง"}
                </button>
              </section>

              <section
                className="import-upload-card"
                aria-labelledby="upload-title"
              >
                <div
                  className={`csv-picker ${isDragOver ? "is-drag-over" : ""}`}
                  onDragOver={(event) => {
                    event.preventDefault();
                    if (!busy) setIsDragOver(true);
                  }}
                  onDragLeave={() => setIsDragOver(false)}
                  onDrop={(event) => {
                    event.preventDefault();
                    setIsDragOver(false);
                    const file = event.dataTransfer.files?.[0];
                    if (file && !busy) void upload(file);
                  }}
                >
                  <UploadCloud size={24} aria-hidden="true" />
                  <div>
                    <h3 id="upload-title">
                      {busy ? "กำลังตรวจไฟล์…" : "อัปโหลดไฟล์ที่กรอกแล้ว"}
                    </h3>
                    <p>ลากไฟล์มาวาง หรือเลือกไฟล์ .xlsx / .csv</p>
                  </div>
                  <label className="button" htmlFor="inventory-import-file">
                    เลือกไฟล์
                  </label>
                  <input
                    id="inventory-import-file"
                    aria-label="เลือกไฟล์ Excel หรือ CSV"
                    type="file"
                    accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                    disabled={busy}
                    onChange={(event) => {
                      const file = event.target.files?.[0];
                      event.currentTarget.value = "";
                      if (file) void upload(file);
                    }}
                  />
                </div>
                {selectedFile && (
                  <div className="import-file-row" role="status">
                    <FileText size={18} aria-hidden="true" />
                    <span>
                      <strong>{selectedFile.name}</strong>
                      <small>{fileSize(selectedFile.size)}</small>
                    </span>
                    {preview && (
                      <CheckCircle2 size={18} className="success-icon" />
                    )}
                  </div>
                )}
              </section>
            </div>

            {error && (
              <div className="auth-error import-error" role="alert">
                {error}
              </div>
            )}

            {preview && (
              <section
                className="import-preview"
                aria-labelledby="preview-title"
              >
                <div className="import-preview-head">
                  <div>
                    <span className="eyebrow">ตรวจสอบก่อนบันทึก</span>
                    <h3 id="preview-title">จับคู่คอลัมน์</h3>
                    <p>
                      พบ {preview.total_rows.toLocaleString("th-TH")} แถว ·
                      แสดงตัวอย่างสูงสุด 10 แถว
                    </p>
                  </div>
                </div>
                <div className="form-grid import-mapping">
                  {fields.map(([key, label]) => (
                    <Field
                      key={key}
                      label={`${label}${["riot_id", "list_price"].includes(key) ? " *" : ""}`}
                    >
                      <select
                        aria-label={`คอลัมน์สำหรับ ${label}`}
                        value={mapping[key] ?? ""}
                        onChange={(event) => {
                          setError("");
                          setMapping((current) => ({
                            ...current,
                            [key]: event.target.value,
                          }));
                        }}
                      >
                        <option value="">ไม่ต้องนำเข้า</option>
                        {preview.headers.map((header) => (
                          <option key={header} value={header}>
                            {header}
                          </option>
                        ))}
                      </select>
                    </Field>
                  ))}
                </div>
                <div className="table-wrap import-preview-table">
                  <table>
                    <thead>
                      <tr>
                        <th>#</th>
                        {preview.headers.map((header) => (
                          <th key={header}>{header}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {preview.rows.map((row, index) => (
                        <tr key={index}>
                          <td>{index + 2}</td>
                          {preview.headers.map((header) => (
                            <td key={header}>{row[header] ?? "–"}</td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="import-confirm-row">
                  <p>ระบบจะยกเลิกทั้งชุดหากพบข้อมูลผิดพลาดแม้เพียง 1 แถว</p>
                  <button
                    type="button"
                    className="button blue"
                    disabled={busy || Boolean(job)}
                    onClick={() => void startImport()}
                  >
                    {busy ? "กำลังดำเนินการ…" : "ยืนยันนำเข้าข้อมูล"}
                  </button>
                </div>

                {job && (
                  <div
                    className={`notice import-status ${job.status === "failed" ? "import-failed" : ""}`}
                    role={job.status === "failed" ? "alert" : "status"}
                  >
                    <span>
                      <strong>{statusLabels[job.status]}</strong> · ประมวลผล{" "}
                      {job.processed_rows.toLocaleString("th-TH")} แถว · สำเร็จ{" "}
                      {job.imported_rows.toLocaleString("th-TH")} · ผิดพลาด{" "}
                      {job.failed_rows.toLocaleString("th-TH")}
                    </span>
                    {["queued", "processing"].includes(job.status) && (
                      <button
                        type="button"
                        className="button ghost"
                        onClick={() => void refreshJob()}
                      >
                        <RefreshCw size={16} />
                        อัปเดตสถานะ
                      </button>
                    )}
                  </div>
                )}

                {importErrors.length > 0 && (
                  <div className="import-error-list" role="alert">
                    <h4>แถวที่ต้องแก้ไข</h4>
                    <ul>
                      {importErrors.map((item) => (
                        <li key={item.id}>
                          <strong>แถว {item.row_number}</strong>
                          <span>{item.message}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </section>
            )}
          </>
        )}
      </div>
    </section>
  );
}
