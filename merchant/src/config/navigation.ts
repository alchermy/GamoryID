import {
  BarChart3,
  CreditCard,
  FileUp,
  ReceiptText,
  MessagesSquare,
  ScrollText,
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
  activity: "/activity",
  billing: "/billing",
  transactions: "/transactions",
  discord: "/discord",
  settings: "/settings",
  manual: "/manual",
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
  ["activity", "บันทึกกิจกรรม", ScrollText],
  ["billing", "แพ็กเกจ", CreditCard],
  ["transactions", "ประวัติธุรกรรม", ReceiptText],
  ["discord", "Discord", MessagesSquare],
  ["settings", "ตั้งค่าร้าน", Settings],
] as const;

export const permissionOptions: [string, string, string][] = [
  [
    "inventory.manage",
    "จัดการสต็อก",
    "ใช้คำสั่ง Discord สำหรับค้นหา ดูรายการ เพิ่มไอดี และบันทึกโน้ต",
  ],
  [
    "inventory.sell",
    "จองและขาย",
    "ใช้คำสั่ง Discord สำหรับค้นหา ดูรายการ จอง ยกเลิกจอง ปิดการขาย และบันทึกโน้ต",
  ],
  [
    "profit.view",
    "ดูต้นทุนและกำไร",
    "แสดงกำไรในคำสั่ง /ร้าน สรุป และหน้ารายงานที่เกี่ยวข้อง",
  ],
  ["data.export", "ส่งออกข้อมูล", "ดาวน์โหลดข้อมูลของร้านจากหน้าเว็บ"],
  [
    "credentials.reveal",
    "เปิดดูข้อมูลเข้าสู่ระบบ",
    "เปิดดูข้อมูลลับจากหน้าเว็บเท่านั้น ระบบจะไม่ส่งผ่าน Discord",
  ],
  ["team.manage", "จัดการทีม", "เชิญพนักงานและกำหนดสิทธิ์ของสมาชิก"],
  [
    "billing.manage",
    "จัดการแพ็กเกจและชำระเงิน",
    "เติมเครดิต ซื้อแพ็กเกจ และตั้งค่าต่ออายุ",
  ],
  [
    "discord.manage",
    "จัดการ Discord ของร้าน",
    "เชื่อมบอท สร้างห้อง และตั้งค่าการแจ้งเตือน ไม่รวมสิทธิ์จัดการสต็อก",
  ],
];
