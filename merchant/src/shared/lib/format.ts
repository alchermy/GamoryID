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

export function createIdempotencyKey() {
  return crypto.randomUUID();
}
