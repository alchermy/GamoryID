export type ShopAccessMode = "full" | "readonly" | "suspended";

/**
 * Maps a shop's subscription status to what the merchant can do in the app.
 * The backend already blocks writes for anything but trialing/active
 * (EnsureShopWritable → 423); this drives the UI so the user isn't surprised.
 */
export function shopAccessMode(
  status: string | undefined | null,
): ShopAccessMode {
  if (status === "suspended" || status === "cancelled") return "suspended";
  if (
    status === "grace_read_only" ||
    status === "expired" ||
    status === "pending_payment"
  )
    return "readonly";
  return "full";
}

export function shopAccessNotice(
  mode: ShopAccessMode,
): { title: string; body: string } | null {
  if (mode === "suspended")
    return {
      title: "ร้านถูกระงับการใช้งาน",
      body: "ตอนนี้ทำได้เฉพาะดูข้อมูล กรุณาติดต่อทีมงานเพื่อเปิดใช้งานอีกครั้ง",
    };
  if (mode === "readonly")
    return {
      title: "โหมดอ่านอย่างเดียว",
      body: "แพ็กเกจหมดอายุแล้ว ต่ออายุเพื่อกลับมาเพิ่ม แก้ไข และขายไอดีได้",
    };
  return null;
}
