// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DashboardPanel } from "../features/dashboard/DashboardPanel";
import type {
  DashboardData,
  SalesSeries,
  ViewSeries,
} from "../types/models";

afterEach(cleanup);

const dashboard = {
  summary: {
    available: 5,
    reserved: 2,
    sold_this_month: 1,
    sold_total: 3,
    inventory_value: null,
    revenue_this_month: 12000,
    profit_this_month: 4000,
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

const salesReport: SalesSeries = {
  granularity: "day",
  totals: { revenue: 15000, sales: 4, profit: 6000 },
  previous: { revenue: 12000, sales: 3, profit: 5000 },
  data: [
    { period: "2026-09-01", revenue: 5000, sales: 1, profit: 2000 },
    { period: "2026-09-02", revenue: 7000, sales: 2, profit: 3000 },
    { period: "2026-09-03", revenue: 3000, sales: 1, profit: 1000 },
  ],
};

function panelProps(over: Record<string, unknown> = {}) {
  return {
    dashboard,
    summary,
    canViewProfit: true,
    canViewAnalytics: true,
    storefrontViews: views,
    viewGranularity: "day" as const,
    onViewGranularityChange: vi.fn(),
    salesReport,
    salesGranularity: "day" as const,
    onSalesGranularityChange: vi.fn(),
    onOpenInventory: vi.fn(),
    onOpenImport: vi.fn(),
    onOpenAdd: vi.fn(),
    onRefresh: vi.fn(),
    ...over,
  };
}

describe("DashboardPanel redesign", () => {
  it("drops the SHOP PULSE band and wires the refresh button", async () => {
    const user = userEvent.setup();
    const onRefresh = vi.fn();
    render(<DashboardPanel {...panelProps({ onRefresh })} />);

    expect(screen.queryByText(/SHOP PULSE/)).toBeNull();
    await user.click(screen.getByRole("button", { name: "รีเฟรชข้อมูล" }));
    expect(onRefresh).toHaveBeenCalled();
  });

  it("renders the sales chart with a working day/month/year toggle", async () => {
    const user = userEvent.setup();
    const onSalesGranularityChange = vi.fn();
    render(
      <DashboardPanel {...panelProps({ onSalesGranularityChange })} />,
    );

    const panel = screen
      .getByRole("heading", { name: "ยอดขาย" })
      .closest(".dashboard-sales") as HTMLElement;
    expect(panel).toBeInTheDocument();
    expect(panel.textContent).toContain("30 วันล่าสุด");
    expect(
      panel.querySelector('[role="tab"][aria-selected="true"]')?.textContent,
    ).toBe("วัน");

    await user.click(
      panel.querySelector('[role="tab"]:nth-of-type(2)') as HTMLElement,
    );
    expect(onSalesGranularityChange).toHaveBeenCalledWith("month");
  });

  it("shows a vs-previous delta on the revenue KPI and profit bars when allowed", () => {
    render(<DashboardPanel {...panelProps()} />);
    // 15000 vs 12000 previous = +25%
    expect(screen.getByText(/\+25% เทียบช่วงก่อน/)).toBeInTheDocument();
    const salesPanel = screen
      .getByRole("heading", { name: "ยอดขาย" })
      .closest(".dashboard-sales") as HTMLElement;
    expect(salesPanel.querySelector(".sales-column > i b")).not.toBeNull();
  });

  it("hides profit entirely without the permission", () => {
    const noProfitSales: SalesSeries = {
      ...salesReport,
      totals: { ...salesReport.totals, profit: null },
      previous: { ...salesReport.previous, profit: null },
      data: salesReport.data.map((d) => ({ ...d, profit: null })),
    };
    render(
      <DashboardPanel
        {...panelProps({ canViewProfit: false, salesReport: noProfitSales })}
      />,
    );
    const salesPanel = screen
      .getByRole("heading", { name: "ยอดขาย" })
      .closest(".dashboard-sales") as HTMLElement;
    expect(salesPanel.querySelector(".sales-column > i b")).toBeNull();
    expect(screen.getByText("ไม่มีสิทธิ์ดูต้นทุนและกำไร")).toBeInTheDocument();
  });

  it("keeps the storefront-views chart and its upsell placeholder", () => {
    render(
      <DashboardPanel
        {...panelProps({ canViewAnalytics: false, storefrontViews: null })}
      />,
    );
    const panel = screen
      .getByRole("heading", { name: "ยอดเข้าชมร้าน" })
      .closest(".dashboard-views") as HTMLElement;
    expect(panel.textContent).toContain("แพ็ก Growth");
    expect(panel.querySelector('[role="tab"]')).toBeNull();
  });

  it("does not crash when the selected granularity is ahead of the fetched series", () => {
    // Rapid วัน→ปี toggle: the year tab is active but the report still holds
    // day-shaped periods. fmtPeriod used to feed those to Intl and throw
    // RangeError, white-screening the page.
    const dayShapedButYearTab: SalesSeries = { ...salesReport, granularity: "day" };
    expect(() =>
      render(
        <DashboardPanel
          {...panelProps({
            salesReport: dayShapedButYearTab,
            salesGranularity: "year" as const,
            storefrontViews: { ...views, granularity: "day" as const },
            viewGranularity: "year" as const,
          })}
        />,
      ),
    ).not.toThrow();
    expect(screen.getByRole("heading", { name: "ยอดขาย" })).toBeInTheDocument();
  });
});
