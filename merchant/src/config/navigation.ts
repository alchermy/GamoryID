import {
  BarChart3,
  CreditCard,
  FileUp,
  ReceiptText,
  Settings,
  ShieldCheck,
  ShoppingBag,
  Users,
  WalletCards,
} from "lucide-react";
import type { MerchantPage } from "../types/models";

export const PAGE_PATHS: Record<MerchantPage, string> = {
  dashboard: "/",
  inventory: "/inventory",
  sales: "/sales",
  customers: "/customers",
  imports: "/imports",
  team: "/team",
  billing: "/billing",
  transactions: "/transactions",
  settings: "/settings",
};

export const PATH_PAGES = new Map(
  Object.entries(PAGE_PATHS).map(([page, path]) => [
    path,
    page as MerchantPage,
  ]),
);

export const mainNavigation = [
  ["dashboard", "ภาพรวม", BarChart3],
  ["inventory", "คลังไอดี", ShoppingBag],
  ["sales", "รายการขาย", WalletCards],
  ["customers", "ลูกค้า", Users],
  ["imports", "นำเข้าข้อมูล", FileUp],
] as const;

export const managementNavigation = [
  ["team", "ทีมและสิทธิ์", ShieldCheck],
  ["billing", "แพ็กเกจ", CreditCard],
  ["transactions", "ประวัติธุรกรรม", ReceiptText],
  ["settings", "ตั้งค่าร้าน", Settings],
] as const;

export const permissionOptions: [string, string][] = [
  ["inventory.manage", "จัดการสต็อก"],
  ["inventory.sell", "จองและขาย"],
  ["profit.view", "ดูต้นทุนและกำไร"],
  ["data.export", "ส่งออกข้อมูล"],
  ["credentials.reveal", "เปิดดูข้อมูลเข้าสู่ระบบ"],
  ["team.manage", "จัดการทีม"],
  ["billing.manage", "จัดการแพ็กเกจและชำระเงิน"],
];
