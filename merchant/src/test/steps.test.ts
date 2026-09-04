import { describe, expect, it } from "vitest";
import { buildOnboardingSteps } from "../features/onboarding/steps";
import type { OnboardingContext } from "../features/onboarding/steps";
import type {
  DashboardData,
  PlanFeatures,
  ShopDetails,
} from "../types/models";

function features(over: Partial<PlanFeatures> = {}): PlanFeatures {
  return {
    storefront: true,
    bulk_import: true,
    advanced_export: true,
    activity_log: true,
    discord: true,
    analytics: true,
    early_access: true,
    priority_support: true,
    ...over,
  };
}

function shop(over: Partial<ShopDetails> = {}): ShopDetails {
  return {
    id: 1,
    name: "ร้านทดสอบ",
    slug: "test",
    status: "trialing",
    role: "owner",
    permissions: [],
    description: null,
    line_url: null,
    facebook_url: null,
    phone: null,
    trial_ends_at: new Date(Date.now() + 5 * 86_400_000).toISOString(),
    grace_ends_at: null,
    credit_balance: 0,
    storefront_enabled: false,
    onboarding_dismissed_at: null,
    logo_url: null,
    banner_url: null,
    subscription: null,
    entitlements: {
      status: "trialing",
      trial_ends_at: null,
      writable: true,
      effective_plan: {
        code: "growth",
        name: "Growth",
        is_free: false,
        active_inventory_limit: 1000,
        member_limit: 5,
        features: features(),
      },
      usage: { inventory_active: 0, members: 1 },
    },
    latest_top_up: null,
    ...over,
  } as unknown as ShopDetails;
}

function dash(over: Partial<DashboardData["summary"]> = {}): DashboardData {
  return {
    summary: {
      available: 0,
      reserved: 0,
      sold_this_month: 0,
      sold_total: 0,
      inventory_value: null,
      revenue_this_month: 0,
      profit_this_month: null,
      storefront_views: null,
      ...over,
    },
    activity: [],
    sales_last_7_days: [],
    subscription: {} as unknown as DashboardData["subscription"],
  } as DashboardData;
}

function byId(ctx: OnboardingContext) {
  return Object.fromEntries(
    buildOnboardingSteps(ctx).map((step) => [step.id, step]),
  );
}

describe("buildOnboardingSteps", () => {
  it("marks the required steps unfinished for a brand-new shop", () => {
    const steps = byId({
      shopDetails: shop(),
      dashboard: dash(),
      discordConnected: false,
    });

    expect(steps["shop-info"].kind).toBe("required");
    expect(steps["shop-info"].done).toBe(false);
    expect(steps["inventory"].done).toBe(false);
    expect(steps["branding"].done).toBe(false);
    expect(steps["storefront"].locked).toBe(false);
    expect(steps["plan"].done).toBe(false);
    expect(steps["plan"].description).toContain("เหลือเวลาทดลอง");
  });

  it("marks steps done once the shop is configured", () => {
    const steps = byId({
      shopDetails: shop({
        description: "ร้านไอดีคุณภาพ",
        line_url: "https://line.me/ti/p/@x",
        storefront_enabled: true,
        logo_url: "https://cdn/logo.webp",
        entitlements: {
          status: "active",
          trial_ends_at: null,
          writable: true,
          effective_plan: {
            code: "growth",
            name: "Growth",
            is_free: false,
            active_inventory_limit: 1000,
            member_limit: 5,
            features: features(),
          },
          usage: { inventory_active: 12, members: 3 },
        },
      } as unknown as Partial<ShopDetails>),
      dashboard: dash({ available: 12 }),
      discordConnected: true,
    });

    expect(steps["shop-info"].done).toBe(true);
    expect(steps["inventory"].done).toBe(true);
    expect(steps["branding"].done).toBe(true);
    expect(steps["storefront"].done).toBe(true);
    expect(steps["discord"].done).toBe(true);
    expect(steps["team"].done).toBe(true);
    expect(steps["plan"].done).toBe(true);
  });

  it("locks the plan-gated steps on a free plan and points them at billing", () => {
    const steps = byId({
      shopDetails: shop({
        entitlements: {
          status: "active",
          trial_ends_at: null,
          writable: true,
          effective_plan: {
            code: "free",
            name: "เริ่มต้น",
            is_free: true,
            active_inventory_limit: 20,
            member_limit: 1,
            features: features({ storefront: false, discord: false }),
          },
          usage: { inventory_active: 3, members: 1 },
        },
      } as unknown as Partial<ShopDetails>),
      dashboard: dash({ available: 3 }),
      discordConnected: false,
    });

    expect(steps["storefront"].locked).toBe(true);
    expect(steps["storefront"].ctas[0].action).toBe("billing");
    expect(steps["discord"].locked).toBe(true);
    expect(steps["discord"].ctas[0].action).toBe("billing");
  });
});
