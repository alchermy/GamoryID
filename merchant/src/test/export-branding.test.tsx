// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ExportDialog } from "../features/merchant/ExportDialog";
import { SettingsPanel } from "../features/settings/SettingsPanel";
import type { ShopDetails } from "../types/models";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
});

const shop = {
  id: 1,
  name: "ร้านทดสอบ",
  slug: "test-shop",
  status: "active",
  role: "owner",
  permissions: [],
  trial_ends_at: null,
  grace_ends_at: null,
  credit_balance: 0,
  subscription: null,
} as unknown as ShopDetails;

describe("ExportDialog", () => {
  it("ดาวน์โหลดคลังไอดีผ่าน endpoint ที่ถูกต้อง", async () => {
    const user = userEvent.setup();
    const close = vi.fn();
    const notify = vi.fn();
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response("tag\n#AAAAA\n", {
        status: 200,
        headers: { "Content-Type": "text/csv" },
      }),
    );
    // jsdom has no real anchor download; stub it out
    vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => {});

    render(
      <ExportDialog
        shopId={1}
        shopSlug="test-shop"
        canSalesExport={false}
        notify={notify}
        close={close}
      />,
    );

    // sales option is locked without the feature
    expect(
      screen.getByRole("radio", { name: /รายการขาย/ }),
    ).toBeDisabled();

    await user.click(screen.getByRole("button", { name: /ดาวน์โหลด CSV/ }));

    const url = String(fetchMock.mock.calls[0][0]);
    expect(url).toContain("/export/inventory.csv");
    expect(close).toHaveBeenCalled();
  });

  it("เปิดตัวเลือกช่วงวันที่เมื่อมีสิทธิ์ advanced_export", async () => {
    const user = userEvent.setup();
    render(
      <ExportDialog
        shopId={1}
        shopSlug="test-shop"
        canSalesExport
        notify={vi.fn()}
        close={vi.fn()}
      />,
    );

    await user.click(screen.getByRole("radio", { name: /รายการขาย/ }));
    expect(screen.getByLabelText("ตั้งแต่")).toBeInTheDocument();
    expect(screen.getByLabelText("ถึง")).toBeInTheDocument();
  });
});

describe("SettingsPanel branding", () => {
  it("แสดงส่วนแบรนด์ร้านและเรียก callback เมื่อเลือกไฟล์", async () => {
    const user = userEvent.setup();
    const onUploadBranding = vi.fn();
    const onRemoveBranding = vi.fn();

    render(
      <SettingsPanel
        shop={shop}
        loading={false}
        error=""
        canUseStorefront
        logoUrl="https://cdn.example/logo.png"
        bannerUrl={null}
        onSubmit={vi.fn()}
        onUploadBranding={onUploadBranding}
        onRemoveBranding={onRemoveBranding}
        onSignOut={vi.fn()}
        retry={vi.fn()}
      />,
    );

    expect(
      screen.getByRole("heading", { name: "แบรนด์ร้าน" }),
    ).toBeInTheDocument();

    // logo has a remove button because a logoUrl was supplied
    await user.click(screen.getAllByRole("button", { name: /เอาออก/ })[0]);
    expect(onRemoveBranding).toHaveBeenCalledWith("logo");

    const file = new File(["x"], "logo.png", { type: "image/png" });
    const inputs = document.querySelectorAll('input[type="file"]');
    await user.upload(inputs[0] as HTMLInputElement, file);
    expect(onUploadBranding).toHaveBeenCalledWith("logo", file);
  });

  it("ปุ่มออกจากระบบเรียก onSignOut", async () => {
    const user = userEvent.setup();
    const onSignOut = vi.fn();
    render(
      <SettingsPanel
        shop={shop}
        loading={false}
        error=""
        canUseStorefront
        logoUrl={null}
        bannerUrl={null}
        onSubmit={vi.fn()}
        onUploadBranding={vi.fn()}
        onRemoveBranding={vi.fn()}
        onSignOut={onSignOut}
        retry={vi.fn()}
      />,
    );

    await user.click(screen.getByRole("button", { name: "ออกจากระบบ" }));
    expect(onSignOut).toHaveBeenCalled();
  });
});
