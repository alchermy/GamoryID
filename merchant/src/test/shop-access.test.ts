import { describe, expect, it } from "vitest";
import {
  shopAccessMode,
  shopAccessNotice,
} from "../shared/lib/shop-access";

describe("shopAccessMode", () => {
  it("treats trialing / active / unknown as full access", () => {
    expect(shopAccessMode("trialing")).toBe("full");
    expect(shopAccessMode("active")).toBe("full");
    expect(shopAccessMode(undefined)).toBe("full");
    expect(shopAccessMode(null)).toBe("full");
  });

  it("maps a lapsed plan to read-only", () => {
    expect(shopAccessMode("grace_read_only")).toBe("readonly");
    expect(shopAccessMode("expired")).toBe("readonly");
    expect(shopAccessMode("pending_payment")).toBe("readonly");
  });

  it("maps an admin suspension to suspended", () => {
    expect(shopAccessMode("suspended")).toBe("suspended");
    expect(shopAccessMode("cancelled")).toBe("suspended");
  });

  it("gives a contact-us notice for suspended and a renew notice for read-only", () => {
    expect(shopAccessNotice("full")).toBeNull();
    expect(shopAccessNotice("suspended")?.body).toContain("ติดต่อทีมงาน");
    expect(shopAccessNotice("readonly")?.body).toContain("ต่ออายุ");
  });
});
