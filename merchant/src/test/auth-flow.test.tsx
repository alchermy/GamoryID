// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { AuthScreen } from "../features/auth/auth-pages";

afterEach(cleanup);

describe("merchant registration", () => {
  it("แสดงเงื่อนไขรหัสผ่านให้ตรงกับ backend ก่อนสมัคร", () => {
    render(<AuthScreen mode="register" />);

    const password = screen.getByLabelText("รหัสผ่าน");
    expect(password).toHaveAttribute("minlength", "10");
    expect(
      screen.getByText("ใช้รหัสผ่านอย่างน้อย 10 ตัวอักษร"),
    ).toBeInTheDocument();
  });

  it("แจ้งช่องที่ต้องแก้และโฟกัสช่องแรกก่อนส่งข้อมูล", () => {
    render(<AuthScreen mode="register" />);

    fireEvent.click(
      screen.getByRole("button", { name: "สร้างร้านและเริ่มทดลอง" }),
    );

    expect(screen.getByText("กรอกชื่อของคุณ")).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: /^ชื่อของคุณ/ })).toHaveFocus();
  });
});

describe("merchant login", () => {
  it("แจ้งช่องที่ต้องแก้และโฟกัสอีเมลก่อนส่งข้อมูล", () => {
    render(<AuthScreen mode="login" />);

    fireEvent.click(screen.getByRole("button", { name: "เข้าสู่ระบบ" }));

    expect(screen.getByText("กรอกอีเมลสำหรับเข้าใช้งาน")).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: "อีเมล" })).toHaveFocus();
    expect(screen.getByText("กรอกรหัสผ่าน")).toBeInTheDocument();
  });
});
