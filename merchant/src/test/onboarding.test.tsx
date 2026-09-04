// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { OnboardingPanel } from "../features/onboarding/OnboardingPanel";
import { OnboardingCard } from "../features/onboarding/OnboardingCard";
import type { OnboardingStep } from "../features/onboarding/steps";

afterEach(cleanup);

function steps(): OnboardingStep[] {
  return [
    {
      id: "shop-info",
      title: "กรอกข้อมูลร้านและช่องทางติดต่อ",
      description: "…",
      kind: "required",
      done: true,
      locked: false,
      ctas: [{ label: "ไปกรอกข้อมูลร้าน", action: "settings-info" }],
    },
    {
      id: "inventory",
      title: "เพิ่มไอดีลงคลัง",
      description: "…",
      kind: "required",
      done: false,
      locked: false,
      ctas: [
        { label: "เพิ่มไอดี", action: "add" },
        { label: "นำเข้าจากไฟล์", action: "imports" },
      ],
    },
    {
      id: "branding",
      title: "ใส่โลโก้ร้าน",
      description: "…",
      kind: "recommended",
      done: false,
      locked: false,
      ctas: [{ label: "อัปโหลดโลโก้", action: "branding" }],
    },
    {
      id: "storefront",
      title: "เปิดหน้าร้านสาธารณะ",
      description: "…",
      kind: "recommended",
      done: false,
      locked: true,
      lockedNote: "ใช้ได้ตั้งแต่แพ็ก Starter ขึ้นไป",
      ctas: [{ label: "ดูแพ็กเกจ", action: "billing" }],
    },
    {
      id: "discord",
      title: "เชื่อม Discord ของร้าน",
      description: "…",
      kind: "optional",
      done: false,
      locked: false,
      ctas: [{ label: "ตั้งค่า Discord", action: "discord" }],
    },
    {
      id: "team",
      title: "เพิ่มพนักงานเข้าร้าน",
      description: "…",
      kind: "optional",
      done: false,
      locked: false,
      ctas: [{ label: "จัดการทีม", action: "team" }],
    },
    {
      id: "plan",
      title: "เลือกแพ็กเกจก่อนหมดช่วงทดลอง",
      description: "…",
      kind: "info",
      done: false,
      locked: false,
      ctas: [{ label: "ดูแพ็กเกจ", action: "billing" }],
    },
  ];
}

describe("OnboardingPanel", () => {
  it("renders every step, checks the done ones, and routes CTAs by action", async () => {
    const user = userEvent.setup();
    const onCta = vi.fn();
    const onDismiss = vi.fn();
    render(
      <OnboardingPanel
        steps={steps()}
        dismissed={false}
        onCta={onCta}
        onDismiss={onDismiss}
      />,
    );

    expect(screen.getAllByRole("listitem")).toHaveLength(7);
    expect(screen.getByText("เสร็จแล้ว 1/7 ข้อ")).toBeInTheDocument();

    const doneRow = screen
      .getByRole("heading", { name: "กรอกข้อมูลร้านและช่องทางติดต่อ" })
      .closest("li") as HTMLElement;
    expect(doneRow).toHaveClass("is-done");
    // done rows expose no CTA button
    expect(within(doneRow).queryByRole("button")).toBeNull();

    await user.click(screen.getByRole("button", { name: /เพิ่มไอดี/ }));
    expect(onCta).toHaveBeenCalledWith("add");

    const lockedRow = screen
      .getByRole("heading", { name: "เปิดหน้าร้านสาธารณะ" })
      .closest("li") as HTMLElement;
    expect(lockedRow).toHaveClass("is-locked");
    expect(
      within(lockedRow).getByText("ใช้ได้ตั้งแต่แพ็ก Starter ขึ้นไป"),
    ).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "ซ่อนไกด์นี้" }));
    expect(onDismiss).toHaveBeenCalled();
  });

  it("shows the completion banner once required steps are all done", () => {
    const allRequiredDone = steps().map((step) =>
      step.kind === "required" ? { ...step, done: true } : step,
    );
    render(
      <OnboardingPanel
        steps={allRequiredDone}
        dismissed={false}
        onCta={vi.fn()}
        onDismiss={vi.fn()}
      />,
    );
    expect(screen.getByText("ตั้งค่าครบแล้ว ร้านพร้อมขาย")).toBeInTheDocument();
  });
});

describe("OnboardingCard", () => {
  it("shows progress and the next unlocked steps", async () => {
    const user = userEvent.setup();
    const onOpen = vi.fn();
    const onDismiss = vi.fn();
    render(
      <OnboardingCard steps={steps()} onOpen={onOpen} onDismiss={onDismiss} />,
    );

    expect(screen.getByText("เสร็จแล้ว 1/7 ข้อ")).toBeInTheDocument();
    // locked storefront step is skipped in the "next" list
    expect(screen.queryByText("เปิดหน้าร้านสาธารณะ")).toBeNull();
    expect(screen.getByText("เพิ่มไอดีลงคลัง")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /ดูทั้งหมด/ }));
    expect(onOpen).toHaveBeenCalled();
    await user.click(
      screen.getByRole("button", { name: "ซ่อนการ์ดตั้งค่าร้าน" }),
    );
    expect(onDismiss).toHaveBeenCalled();
  });
});
