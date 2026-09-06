// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { CredentialReveal } from "../features/inventory/CredentialReveal";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
});

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });

function baseProps(over: Partial<Parameters<typeof CredentialReveal>[0]> = {}) {
  return {
    itemId: 7,
    shopId: 1,
    hasCredentials: true,
    twoFactorEnabled: false,
    notify: vi.fn(),
    ...over,
  };
}

describe("CredentialReveal", () => {
  it("renders nothing when the item has no stored credentials", () => {
    const { container } = render(
      <CredentialReveal {...baseProps({ hasCredentials: false })} />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("re-auths on a 428 then shows the revealed password", async () => {
    const user = userEvent.setup();
    let revealCalls = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const url = String(input);
      if (url.endsWith("/sanctum/csrf-cookie")) {
        document.cookie = "XSRF-TOKEN=t; path=/";
        return new Response(null, { status: 204 });
      }
      if (url.includes("/inventory/7/credentials")) {
        revealCalls += 1;
        return revealCalls === 1
          ? json({ code: "SENSITIVE_REAUTH_REQUIRED" }, 428)
          : json({
              data: {
                username: "acc.login",
                password: "s3cret-pw",
                recovery_email: "rescue@x.test",
              },
            });
      }
      if (url.includes("/security/reauth")) {
        return json({ message: "ยืนยันตัวตนแล้ว", valid_for_seconds: 1800 });
      }
      return new Response("{}", { status: 500 });
    });

    render(<CredentialReveal {...baseProps()} />);
    await user.click(screen.getByRole("button", { name: /ดูรหัสผ่าน/ }));

    expect(
      await screen.findByText("ยืนยันตัวตนก่อนดูรหัสผ่าน"),
    ).toBeInTheDocument();
    await user.type(
      screen.getByLabelText("รหัสผ่านบัญชี"),
      "my-password",
    );
    await user.click(screen.getByRole("button", { name: "ยืนยัน" }));

    // revealed panel appears; password is masked until you toggle it
    expect(await screen.findByText("acc.login")).toBeInTheDocument();
    expect(screen.getByText("rescue@x.test")).toBeInTheDocument();
    expect(screen.queryByText("s3cret-pw")).not.toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "แสดง" }));
    expect(screen.getByText("s3cret-pw")).toBeInTheDocument();
    expect(revealCalls).toBe(2);
  });

  it("notifies when the user lacks the reveal permission (403)", async () => {
    const user = userEvent.setup();
    const notify = vi.fn();
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      if (String(input).includes("/inventory/7/credentials")) {
        return json({ message: "คุณไม่มีสิทธิ์ทำรายการนี้" }, 403);
      }
      return new Response("{}", { status: 500 });
    });

    render(<CredentialReveal {...baseProps({ notify })} />);
    await user.click(screen.getByRole("button", { name: /ดูรหัสผ่าน/ }));

    expect(notify).toHaveBeenCalledWith("ไม่มีสิทธิ์ดูข้อมูลลับของไอดี");
  });
});
