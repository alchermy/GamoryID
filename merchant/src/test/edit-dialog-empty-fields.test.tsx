// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { EditDialog } from "../features/inventory/inventory-components";
import type { InventoryItem } from "../types/models";

afterEach(cleanup);

function item(over: Partial<InventoryItem> = {}): InventoryItem {
  return {
    id: 1,
    tag: "#TEST1",
    title: "Test#TH01",
    riotId: "Test#TH01",
    // mapInventoryItem substitutes "–" for a null rank/username so list and
    // detail views have something to show — that sentinel must not leak
    // into this form's starting value.
    username: "–",
    email: "–",
    rank: "–",
    level: 1,
    skins: 0,
    cost: 100,
    price: 200,
    status: "available",
    updated: "เมื่อสักครู่",
    media: [],
    ...over,
  };
}

describe("EditDialog", () => {
  it("starts rank/username empty instead of the literal \"–\" placeholder", () => {
    render(
      <EditDialog
        item={item()}
        close={vi.fn()}
        submit={vi.fn()}
        busy={false}
      />,
    );

    expect(screen.getByLabelText("Username")).toHaveValue("");
    expect(screen.getByLabelText("Email")).toHaveValue("");
    expect(screen.getByLabelText("แรงก์")).toHaveValue("");
  });

  it("keeps a real rank/username value as-is", () => {
    render(
      <EditDialog
        item={item({
          username: "gammy.ops01",
          email: "gammy.account@mail.test",
          rank: "Diamond 3",
        })}
        close={vi.fn()}
        submit={vi.fn()}
        busy={false}
      />,
    );

    expect(screen.getByLabelText("Username")).toHaveValue("gammy.ops01");
    expect(screen.getByLabelText("Email")).toHaveValue("gammy.account@mail.test");
    expect(screen.getByLabelText("แรงก์")).toHaveValue("Diamond 3");
  });
});
