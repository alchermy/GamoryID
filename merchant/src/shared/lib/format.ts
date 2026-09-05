import type { InventoryStatus } from "../../types/models";

export const money = new Intl.NumberFormat("th-TH", {
  style: "currency",
  currency: "THB",
  maximumFractionDigits: 0,
});

export const statusLabel: Record<InventoryStatus, string> = {
  available: "พร้อมขาย",
  reserved: "ถูกจอง",
  sold: "ขายแล้ว",
  archived: "เก็บถาวร",
};

const thaiDateTime = new Intl.DateTimeFormat("th-TH", {
  dateStyle: "medium",
  timeStyle: "short",
});

export function formatDate(value: string) {
  return thaiDateTime.format(new Date(value));
}

/** Short relative time in Thai (e.g. "8 นาทีที่แล้ว"); falls back to an absolute date once it's old. */
export function formatRelativeTime(value: string) {
  const then = new Date(value).getTime();
  if (Number.isNaN(then)) return value;
  const diffMs = Date.now() - then;
  const minutes = Math.floor(diffMs / 60_000);
  if (minutes < 1) return "เมื่อสักครู่";
  if (minutes < 60) return `${minutes} นาทีที่แล้ว`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} ชม.ที่แล้ว`;
  const days = Math.floor(hours / 24);
  if (days === 1) return "เมื่อวาน";
  if (days < 7) return `${days} วันที่แล้ว`;
  return formatDate(value);
}

export function createIdempotencyKey() {
  return crypto.randomUUID();
}
