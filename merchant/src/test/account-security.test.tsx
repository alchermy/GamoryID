// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AccountSecurityPanel } from "../features/account/AccountSecurityPanel";
import type { SessionUser } from "../types/models";

vi.mock("qrcode", () => ({
  default: { toDataURL: vi.fn().mockResolvedValue("data:image/png;base64,QQ==") },
}));

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
});

const session = {
  email: "owner@example.test",
  two_factor_enabled: false,
} as SessionUser;

function mockApi(handlers: Record<string, () => Response>) {
  return vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
    const url = String(input);
    if (url.endsWith("/sanctum/csrf-cookie")) {
      document.cookie = "XSRF-TOKEN=test-token; path=/";
      return new Response(null, { status: 204 });
    }
    const key = Object.keys(handlers).find((k) => url.includes(k));
    return key
      ? handlers[key]()
      : new Response(JSON.stringify({ message: "unexpected" }), { status: 500 });
  });
}

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });

describe("AccountSecurityPanel", () => {
  it("walks through enabling 2FA: password -> QR -> code", async () => {
    const user = userEvent.setup();
    const notify = vi.fn();
    mockApi({
      "/security/2fa/begin": () =>
        json({ secret: "ABCDEF234567", otpauth_uri: "otpauth://totp/x" }),
      "/security/2fa/confirm": () =>
        json({
          message: "เปิดใช้ 2FA แล้ว",
          recovery_codes: ["aaaaa-11111", "bbbbb-22222"],
        }),
    });

    render(<AccountSecurityPanel session={session} notify={notify} />);
    expect(screen.getByText("ปิดอยู่")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /เปิด 2FA/ }));
    await user.type(
      screen.getByLabelText("ยืนยันรหัสผ่านบัญชี"),
      "my-password",
    );
    await user.click(screen.getByRole("button", { name: "ต่อไป" }));

    expect(await screen.findByAltText("QR สำหรับตั้งค่า 2FA")).toBeInTheDocument();
    await user.type(screen.getByLabelText("กรอกรหัส 6 หลักจากแอป"), "123456");
    await user.click(
      screen.getByRole("button", { name: "ยืนยันและเปิดใช้" }),
    );

    // recovery codes are shown once before 2FA is really "on"
    expect(await screen.findByText("aaaaa-11111")).toBeInTheDocument();
    await user.click(
      screen.getByRole("button", { name: /บันทึกรหัสสำรองแล้ว/ }),
    );

    expect(await screen.findByText("เปิดอยู่")).toBeInTheDocument();
    expect(notify).toHaveBeenCalledWith("เปิด 2FA แล้ว");
  });

  it("shows the error from a wrong password at the begin step", async () => {
    const user = userEvent.setup();
    mockApi({
      "/security/2fa/begin": () =>
        json({ message: "รหัสผ่านไม่ถูกต้อง" }, 422),
    });

    render(<AccountSecurityPanel session={session} notify={vi.fn()} />);
    await user.click(screen.getByRole("button", { name: /เปิด 2FA/ }));
    await user.type(screen.getByLabelText("ยืนยันรหัสผ่านบัญชี"), "nope");
    await user.click(screen.getByRole("button", { name: "ต่อไป" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "รหัสผ่านไม่ถูกต้อง",
    );
  });

  it("offers to disable when already enabled", () => {
    render(
      <AccountSecurityPanel
        session={{ ...session, two_factor_enabled: true }}
        notify={vi.fn()}
      />,
    );
    expect(screen.getByText("เปิดอยู่")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /ปิด 2FA/ }),
    ).toBeInTheDocument();
  });
});
