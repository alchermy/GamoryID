// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { afterEach, describe, expect, it, vi } from "vitest";
import { SalesPanel } from "../features/history/history-panels";
import { SaleDetailPage } from "../features/sales/SaleDetailPage";
import * as salesApi from "../features/sales/sales-api";
import type { SaleRecord } from "../types/models";

vi.mock("../features/sales/sales-api", () => ({
  loadSaleDetail: vi.fn(),
}));

const sale: SaleRecord = {
  id: 41,
  sold_price: "13900.00",
  cost_snapshot: "7000.00",
  profit: "6900.00",
  sold_at: "2026-08-31T16:28:00+07:00",
  has_warranty: true,
  warranty_ends_at: "2026-09-07",
  notes: "นัดส่งมอบหลังตรวจสอบยอด",
  inventory_item: {
    id: 7,
    tag: "Q7N2P",
    title: "Vega#TH03",
    riot_id: "Vega#TH03",
    rank: "Immortal 1",
    level: 201,
    list_price: "13900.00",
  },
  customer: {
    id: 9,
    name: "คุณทดสอบ",
    phone: "0812345678",
    line_id: "test-line",
    facebook_url: "https://facebook.com/customer",
  },
  creator: { id: 3, name: "พีท เจ้าของร้าน" },
};

afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

describe("sale detail", () => {
  it("เปิดรายละเอียดจากตารางขายด้วยลิงก์ที่แชร์และเปิดแท็บใหม่ได้", () => {
    render(
      <MemoryRouter>
        <SalesPanel
          records={[sale]}
          loading={false}
          error=""
          retry={() => undefined}
          canViewProfit={true}
        />
      </MemoryRouter>,
    );

    expect(
      screen.getByRole("link", { name: "ดูรายละเอียดการขาย #Q7N2P" }),
    ).toHaveAttribute("href", "/sales/41");
    expect(screen.getByText("฿6,900")).toBeInTheDocument();
  });

  it("แสดงลูกค้า ผู้ขาย ประกัน และลิงก์กลับไปยังข้อมูลไอดี", async () => {
    vi.mocked(salesApi.loadSaleDetail).mockResolvedValue(sale);

    render(
      <MemoryRouter>
        <SaleDetailPage shopId={1} saleId={41} canViewProfit={true} />
      </MemoryRouter>,
    );

    expect(
      await screen.findByRole("heading", { name: "#Q7N2P" }),
    ).toBeInTheDocument();
    expect(screen.getByText("คุณทดสอบ")).toBeInTheDocument();
    expect(screen.getByText("พีท เจ้าของร้าน")).toBeInTheDocument();
    expect(screen.getByText(/7 กันยายน/)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "ดูข้อมูลไอดี" })).toHaveAttribute(
      "href",
      "/inventory?item=7",
    );
  });

  it("ไม่แสดงต้นทุนและกำไรเมื่อสมาชิกไม่มีสิทธิ์", async () => {
    vi.mocked(salesApi.loadSaleDetail).mockResolvedValue({
      ...sale,
      cost_snapshot: null,
      profit: null,
    });

    render(
      <MemoryRouter>
        <SaleDetailPage shopId={1} saleId={41} canViewProfit={false} />
      </MemoryRouter>,
    );

    await screen.findByRole("heading", { name: "#Q7N2P" });
    expect(screen.queryByText("ต้นทุน")).not.toBeInTheDocument();
    expect(screen.queryByText("กำไร")).not.toBeInTheDocument();
  });
});
