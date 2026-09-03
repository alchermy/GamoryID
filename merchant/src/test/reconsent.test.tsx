// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReconsentScreen } from "../features/auth/auth-pages";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
});

describe("ReconsentScreen", () => {
  it("บล็อกปุ่มยอมรับจนกว่าจะติ๊กช่องยินยอม แล้วเรียก /terms/accept", async () => {
    const user = userEvent.setup();
    const onAccepted = vi.fn();
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ user: { terms_current: true } }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );

    render(<ReconsentScreen onAccepted={onAccepted} />);

    expect(
      screen.getByRole("heading", { name: "ข้อกำหนดการใช้บริการมีการปรับปรุง" }),
    ).toBeInTheDocument();
    expect(screen.getAllByRole("link")).toHaveLength(2);

    const acceptButton = screen.getByRole("button", {
      name: "ยอมรับและใช้งานต่อ",
    });
    expect(acceptButton).toBeDisabled();

    await user.click(screen.getByRole("checkbox"));
    expect(acceptButton).toBeEnabled();

    await user.click(acceptButton);

    const call = fetchMock.mock.calls.find(([input]) =>
      String(input).endsWith("/api/v1/terms/accept"),
    );
    expect(call).toBeDefined();
    expect(call?.[1]).toMatchObject({ method: "POST", credentials: "include" });
    expect(onAccepted).toHaveBeenCalledTimes(1);
  });
});
