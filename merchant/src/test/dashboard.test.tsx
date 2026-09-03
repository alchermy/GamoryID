// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DashboardPanel } from "../features/dashboard/DashboardPanel";
import type { DashboardData, ViewSeries } from "../types/models";

afterEach(cleanup);

const dashboard = {
  summary: {
    available: 5,
    reserved: 2,
    sold_this_month: 1,
    sold_total: 3,
    inventory_value: null,
    revenue_this_month: 0,
    profit_this_month: null,
    storefront_views: 42,
  },
  activity: [],
  sales_last_7_days: [],
  subscription: { status: "active", trial_ends_at: null, writable: true },
} as unknown as DashboardData;

const summary = { available: 5, reserved: 2, sold: 1, soldTotal: 3, value: null };

const views: ViewSeries = {
  granularity: "day",
  total: 42,
  data: [
    { period: "2026-09-01", views: 3 },
    { period: "2026-09-02", views: 7 },
    { period: "2026-09-03", views: 5 },
  ],
};

function panelProps(over: Record<string, unknown> = {}) {
  return {
    dashboard,
    summary,
    canViewProfit: false,
    canViewAnalytics: true,
    storefrontViews: views,
    viewGranularity: "day" as const,
    onViewGranularityChange: vi.fn(),
    onOpenInventory: vi.fn(),
    onOpenImport: vi.fn(),
    onOpenAdd: vi.fn(),
    onRefresh: vi.fn(),
    ...over,
  };
}

describe("DashboardPanel storefront-views chart", () => {
  it("shows the day/month/year toggle + total and emits granularity changes", async () => {
    const user = userEvent.setup();
    const onViewGranularityChange = vi.fn();
    render(<DashboardPanel {...panelProps({ onViewGranularityChange })} />);

    const panel = screen
      .getAllByRole("heading", { name: "ยอดเข้าชมร้าน" })[0]
      .closest(".dashboard-views") as HTMLElement;
    expect(panel).toBeInTheDocument();
    expect(panel.textContent).toContain("42 ครั้ง รวมทั้งหมด");
    expect(panel.querySelector('[role="tab"][aria-selected="true"]')?.textContent).toBe(
      "วัน",
    );

    await user.click(
      panel.querySelector('[role="tab"]:nth-of-type(2)') as HTMLElement,
    );
    expect(onViewGranularityChange).toHaveBeenCalledWith("month");
  });

  it("shows an upsell placeholder without the analytics feature", () => {
    render(
      <DashboardPanel
        {...panelProps({ canViewAnalytics: false, storefrontViews: null })}
      />,
    );
    const panel = screen
      .getAllByRole("heading", { name: "ยอดเข้าชมร้าน" })[0]
      .closest(".dashboard-views") as HTMLElement;
    expect(panel.textContent).toContain("แพ็ก Growth");
    expect(panel.querySelector('[role="tab"]')).toBeNull();
  });
});
