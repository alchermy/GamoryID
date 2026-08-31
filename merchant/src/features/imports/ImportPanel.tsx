import { useState } from "react";
import { FileUp } from "lucide-react";
import { prepareCsrf, shopRequest } from "../../api";
import { Field } from "../../shared/ui/form-controls";

type ImportPreview = {
  id: number;
  headers: string[];
  rows: Array<Record<string, string | null>>;
  total_rows: number;
};
type ImportState = {
  status: string;
  processed_rows: number;
  imported_rows: number;
  failed_rows: number;
};
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
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const fields: [string, string][] = [
    ["riot_id", "Riot ID"],
    ["title", "ชื่อรายการ/รายละเอียด"],
    ["list_price", "ราคาตั้งขาย"],
    ["cost", "ต้นทุน"],
    ["rank", "แรงก์"],
    ["level", "เลเวล"],
    ["username", "Username"],
    ["password", "Password"],
  ];
  const upload = async (file: File) => {
    if (!shopId) return;
    setBusy(true);
    setError("");
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
      const defaults = Object.fromEntries(
        fields.map(([key]) => [
          key,
          result.data.headers.find((header) => header.toLowerCase() === key) ||
            "",
        ]),
      );
      setMapping(defaults);
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ไม่สามารถอ่านไฟล์ CSV ได้",
      );
    } finally {
      setBusy(false);
    }
  };
  const startImport = async () => {
    if (!shopId || !preview) return;
    const selected = Object.fromEntries(
      Object.entries(mapping).filter(([, source]) => source),
    );
    if ((!selected.title && !selected.riot_id) || !selected.list_price) {
      setError("ต้องเลือกคอลัมน์ Riot ID หรือชื่อรายการ และราคาตั้งขาย");
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
      window.setTimeout(() => void refresh(), 750);
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ไม่สามารถเริ่มนำเข้าได้",
      );
    } finally {
      setBusy(false);
    }
  };
  const refresh = async () => {
    if (!shopId || !preview) return;
    try {
      const result = await shopRequest<{ data: ImportState }>(
        `/imports/${preview.id}`,
        shopId,
      );
      setJob(result.data);
      if (result.data.status === "completed") onComplete();
      else if (["queued", "processing"].includes(result.data.status))
        window.setTimeout(() => void refresh(), 900);
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ตรวจสอบสถานะนำเข้าไม่ได้",
      );
    }
  };
  return (
    <section className="panel import-panel" aria-labelledby="import-title">
      <div className="panel-head">
        <div>
          <h2 id="import-title">นำเข้า CSV</h2>
          <small>
            เลือกไฟล์ CSV ขนาดไม่เกิน 5 MB แล้วจับคู่คอลัมน์ก่อนเริ่มนำเข้า
          </small>
        </div>
      </div>
      <div className="dialog-body">
        {!shopId ? (
          <div className="empty">
            <strong>กรุณาเข้าสู่ระบบก่อนนำเข้า CSV</strong>
          </div>
        ) : (
          <>
            <label className="csv-picker">
              <FileUp size={20} />
              <span>{busy ? "กำลังอ่านไฟล์…" : "เลือกไฟล์ CSV"}</span>
              <input
                aria-label="เลือกไฟล์ CSV"
                type="file"
                accept=".csv,text/csv"
                disabled={busy}
                onChange={(event) => {
                  const file = event.target.files?.[0];
                  if (file) void upload(file);
                }}
              />
            </label>
            {error && (
              <div className="auth-error" role="alert">
                {error}
              </div>
            )}
            {preview && (
              <>
                <p className="hint">
                  พบ {preview.total_rows.toLocaleString("th-TH")} แถว · ตัวอย่าง
                  10 แถวแรกจะแสดงด้านล่าง
                </p>
                <div className="form-grid import-mapping">
                  {fields.map(([key, label]) => (
                    <Field
                      key={key}
                      label={`${label}${["title", "list_price"].includes(key) ? " *" : ""}`}
                    >
                      <select
                        value={mapping[key] ?? ""}
                        onChange={(event) =>
                          setMapping((current) => ({
                            ...current,
                            [key]: event.target.value,
                          }))
                        }
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
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        {preview.headers.map((header) => (
                          <th key={header}>{header}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {preview.rows.map((row, index) => (
                        <tr key={index}>
                          {preview.headers.map((header) => (
                            <td key={header}>{row[header] ?? "–"}</td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="dialog-actions">
                  <button
                    className="button blue"
                    disabled={busy || Boolean(job)}
                    onClick={() => void startImport()}
                  >
                    {busy ? "กำลังดำเนินการ…" : "ยืนยันการนำเข้า"}
                  </button>
                </div>
                {job && (
                  <div
                    className={`notice import-status ${job.status === "failed" ? "import-failed" : ""}`}
                    role={job.status === "failed" ? "alert" : "status"}
                  >
                    <span>
                      <strong>สถานะ: {job.status}</strong> · ประมวลผล{" "}
                      {job.processed_rows} แถว, สำเร็จ {job.imported_rows} แถว,
                      ผิดพลาด {job.failed_rows} แถว
                    </span>
                    {["queued", "processing"].includes(job.status) && (
                      <button
                        className="button ghost"
                        onClick={() => void refresh()}
                      >
                        อัปเดตสถานะ
                      </button>
                    )}
                  </div>
                )}
              </>
            )}
          </>
        )}
      </div>
    </section>
  );
}
