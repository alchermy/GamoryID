// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import {
  cleanup,
  fireEvent,
  render,
  screen,
  within,
} from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AnalyticsPanel } from "../features/analytics/AnalyticsPanel";
import type { AnalyticsReport } from "../types/models";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

function report(over: Partial<AnalyticsReport> = {}): AnalyticsReport {
  return {
    range: { from: "2026-09-01", to: "2026-09-04" },
    summary: {
      revenue: 20900,
      sales: 3,
      profit: 7300,
      margin_pct: 34.9,
      avg_price: 6966.67,
      avg_days_to_sell: 4.5,
    },
    by_rank: [
      { label: "Immortal", sales: 2, revenue: 20000, profit: 7000 },
      { label: "Diamond", sales: 1, revenue: 900, profit: 300 },
    ],
    by_price_band: [
      { label: "ต่ำกว่า 1,000", min: 0, sales: 1, revenue: 900 },
      { label: "1,000–2,999", min: 1000, sales: 0, revenue: 0 },
      { label: "3,000–4,999", min: 3000, sales: 0, revenue: 0 },
      { label: "5,000–9,999", min: 5000, sales: 1, revenue: 8000 },
      { label: "10,000 ขึ้นไป", min: 10000, sales: 1, revenue: 12000 },
    ],
    by_staff: [
      { id: 1, name: "เจ้าของร้าน", sales: 2, revenue: 20000, profit: 7000 },
      { id: 2, name: "พนักงาน A", sales: 1, revenue: 900, profit: 300 },
    ],
    top_customers: [
      {
        id: 9,
        name: "ลูกค้า VIP",
        sales: 2,
        revenue: 20000,
        last_bought_at: "2026-09-04T10:00:00Z",
      },
    ],
    ...over,
  };
}

function mockFetch(payload: AnalyticsReport) {
  return vi.spyOn(globalThis, "fetch").mockResolvedValue(
    new Response(JSON.stringify(payload), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    }),
  );
}

describe("AnalyticsPanel", () => {
  it("renders the KPI row and the four breakdown cards", async () => {
    mockFetch(report());
    const { container } = render(
      <AnalyticsPanel shopId={1} canViewProfit={true} />,
    );

    expect(
      await screen.findByRole("heading", { name: "ยอดขายตามแรงก์" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "ยอดขายตามช่วงราคา" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "ผลงานทีมขาย" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "ลูกค้าที่ซื้อมากที่สุด" }),
    ).toBeInTheDocument();

    const kpis = container.querySelector(".analytics-kpis") as HTMLElement;
    expect(kpis.textContent).toContain("฿20,900");
    expect(kpis.textContent).toContain("34.9%");
    // staff profit column present when allowed
    const staffCard = screen
      .getByRole("heading", { name: "ผลงานทีมขาย" })
      .closest(".analytics-card") as HTMLElement;
    expect(within(staffCard).getByText("กำไร")).toBeInTheDocument();
  });

  it("hides every profit figure without the permission", async () => {
    mockFetch(
      report({
        summary: {
          revenue: 20900,
          sales: 3,
          profit: null,
          margin_pct: null,
          avg_price: 6966.67,
          avg_days_to_sell: 4.5,
        },
        by_rank: [
          { label: "Immortal", sales: 2, revenue: 20000, profit: null },
        ],
        by_staff: [
          { id: 1, name: "เจ้าของร้าน", sales: 2, revenue: 20000, profit: null },
        ],
      }),
    );
    const { container } = render(
      <AnalyticsPanel shopId={1} canViewProfit={false} />,
    );

    const staffCard = (
      await screen.findByRole("heading", { name: "ผลงานทีมขาย" })
    ).closest(".analytics-card") as HTMLElement;
    expect(within(staffCard).queryByText("กำไร")).toBeNull();

    const kpis = container.querySelector(".analytics-kpis") as HTMLElement;
    // the "กำไร" and "อัตรากำไร" KPIs show a dash
    expect(within(kpis).getAllByText("—").length).toBeGreaterThanOrEqual(2);
  });

  it("refetches with the new range when ดูรายงาน is clicked", async () => {
    const user = userEvent.setup();
    const fetchMock = mockFetch(report());
    render(<AnalyticsPanel shopId={1} canViewProfit={true} />);
    await screen.findByRole("heading", { name: "ยอดขายตามแรงก์" });

    const first = fetchMock.mock.calls.length;
    const fromInput = screen.getByLabelText("ตั้งแต่") as HTMLInputElement;
    fireEvent.change(fromInput, { target: { value: "2026-01-01" } });
    await user.click(screen.getByRole("button", { name: "ดูรายงาน" }));

    expect(fetchMock.mock.calls.length).toBeGreaterThan(first);
    expect(String(fetchMock.mock.calls.at(-1)?.[0])).toContain(
      "from=2026-01-01",
    );
  });

  it("shows an empty state when there are no sales in range", async () => {
    mockFetch(
      report({
        summary: {
          revenue: 0,
          sales: 0,
          profit: 0,
          margin_pct: null,
          avg_price: 0,
          avg_days_to_sell: null,
        },
      }),
    );
    render(<AnalyticsPanel shopId={1} canViewProfit={true} />);

    expect(
      await screen.findByText("ยังไม่มียอดขายในช่วงที่เลือก"),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("heading", { name: "ยอดขายตามแรงก์" }),
    ).toBeNull();
  });
});
