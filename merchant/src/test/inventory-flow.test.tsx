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
import { useState } from "react";
import App from "../App";
import {
  InventoryMediaFields,
  InventoryMediaGallery,
} from "../features/inventory/InventoryMediaFields";
import {
  createEmptyMediaDraft,
  type InventoryMediaDraft,
} from "../features/inventory/inventory-media-model";
import { TransactionsPanel } from "../features/transactions/TransactionsPanel";
import { ImportPanel } from "../features/imports/ImportPanel";
import { buildInventoryCopyText } from "../inventory-copy";

afterEach(cleanup);

describe("inventory flow", () => {
  it("แสดงไฟล์ Excel ตัวอย่างและรับเฉพาะ Excel หรือ CSV", async () => {
    render(<ImportPanel shopId={1} onComplete={() => undefined} />);

    expect(
      screen.getByRole("button", { name: "ดาวน์โหลด Excel ตัวอย่าง" }),
    ).toBeInTheDocument();
    const picker = screen.getByLabelText("เลือกไฟล์ Excel หรือ CSV");
    expect(picker).toHaveAttribute("accept", expect.stringContaining(".xlsx"));
    expect(picker).toHaveAttribute("accept", expect.stringContaining(".csv"));

    fireEvent.change(picker, {
      target: {
        files: [
          new File(["not-a-sheet"], "inventory.txt", { type: "text/plain" }),
        ],
      },
    });
    expect(screen.getByRole("alert")).toHaveTextContent(
      "รองรับเฉพาะไฟล์ Excel (.xlsx) หรือ CSV (.csv)",
    );
  });

  it("ค้นหา exact tag และบันทึกขายได้", async () => {
    const user = userEvent.setup();
    render(<App />);

    expect(
      screen.queryByRole("textbox", { name: "ค้นหาไอดี" }),
    ).not.toBeInTheDocument();
    await user.click(
      within(screen.getByRole("complementary", { name: "เมนูหลัก" })).getByRole(
        "button",
        { name: "คลังไอดี" },
      ),
    );
    const search = screen.getByRole("textbox", { name: "ค้นหาไอดี" });
    await user.type(search, "#Q7N2P");
    expect(screen.getAllByText("Vega#TH03").length).toBeGreaterThan(0);
    expect(screen.queryByText("Nova#TH02")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "ปิดการขาย #Q7N2P" }));
    expect(screen.getByText("#Q7N2P · Vega#TH03")).toBeInTheDocument();
    await user.type(
      screen.getByLabelText("ชื่อ-นามสกุลลูกค้า *"),
      "ลูกค้าทดสอบ",
    );
    await user.type(
      screen.getByLabelText("Facebook"),
      "https://facebook.com/customer",
    );
    await user.type(screen.getByLabelText("LINE"), "customer-line");
    await user.type(screen.getByLabelText("เบอร์โทร"), "0812345678");
    await user.click(
      screen.getByRole("checkbox", { name: /มีประกันหลังการขาย/ }),
    );
    await user.type(screen.getByLabelText("วันที่หมดประกัน *"), "2099-12-31");
    await user.type(
      screen.getByLabelText("รายละเอียดเพิ่มเติม"),
      "รับประกันการเข้าใช้งาน",
    );
    await user.click(screen.getByRole("button", { name: "บันทึกการขาย" }));
    expect(
      await screen.findByText("บันทึกขาย #Q7N2P สำเร็จ"),
    ).toBeInTheDocument();
  });

  it("แสดงช่องค้นหาเฉพาะหน้าคลังและวางระหว่างสถิติกับรายการไอดี", async () => {
    const user = userEvent.setup();
    render(<App />);

    expect(
      screen.queryByRole("textbox", { name: "ค้นหาไอดี" }),
    ).not.toBeInTheDocument();
    await user.click(
      within(screen.getByRole("complementary", { name: "เมนูหลัก" })).getByRole(
        "button",
        { name: "คลังไอดี" },
      ),
    );

    const stats = screen.getByRole("region", { name: "ตัวเลขภาพรวม" });
    const search = screen.getByRole("search", { name: "ค้นหาในคลังไอดี" });
    const latest = screen
      .getByRole("heading", { name: "ไอดีล่าสุด" })
      .closest(".panel");
    expect(
      stats.compareDocumentPosition(search) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
    expect(
      search.compareDocumentPosition(latest!) &
        Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();

    await user.type(
      screen.getByRole("textbox", { name: "ค้นหาไอดี" }),
      "#23DX5",
    );
    const clear = screen.getByRole("button", { name: "ล้างคำค้น" });
    await user.click(clear);
    expect(screen.getByRole("textbox", { name: "ค้นหาไอดี" })).toHaveFocus();
  });

  it("เปลี่ยนสถานะจากตารางและเปิดฟอร์มขายเมื่อเลือกขายแล้ว", async () => {
    const user = userEvent.setup();
    render(<App />);

    const status = screen.getAllByLabelText("เปลี่ยนสถานะ #23DX5")[0];
    await user.selectOptions(status, "reserved");
    expect(await screen.findByText("จอง #23DX5 แล้ว")).toBeInTheDocument();
    expect(status).toHaveValue("reserved");

    await user.selectOptions(status, "sold");
    expect(
      screen.getByRole("dialog", { name: "ปิดการขาย #23DX5" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "บันทึกการขาย" }),
    ).toBeInTheDocument();
  });

  it("เปิดฟอร์มเพิ่มไอดีและแสดงข้อมูลใหม่ในคลัง", async () => {
    const user = userEvent.setup();
    render(<App />);
    await user.click(screen.getByRole("button", { name: "เพิ่มไอดี" }));
    await user.type(screen.getByLabelText("Riot ID"), "Test#TH99");
    await user.type(screen.getByLabelText("Username"), "test.user99");
    await user.type(screen.getByLabelText("ต้นทุน"), "1000");
    await user.type(screen.getByLabelText("ราคาตั้งขาย"), "1500");
    await user.click(screen.getByRole("button", { name: "เพิ่มเข้าคลัง" }));
    expect((await screen.findAllByText("Test#TH99")).length).toBeGreaterThan(0);
  });

  it("ยืนยันก่อนเก็บไอดีถาวร", async () => {
    const user = userEvent.setup();
    render(<App />);

    await user.click(
      screen.getByRole("button", { name: "ดูรายละเอียด #23DX5" }),
    );
    await user.click(screen.getByRole("button", { name: "เก็บรายการถาวร" }));

    const dialog = screen.getByRole("alertdialog");
    expect(within(dialog).getByText("เก็บรายการถาวร")).toBeInTheDocument();
    await user.click(within(dialog).getByRole("button", { name: "เก็บถาวร" }));
    expect(await screen.findByText("เก็บ #23DX5 ถาวรแล้ว")).toBeInTheDocument();
  });

  it("เปิดหน้ารายละเอียดและแก้ไขข้อมูลไอดีได้", async () => {
    const user = userEvent.setup();
    render(<App />);

    await user.click(
      screen.getByRole("button", { name: "ดูรายละเอียด #23DX5" }),
    );
    expect(
      screen.getByRole("heading", { level: 2, name: "Gammy#TH01" }),
    ).toBeInTheDocument();
    expect(screen.getByText("ไอดีนี้ยังไม่มีรูปภาพ")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "แก้ไขข้อมูล" }));
    const rank = screen.getByLabelText("แรงก์");
    await user.clear(rank);
    await user.type(rank, "Immortal 2");
    await user.click(screen.getByRole("button", { name: "บันทึกการแก้ไข" }));

    expect(
      await screen.findByText("บันทึกข้อมูล #23DX5 แล้ว"),
    ).toBeInTheDocument();
    expect(screen.getAllByText("Immortal 2").length).toBeGreaterThan(0);
  });

  it("เพิ่ม แก้ไข และล้างโน้ตช่วยจำภายในร้านได้", async () => {
    const user = userEvent.setup();
    render(<App />);

    await user.click(
      screen.getByRole("button", { name: "เพิ่มโน้ตช่วยจำ #23DX5" }),
    );
    const dialog = screen.getByRole("dialog", {
      name: "โน้ตช่วยจำ #23DX5",
    });
    const noteInput = within(dialog).getByLabelText("โน้ตภายในร้าน");
    await user.type(noteInput, "คุณเอกจองถึง 18:00 น. รอตัดสินใจ");
    await user.click(
      within(dialog).getByRole("button", { name: "บันทึกโน้ต" }),
    );

    expect(
      await screen.findByText("บันทึกโน้ต #23DX5 แล้ว"),
    ).toBeInTheDocument();
    expect(
      screen.getAllByText("คุณเอกจองถึง 18:00 น. รอตัดสินใจ").length,
    ).toBeGreaterThan(0);

    await user.click(
      screen.getByRole("button", { name: "แก้ไขโน้ตช่วยจำ #23DX5" }),
    );
    const editDialog = screen.getByRole("dialog", {
      name: "โน้ตช่วยจำ #23DX5",
    });
    await user.click(
      within(editDialog).getByRole("button", { name: "ล้างโน้ต" }),
    );
    await user.click(
      within(editDialog).getByRole("button", { name: "บันทึกโน้ต" }),
    );
    expect(await screen.findByText("ล้างโน้ต #23DX5 แล้ว")).toBeInTheDocument();
  });

  it("จำกัดรูป Display 1 รูปและรูปภาพรายละเอียดสูงสุด 4 รูป", async () => {
    const user = userEvent.setup();
    Object.defineProperty(URL, "createObjectURL", {
      configurable: true,
      value: vi.fn(() => "blob:inventory-preview"),
    });
    Object.defineProperty(URL, "revokeObjectURL", {
      configurable: true,
      value: vi.fn(),
    });

    function MediaHarness() {
      const [draft, setDraft] = useState<InventoryMediaDraft>(
        createEmptyMediaDraft,
      );
      return <InventoryMediaFields value={draft} onChange={setDraft} />;
    }

    render(<MediaHarness />);
    await user.upload(
      screen.getByLabelText(/เลือกรูป Display/),
      new File(["display"], "display.png", { type: "image/png" }),
    );
    expect(
      screen.getByAltText("ตัวอย่างรูป Display ที่เลือก"),
    ).toBeInTheDocument();

    const detailFiles = Array.from(
      { length: 5 },
      (_, index) =>
        new File([`detail-${index}`], `detail-${index}.png`, {
          type: "image/png",
        }),
    );
    await user.upload(screen.getByLabelText("เพิ่มรูป"), detailFiles);
    expect(screen.getByRole("alert")).toHaveTextContent(
      "เพิ่มได้อีกสูงสุด 4 รูป",
    );
  });

  it("เลือกรูปในแกลเลอรีหน้ารายละเอียดได้", async () => {
    const user = userEvent.setup();
    render(
      <InventoryMediaGallery
        itemTag="#23DX5"
        media={[
          {
            id: 1,
            role: "display",
            originalName: "display.png",
            mimeType: "image/png",
            sizeBytes: 1200,
            sortOrder: 0,
            url: "/display.png",
          },
          {
            id: 2,
            role: "detail",
            originalName: "detail.png",
            mimeType: "image/png",
            sizeBytes: 1200,
            sortOrder: 1,
            url: "/detail.png",
          },
        ]}
      />,
    );

    await user.click(
      screen.getByRole("button", { name: "ดูรูปภาพรายละเอียด 1" }),
    );
    expect(
      screen.getByRole("heading", { name: "รูปภาพรายละเอียด" }),
    ).toBeInTheDocument();
    expect(screen.getByAltText("รูปภาพรายละเอียด ของ #23DX5")).toHaveAttribute(
      "src",
      "/detail.png",
    );
  });

  it("คัดลอกรายละเอียดสำหรับส่งลูกค้าโดยไม่รวมข้อมูลเข้าสู่ระบบ", async () => {
    const user = userEvent.setup();
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "clipboard", {
      configurable: true,
      value: { writeText },
    });
    render(<App />);

    await user.click(
      screen.getByRole("button", { name: "คัดลอกรายละเอียด #23DX5" }),
    );
    expect(writeText).toHaveBeenCalledOnce();
    const copied = String(writeText.mock.calls[0][0]);
    expect(copied).toContain("#23DX5\n\nRiotID=Gammy#TH01");
    expect(copied).toContain("Rank=Ascendant 2");
    expect(copied).toContain("Level=238");
    expect(copied).toContain("ราคา=8,900 บาท");
    expect(copied).toContain("สนใจรายละเอียดเพิ่มเติม");
    expect(copied).not.toContain("gammy.ops01");
    expect(copied).not.toContain("Password");
    expect(copied).not.toContain("โน้ตช่วยจำ");
  });

  it("จัดรูปแบบข้อความคัดลอกตามแม่แบบร้าน", () => {
    const text = buildInventoryCopyText(
      {
        tag: "#8KM4R",
        title: "Prime",
        riotId: "Nova#TH02",
        rank: "Diamond 3",
        level: 191,
        price: 6900,
        description: "Prime Collection",
      },
      "ติดต่อ LINE @gamory",
    );
    expect(text).toBe(
      "#8KM4R\n\nRiotID=Nova#TH02\n\nRank=Diamond 3\n\nLevel=191\n\nรายละเอียด=Prime Collection\n\nราคา=6,900 บาท\n\nติดต่อ LINE @gamory",
    );
    expect(text).not.toContain("Username");
  });
});

describe("merchant transaction history", () => {
  it("แยกประวัติบริการและการเติมเครดิตพร้อมสถานะภาษาไทย", () => {
    render(
      <TransactionsPanel
        history={{
          subscriptions: {
            total: 1,
            items: [
              {
                id: 1,
                status: "active",
                starts_at: "2026-08-01T09:00:00+07:00",
                ends_at: "2026-08-31T09:00:00+07:00",
                created_at: "2026-08-01T09:00:00+07:00",
                auto_renew: true,
                plan: {
                  name: "Growth",
                  code: "growth",
                  price_thb: 699,
                  duration_days: 30,
                },
              },
            ],
          },
          top_ups: {
            total: 1,
            items: [
              {
                id: 8,
                status: "pending_review",
                credits: 1000,
                amount: 1000,
                created_at: "2026-08-27T14:34:00+07:00",
                verified_at: null,
                review_note: null,
                submitted_by: { name: "พีท เจ้าของร้าน" },
              },
            ],
          },
        }}
        loading={false}
        error=""
        retry={() => undefined}
      />,
    );

    expect(
      screen.getByRole("heading", { name: "ประวัติการสมัครบริการ" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "ประวัติการเติมเครดิต" }),
    ).toBeInTheDocument();
    expect(screen.getAllByText("Growth").length).toBeGreaterThan(0);
    expect(screen.getAllByText("ใช้งานอยู่").length).toBeGreaterThan(0);
    expect(screen.getAllByText("รอแอดมินตรวจ").length).toBeGreaterThan(0);
    expect(screen.getAllByText("1,000").length).toBeGreaterThan(0);
  });
});
