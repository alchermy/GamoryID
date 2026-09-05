import { useCallback, useEffect, useMemo, useState } from "react";
import { RefreshCw, ScrollText } from "lucide-react";
import { shopRequest } from "../../api";
import { formatDate } from "../../shared/lib/format";
import {
  activityIcon,
  activityLabel,
  activitySubjectHint,
} from "../../shared/lib/activity";
import type { ActivityResponse } from "../../types/models";

const ROLE_LABEL: Record<string, string> = { owner: "เจ้าของร้าน", staff: "พนักงาน" };

export function ActivityPanel({ shopId }: { shopId: number }) {
  const [data, setData] = useState<ActivityResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [page, setPage] = useState(1);
  const [event, setEvent] = useState("");
  const [actor, setActor] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [q, setQ] = useState("");

  const queryString = useMemo(() => {
    const params = new URLSearchParams({ per_page: "50", page: String(page) });
    if (event) params.set("event", event);
    if (actor) params.set("actor", actor);
    if (from) params.set("from", from);
    if (to) params.set("to", to);
    if (q.trim()) params.set("q", q.trim());
    return params.toString();
  }, [page, event, actor, from, to, q]);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await shopRequest<ActivityResponse>(
        `/activity?${queryString}`,
        shopId,
      );
      setData(result);
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "ไม่สามารถโหลดบันทึกกิจกรรมได้",
      );
    } finally {
      setLoading(false);
    }
  }, [queryString, shopId]);

  useEffect(() => {
    const handle = window.setTimeout(() => void load(), q ? 300 : 0);
    return () => window.clearTimeout(handle);
  }, [load, q]);

  const resetToFirstPage = <T,>(setter: (v: T) => void) => (value: T) => {
    setPage(1);
    setter(value);
  };

  const entries = data?.data ?? [];
  const meta = data?.meta;

  return (
    <section className="panel management-panel" aria-labelledby="activity-title">
      <div className="panel-head">
        <div>
          <h2 id="activity-title">บันทึกกิจกรรม</h2>
          <small>
            ทุกการกระทำของเจ้าของร้านและพนักงาน — ตรวจสอบย้อนหลังได้
          </small>
        </div>
        <button className="button" onClick={() => void load()} disabled={loading}>
          <RefreshCw size={16} />
          รีเฟรช
        </button>
      </div>

      <div className="activity-filters">
        <input
          type="search"
          placeholder="ค้นหาชื่อผู้ทำรายการ หรือรายละเอียด"
          value={q}
          onChange={(e) => resetToFirstPage(setQ)(e.target.value)}
          aria-label="ค้นหาบันทึกกิจกรรม"
        />
        <select
          value={event}
          onChange={(e) => resetToFirstPage(setEvent)(e.target.value)}
          aria-label="กรองตามประเภทกิจกรรม"
        >
          <option value="">กิจกรรมทั้งหมด</option>
          {(data?.filters.events ?? []).map((value) => (
            <option key={value} value={value}>
              {activityLabel(value)}
            </option>
          ))}
        </select>
        <select
          value={actor}
          onChange={(e) => resetToFirstPage(setActor)(e.target.value)}
          aria-label="กรองตามผู้ทำรายการ"
        >
          <option value="">ผู้ทำรายการทั้งหมด</option>
          {(data?.filters.actors ?? []).map((person) => (
            <option key={person.id} value={String(person.id)}>
              {person.name} ({ROLE_LABEL[person.role] ?? person.role})
            </option>
          ))}
          <option value="system">ระบบอัตโนมัติ</option>
        </select>
        <label className="activity-date">
          <span>ตั้งแต่</span>
          <input
            type="date"
            value={from}
            max={to || undefined}
            onChange={(e) => resetToFirstPage(setFrom)(e.target.value)}
          />
        </label>
        <label className="activity-date">
          <span>ถึง</span>
          <input
            type="date"
            value={to}
            min={from || undefined}
            onChange={(e) => resetToFirstPage(setTo)(e.target.value)}
          />
        </label>
      </div>

      {error ? (
        <div className="empty" role="alert">
          <strong>โหลดข้อมูลไม่สำเร็จ</strong>
          <p>{error}</p>
          <button className="button" onClick={() => void load()}>
            ลองใหม่
          </button>
        </div>
      ) : loading && !data ? (
        <div className="management-loading" aria-live="polite">
          กำลังโหลดบันทึกกิจกรรม…
        </div>
      ) : (
        <>
          <div className="table-wrap">
            <table className="member-table activity-table">
              <thead>
                <tr>
                  <th>เวลา</th>
                  <th>ผู้ทำรายการ</th>
                  <th>กิจกรรม</th>
                  <th>รายละเอียด</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id}>
                    <td>{formatDate(entry.created_at)}</td>
                    <td>
                      <strong>{entry.actor?.name ?? "ระบบอัตโนมัติ"}</strong>
                    </td>
                    <td>
                      <span className="activity-event">
                        {activityIcon(entry.event)}
                        {activityLabel(entry.event)}
                      </span>
                    </td>
                    <td>
                      <small>{activitySubjectHint(entry) || "–"}</small>
                    </td>
                    <td>
                      <small>{entry.ip_address ?? "–"}</small>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {entries.length === 0 && (
            <div className="empty">
              <ScrollText size={22} aria-hidden="true" />
              <strong>ยังไม่มีบันทึกกิจกรรมตามเงื่อนไขนี้</strong>
            </div>
          )}

          {meta && meta.last_page > 1 && (
            <div className="activity-pager">
              <button
                className="button"
                disabled={meta.current_page <= 1 || loading}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                ก่อนหน้า
              </button>
              <span>
                หน้า {meta.current_page} / {meta.last_page} · {meta.total} รายการ
              </span>
              <button
                className="button"
                disabled={meta.current_page >= meta.last_page || loading}
                onClick={() => setPage((p) => p + 1)}
              >
                ถัดไป
              </button>
            </div>
          )}
        </>
      )}
    </section>
  );
}
