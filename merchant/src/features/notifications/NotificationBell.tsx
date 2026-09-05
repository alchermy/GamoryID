import { useCallback, useEffect, useRef, useState } from "react";
import { Bell, ScrollText } from "lucide-react";
import { shopRequest } from "../../api";
import { formatRelativeTime } from "../../shared/lib/format";
import {
  activityIcon,
  activityLabel,
  activitySubjectHint,
} from "../../shared/lib/activity";
import type { ActivityResponse } from "../../types/models";

// The backend only accepts per_page in {25,50,100}; we fetch the smallest
// page and just show the first few entries in the dropdown.
const FETCH_PER_PAGE = 25;
const PREVIEW_COUNT = 8;
const lastSeenKey = (shopId: number) => `gamoryid.notif.lastSeen.${shopId}`;

/**
 * Bell icon in the topbar. When the shop's plan/permissions allow viewing the
 * activity log, it shows the most recent entries with an unread-count badge
 * (tracked client-side via localStorage, since there is no per-user "read"
 * state on the backend). Otherwise it falls back to a plain toast.
 */
export function NotificationBell({
  shopId,
  canView,
  onViewAll,
  fallbackNotify,
}: {
  shopId: number;
  canView: boolean;
  onViewAll: () => void;
  fallbackNotify: (message: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [entries, setEntries] = useState<ActivityResponse["data"]>([]);
  const [unread, setUnread] = useState(0);
  const rootRef = useRef<HTMLDivElement>(null);

  const readLastSeen = useCallback(() => {
    try {
      return localStorage.getItem(lastSeenKey(shopId)) ?? "";
    } catch {
      return "";
    }
  }, [shopId]);

  const load = useCallback(async () => {
    if (!canView) return;
    setLoading(true);
    setError("");
    try {
      const result = await shopRequest<ActivityResponse>(
        `/activity?per_page=${FETCH_PER_PAGE}&page=1`,
        shopId,
      );
      const preview = result.data.slice(0, PREVIEW_COUNT);
      setEntries(preview);
      const lastSeen = readLastSeen();
      // Compare as actual instants, not strings — the API returns
      // timestamps with the app's UTC+7 offset (+07:00) while lastSeen is
      // stored as a browser Date.toISOString() (Z); the two formats don't
      // sort correctly against each other as plain strings.
      const lastSeenMs = lastSeen ? new Date(lastSeen).getTime() : null;
      setUnread(
        lastSeenMs === null
          ? preview.length
          : preview.filter((e) => new Date(e.created_at).getTime() > lastSeenMs).length,
      );
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "โหลดการแจ้งเตือนไม่สำเร็จ",
      );
    } finally {
      setLoading(false);
    }
  }, [canView, shopId, readLastSeen]);

  // Fetch once on mount just to populate the unread badge (without opening the panel).
  useEffect(() => {
    if (canView) void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canView, shopId]);

  useEffect(() => {
    if (!open) return;
    const onPointerDown = (event: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open]);

  const toggle = () => {
    if (!canView) {
      fallbackNotify("ยังไม่มีการแจ้งเตือนใหม่");
      return;
    }
    const willOpen = !open;
    setOpen(willOpen);
    if (willOpen) {
      void load();
      setUnread(0);
      try {
        localStorage.setItem(lastSeenKey(shopId), new Date().toISOString());
      } catch {
        /* private-browsing / storage disabled — badge just won't persist */
      }
    }
  };

  return (
    <div className="notif-bell" ref={rootRef}>
      <button
        className="icon-button"
        aria-label="การแจ้งเตือน"
        aria-haspopup="true"
        aria-expanded={open}
        onClick={toggle}
      >
        <Bell size={19} />
        {unread > 0 && (
          <span className="notif-badge" aria-hidden="true">
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </button>
      {open && (
        <div className="notif-panel" role="menu" aria-label="รายการแจ้งเตือน">
          <div className="notif-panel-head">
            <strong>การแจ้งเตือน</strong>
          </div>
          {error ? (
            <div className="notif-empty">
              <p>{error}</p>
            </div>
          ) : loading && entries.length === 0 ? (
            <div className="notif-empty" aria-live="polite">
              กำลังโหลด…
            </div>
          ) : entries.length === 0 ? (
            <div className="notif-empty">
              <ScrollText size={20} aria-hidden="true" />
              <span>ยังไม่มีการแจ้งเตือนใหม่</span>
            </div>
          ) : (
            <ul className="notif-list">
              {entries.map((entry) => (
                <li key={entry.id} className="notif-item">
                  <span className="notif-item-icon">{activityIcon(entry.event)}</span>
                  <span className="notif-item-body">
                    <strong>{activityLabel(entry.event)}</strong>
                    <small>{activitySubjectHint(entry) || entry.actor?.name || "ระบบอัตโนมัติ"}</small>
                    <small className="notif-item-time">
                      {formatRelativeTime(entry.created_at)}
                    </small>
                  </span>
                </li>
              ))}
            </ul>
          )}
          <button
            className="notif-panel-footer"
            onClick={() => {
              setOpen(false);
              onViewAll();
            }}
          >
            ดูบันทึกกิจกรรมทั้งหมด
          </button>
        </div>
      )}
    </div>
  );
}
