import type { DashboardData, ShopDetails } from "../../types/models";

export type OnboardingStepKind = "required" | "recommended" | "optional" | "info";

export type OnboardingCtaAction =
  | "settings-info"
  | "add"
  | "imports"
  | "branding"
  | "storefront"
  | "discord"
  | "team"
  | "billing";

export type OnboardingCta = { label: string; action: OnboardingCtaAction };

export type OnboardingStep = {
  id: string;
  title: string;
  description: string;
  kind: OnboardingStepKind;
  /** Step goal is met — data already reflects it. */
  done: boolean;
  /** Plan-gated and the current plan does not include it. */
  locked: boolean;
  /** Shown instead of the normal CTA when locked. */
  lockedNote?: string;
  ctas: OnboardingCta[];
};

export type OnboardingContext = {
  shopDetails: ShopDetails | null;
  dashboard: DashboardData | null;
  discordConnected: boolean;
};

function daysLeft(shop: ShopDetails | null): number | null {
  const end =
    shop?.entitlements?.current_period_ends_at ?? shop?.trial_ends_at ?? null;
  if (!end) return null;
  return Math.max(
    0,
    Math.ceil((new Date(end).getTime() - Date.now()) / 86_400_000),
  );
}

/**
 * Pure: turns the data the app already holds into the 7 setup-guide steps.
 * Order is the recommended order of work; `done`/`locked` come straight from
 * `/shop` + `/dashboard` (+ `/discord/settings` for step 5).
 */
export function buildOnboardingSteps(ctx: OnboardingContext): OnboardingStep[] {
  const { shopDetails, dashboard, discordConnected } = ctx;
  const ent = shopDetails?.entitlements ?? null;
  const features = ent?.effective_plan.features ?? null;

  const hasContact = Boolean(
    shopDetails?.line_url ?? shopDetails?.facebook_url ?? shopDetails?.phone,
  );
  const hasProfile = Boolean(shopDetails?.description) && hasContact;

  const inventoryCount = dashboard
    ? dashboard.summary.available +
      dashboard.summary.reserved +
      dashboard.summary.sold_total
    : 0;

  const storefrontAllowed = features ? features.storefront : true;
  const discordAllowed = features ? features.discord : true;
  const members = ent?.usage.members ?? 1;
  const planActive = ent?.status === "active";
  const remaining = daysLeft(shopDetails);

  return [
    {
      id: "shop-info",
      title: "กรอกข้อมูลร้านและช่องทางติดต่อ",
      description:
        "ใส่คำอธิบายร้าน และช่องทางติดต่ออย่างน้อยหนึ่งช่อง (LINE, Facebook หรือเบอร์โทร) เพื่อให้ลูกค้าติดต่อกลับได้",
      kind: "required",
      done: hasProfile,
      locked: false,
      ctas: [{ label: "ไปกรอกข้อมูลร้าน", action: "settings-info" }],
    },
    {
      id: "inventory",
      title: "เพิ่มไอดีลงคลัง",
      description:
        "บันทึกไอดีแรกเข้าคลัง เพิ่มทีละรายการ หรือใช้การนำเข้าจากไฟล์เพื่อลงหลายรายการพร้อมกัน",
      kind: "required",
      done: inventoryCount > 0,
      locked: false,
      ctas: [
        { label: "เพิ่มไอดี", action: "add" },
        { label: "นำเข้าจากไฟล์", action: "imports" },
      ],
    },
    {
      id: "branding",
      title: "ใส่โลโก้ร้าน",
      description:
        "โลโก้จะแสดงบนหน้าร้านสาธารณะและในหน้ารวมสินค้าทุกร้าน ช่วยให้ลูกค้าจำร้านได้",
      kind: "recommended",
      done: Boolean(shopDetails?.logo_url),
      locked: false,
      ctas: [{ label: "อัปโหลดโลโก้", action: "branding" }],
    },
    {
      id: "storefront",
      title: "เปิดหน้าร้านสาธารณะ",
      description:
        "เปิดหน้าร้านเพื่อให้ลูกค้าเปิดดูไอดีที่พร้อมขายได้เองผ่านลิงก์ร้านของคุณ",
      kind: "recommended",
      done: Boolean(shopDetails?.storefront_enabled),
      locked: !storefrontAllowed,
      lockedNote: "ใช้ได้ตั้งแต่แพ็ก Starter ขึ้นไป",
      ctas: storefrontAllowed
        ? [{ label: "เปิดหน้าร้าน", action: "storefront" }]
        : [{ label: "ดูแพ็กเกจ", action: "billing" }],
    },
    {
      id: "discord",
      title: "เชื่อม Discord ของร้าน",
      description:
        "เชื่อมเซิร์ฟเวอร์ Discord เพื่อรับแจ้งเตือนและจัดการร้านด้วยคำสั่งภาษาไทยจากในเซิร์ฟเวอร์",
      kind: "optional",
      done: discordConnected,
      locked: !discordAllowed,
      lockedNote: "ใช้ได้ตั้งแต่แพ็ก Growth ขึ้นไป",
      ctas: discordAllowed
        ? [{ label: "ตั้งค่า Discord", action: "discord" }]
        : [{ label: "ดูแพ็กเกจ", action: "billing" }],
    },
    {
      id: "team",
      title: "เพิ่มพนักงานเข้าร้าน",
      description:
        "เชิญพนักงานและกำหนดสิทธิ์การใช้งาน เช่น จัดการสต็อก จองและขาย หรือดูต้นทุนกำไร",
      kind: "optional",
      done: members > 1,
      locked: false,
      ctas: [{ label: "จัดการทีม", action: "team" }],
    },
    {
      id: "plan",
      title: "เลือกแพ็กเกจก่อนหมดช่วงทดลอง",
      description: planActive
        ? "ร้านของคุณมีแพ็กเกจที่ใช้งานอยู่แล้ว"
        : remaining != null
          ? `เหลือเวลาทดลองอีก ${remaining.toLocaleString("th-TH")} วัน — เลือกแพ็กเกจเพื่อใช้งานต่อเนื่องไม่สะดุด`
          : "เลือกแพ็กเกจที่เหมาะกับขนาดร้านของคุณ",
      kind: "info",
      done: planActive,
      locked: false,
      ctas: [{ label: "ดูแพ็กเกจ", action: "billing" }],
    },
  ];
}
