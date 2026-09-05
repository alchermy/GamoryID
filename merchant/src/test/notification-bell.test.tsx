// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { NotificationBell } from "../features/notifications/NotificationBell";
import type { ActivityResponse } from "../types/models";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  localStorage.clear();
});

function activity(over: Partial<ActivityResponse> = {}): ActivityResponse {
  return {
    data: [
      {
        id: 2,
        event: "inventory.sold",
        actor: { id: 1, name: "เจ้าของร้าน" },
        subject_type: "InventoryItem",
        subject_id: 5,
        metadata: { tag: "#23DX5" },
        ip_address: "1.2.3.4",
        created_at: new Date().toISOString(),
      },
      {
        id: 1,
        event: "inventory.created",
        actor: { id: 1, name: "เจ้าของร้าน" },
        subject_type: "InventoryItem",
        subject_id: 4,
        metadata: { tag: "#8KM4R" },
        ip_address: "1.2.3.4",
        created_at: new Date(Date.now() - 3_600_000).toISOString(),
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 8, total: 2 },
    filters: { events: [], actors: [] },
    ...over,
  };
}

function mockFetch(payload: ActivityResponse) {
  // The component fetches on mount (to seed the badge) and again on open, so
  // each call needs its own Response — a shared instance can't have its body
  // read twice.
  return vi
    .spyOn(globalThis, "fetch")
    .mockImplementation(
      async () =>
        new Response(JSON.stringify(payload), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
    );
}

describe("NotificationBell", () => {
  it("shows an unread badge and the recent entries when opened", async () => {
    mockFetch(activity());
    const user = userEvent.setup();
    const onViewAll = vi.fn();
    render(
      <NotificationBell
        shopId={1}
        canView={true}
        onViewAll={onViewAll}
        fallbackNotify={vi.fn()}
      />,
    );

    expect(await screen.findByText("2")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "การแจ้งเตือน" }));
    expect(await screen.findByText("บันทึกการขายไอดี")).toBeInTheDocument();
    expect(screen.getByText("เพิ่มไอดีในคลัง")).toBeInTheDocument();
    expect(screen.getByText("#23DX5")).toBeInTheDocument();

    // opening clears the unread badge
    expect(screen.queryByText("2")).toBeNull();

    await user.click(
      screen.getByRole("button", { name: "ดูบันทึกกิจกรรมทั้งหมด" }),
    );
    expect(onViewAll).toHaveBeenCalledTimes(1);
  });

  it("shows an empty state when there is no recent activity", async () => {
    mockFetch(activity({ data: [] }));
    const user = userEvent.setup();
    render(
      <NotificationBell
        shopId={1}
        canView={true}
        onViewAll={vi.fn()}
        fallbackNotify={vi.fn()}
      />,
    );
    await user.click(screen.getByRole("button", { name: "การแจ้งเตือน" }));
    expect(
      await screen.findByText("ยังไม่มีการแจ้งเตือนใหม่"),
    ).toBeInTheDocument();
  });

  it("clears the badge correctly even when the API's timestamp offset (+07:00) differs from the browser's stored lastSeen (Z)", async () => {
    // Regression test: the API returns created_at with a +07:00 offset while
    // lastSeen is stored via Date.toISOString() (Z). Comparing those as plain
    // strings is invalid (e.g. "20:53+07:00" > "14:01Z" lexicographically,
    // even though 14:01 UTC is later) — the fix compares actual instants.
    const now = Date.now();
    const toOffset = (ms: number) => {
      const d = new Date(ms + 7 * 3_600_000); // shift to +07:00 wall-clock
      const pad = (n: number) => String(n).padStart(2, "0");
      return (
        `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}` +
        `T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(d.getUTCSeconds())}+07:00`
      );
    };
    mockFetch(
      activity({
        data: [
          {
            id: 1,
            event: "inventory.sold",
            actor: { id: 1, name: "เจ้าของร้าน" },
            subject_type: "InventoryItem",
            subject_id: 5,
            metadata: { tag: "#23DX5" },
            ip_address: "1.2.3.4",
            created_at: toOffset(now - 60_000), // 1 minute ago
          },
        ],
      }),
    );
    const user = userEvent.setup();
    render(
      <NotificationBell
        shopId={1}
        canView={true}
        onViewAll={vi.fn()}
        fallbackNotify={vi.fn()}
      />,
    );

    await user.click(screen.getByRole("button", { name: "การแจ้งเตือน" }));
    await screen.findByText("บันทึกการขายไอดี");
    // just opened -> badge cleared
    expect(screen.queryByText("1")).toBeNull();

    await user.click(screen.getByRole("button", { name: "การแจ้งเตือน" }));
    await user.click(screen.getByRole("button", { name: "การแจ้งเตือน" }));
    // no new activity since — badge should stay clear, not reappear as "1"
    expect(screen.queryByText("1")).toBeNull();
  });

  it("falls back to a plain toast when the shop can't view activity", async () => {
    const fetchMock = mockFetch(activity());
    const user = userEvent.setup();
    const fallbackNotify = vi.fn();
    render(
      <NotificationBell
        shopId={1}
        canView={false}
        onViewAll={vi.fn()}
        fallbackNotify={fallbackNotify}
      />,
    );

    await user.click(screen.getByRole("button", { name: "การแจ้งเตือน" }));
    expect(fallbackNotify).toHaveBeenCalledWith("ยังไม่มีการแจ้งเตือนใหม่");
    expect(fetchMock).not.toHaveBeenCalled();
    expect(screen.queryByRole("menu")).toBeNull();
  });
});
