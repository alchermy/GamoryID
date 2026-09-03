// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { BillingPanel, TopUpDialog } from "../features/billing/billing-components";
import type {
  Plan,
  PlanFeatureKey,
  PlanFeatures,
  ShopDetails,
} from "../types/models";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const FEATURE_KEYS: PlanFeatureKey[] = [
  "storefront",
  "bulk_import",
  "advanced_export",
  "activity_log",
  "discord",
  "analytics",
  "early_access",
  "priority_support",
];

function feats(...on: PlanFeatureKey[]): PlanFeatures {
  return Object.fromEntries(
    FEATURE_KEYS.map((k) => [k, on.includes(k)]),
  ) as PlanFeatures;
}

const PLANS: Plan[] = [
  {
    id: 1,
    code: "free",
    name: "Free Trial",
    sort_order: 0,
    is_free: true,
    active_inventory_limit: 10,
    member_limit: 1,
    price_monthly: 0,
    price_yearly: null,
    sale_price_monthly: null,
    sale_price_yearly: null,
    sale_label: null,
    sale_ends_at: null,
    monthly_days: 30,
    yearly_days: 365,
    features: feats(),
  },
  {
    id: 2,
    code: "starter",
    name: "Starter",
    sort_order: 1,
    is_free: false,
    active_inventory_limit: 50,
    member_limit: 2,
    price_monthly: 250,
    price_yearly: 2500,
    sale_price_monthly: 199,
    sale_price_yearly: 1990,
    sale_label: "โปรเปิดตัว",
    sale_ends_at: null,
    monthly_days: 30,
    yearly_days: 365,
    features: feats("storefront", "bulk_import", "activity_log", "discord"),
  },
  {
    id: 3,
    code: "growth",
    name: "Growth",
    sort_order: 2,
    is_free: false,
    active_inventory_limit: 250,
    member_limit: 4,
    price_monthly: 600,
    price_yearly: 6000,
    sale_price_monthly: 490,
    sale_price_yearly: 4900,
    sale_label: "โปรเปิดตัว",
    sale_ends_at: null,
    monthly_days: 30,
    yearly_days: 365,
    features: feats(
      "storefront",
      "bulk_import",
      "activity_log",
      "advanced_export",
      "discord",
      "analytics",
      "early_access",
    ),
  },
  {
    id: 4,
    code: "pro",
    name: "Pro",
    sort_order: 3,
    is_free: false,
    active_inventory_limit: 500,
    member_limit: null,
    price_monthly: 1190,
    price_yearly: 11900,
    sale_price_monthly: 890,
    sale_price_yearly: 8900,
    sale_label: "โปรเปิดตัว",
    sale_ends_at: null,
    monthly_days: 30,
    yearly_days: 365,
    features: feats(
      "storefront",
      "bulk_import",
      "activity_log",
      "advanced_export",
      "discord",
      "analytics",
      "early_access",
      "priority_support",
    ),
  },
];

function shopOnPro(overrides: Partial<ShopDetails> = {}): ShopDetails {
  return {
    id: 1,
    name: "Nexus Store",
    slug: "nexus-store",
    status: "active",
    role: "owner",
    permissions: [],
    trial_ends_at: null,
    grace_ends_at: null,
    credit_balance: 1610,
    subscription: {
      status: "active",
      auto_renew: false,
      ends_at: "2026-10-03T00:00:00Z",
      billing_cycle: "monthly",
      plan: {
        name: "Pro",
        code: "pro",
        active_inventory_limit: 500,
        member_limit: null,
      },
    },
    entitlements: {
      status: "active",
      trial_ends_at: null,
      writable: true,
      billing_cycle: "monthly",
      effective_plan: {
        code: "pro",
        name: "Pro",
        is_free: false,
        active_inventory_limit: 500,
        member_limit: null,
        features: PLANS[3].features,
      },
      usage: { inventory_active: 7, members: 1 },
    },
    ...overrides,
  } as unknown as ShopDetails;
}

const noop = () => {};

describe("BillingPanel", () => {
  it("shows only the current plan by default, reveals the grid on 'เปลี่ยนแพ็กเกจ'", async () => {
    const user = userEvent.setup();
    render(
      <BillingPanel
        plans={PLANS}
        shop={shopOnPro()}
        loading={false}
        error=""
        canManage
        busy={false}
        onOpenTopUp={noop}
        onPurchase={noop}
        onAutoRenewChange={noop}
        retry={noop}
      />,
    );

    // current-plan card is visible…
    const currentCard = screen
      .getByRole("heading", { name: /Pro/ })
      .closest(".current-plan-card") as HTMLElement;
    expect(currentCard).toBeInTheDocument();
    expect(within(currentCard).getByText("ใช้งาน")).toBeInTheDocument();
    // …but the selectable plan grid is not
    expect(screen.queryByLabelText("เลือกแพ็กเกจ")).not.toBeInTheDocument();
    expect(screen.queryByText("แนะนำ")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /เปลี่ยนแพ็กเกจ/ }));

    const grid = screen.getByLabelText("เลือกแพ็กเกจ");
    expect(within(grid).getByRole("heading", { name: "Free Trial" })).toBeInTheDocument();
    expect(within(grid).getByRole("heading", { name: "Starter" })).toBeInTheDocument();
    expect(within(grid).getByText("แนะนำ")).toBeInTheDocument(); // growth flag
    expect(within(grid).getByText("ใช้อยู่")).toBeInTheDocument(); // pro badge
    // no comparison "—" rows anywhere in the grid
    expect(within(grid).queryByText("—", { exact: false })).not.toBeInTheDocument();
  });

  it("surfaces a pending top-up review banner", () => {
    render(
      <BillingPanel
        plans={PLANS}
        shop={shopOnPro({
          latest_top_up: {
            id: 9,
            status: "pending_review",
            credits: 2500,
            verified_at: null,
            review_note: null,
          },
        })}
        loading={false}
        error=""
        canManage
        busy={false}
        onOpenTopUp={noop}
        onPurchase={noop}
        onAutoRenewChange={noop}
        retry={noop}
      />,
    );
    expect(
      screen.getByText(/กำลังตรวจสลิปเติมเครดิต 2,500/),
    ).toBeInTheDocument();
  });
});

describe("TopUpDialog", () => {
  it("shows the pending-review thank-you state after a successful submit", async () => {
    const user = userEvent.setup();
    const close = vi.fn();
    const submit = vi.fn().mockResolvedValue(true);

    render(<TopUpDialog busy={false} close={close} submit={submit} />);

    await user.type(
      screen.getByRole("spinbutton", { name: /จำนวนเครดิต/ }),
      "500",
    );
    const file = new File(["x"], "slip.png", { type: "image/png" });
    await user.upload(
      document.querySelector('input[type="file"]') as HTMLInputElement,
      file,
    );
    await user.click(
      screen.getByRole("button", { name: "ส่งสลิปเติมเครดิต" }),
    );

    expect(submit).toHaveBeenCalledWith(500, file);
    const done = (
      await screen.findByRole("heading", { name: "ส่งสลิปเรียบร้อยแล้ว" })
    ).closest(".topup-done") as HTMLElement;
    expect(within(done).getByText(/แอดมิน|ตรวจสอบสลิป|อนุมัติ/)).toBeInTheDocument();

    await user.click(within(done).getByRole("button", { name: "ปิด" }));
    expect(close).toHaveBeenCalled();
  });

  it("stays on the form when submit fails", async () => {
    const user = userEvent.setup();
    const submit = vi.fn().mockResolvedValue(false);

    render(<TopUpDialog busy={false} close={vi.fn()} submit={submit} />);

    await user.type(
      screen.getByRole("spinbutton", { name: /จำนวนเครดิต/ }),
      "500",
    );
    await user.upload(
      document.querySelector('input[type="file"]') as HTMLInputElement,
      new File(["x"], "slip.png", { type: "image/png" }),
    );
    await user.click(
      screen.getByRole("button", { name: "ส่งสลิปเติมเครดิต" }),
    );

    expect(
      screen.queryByRole("heading", { name: "ส่งสลิปเรียบร้อยแล้ว" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "ส่งสลิปเติมเครดิต" }),
    ).toBeInTheDocument();
  });
});
