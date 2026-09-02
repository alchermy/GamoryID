import type { ReactNode } from "react";
import { createElement } from "react";
import {
  Check,
  Clock3,
  CreditCard,
  FileUp,
  Image,
  KeyRound,
  LogIn,
  MessageSquare,
  PackagePlus,
  Settings2,
  ShieldCheck,
  Users,
} from "lucide-react";

/** Thai labels for every activity_logs event the merchant can see. */
export const ACTIVITY_LABELS: Record<string, string> = {
  "inventory.created": "เพิ่มไอดีในคลัง",
  "inventory.updated": "อัปเดตรายละเอียดไอดี",
  "inventory.note_updated": "อัปเดตโน้ตช่วยจำของไอดี",
  "inventory.media_added": "เพิ่มรูปสินค้า",
  "inventory.media_deleted": "ลบรูปสินค้า",
  "inventory.reserved": "จองไอดีให้ลูกค้า",
  "inventory.reservation_released": "ยกเลิกการจองไอดี",
  "inventory.reservation_expired": "การจองหมดเวลาอัตโนมัติ",
  "inventory.sold": "บันทึกการขายไอดี",
  "inventory.archived": "เก็บไอดีถาวร",
  "inventory.exported": "ส่งออกข้อมูลคลัง",
  "custom_field.created": "เพิ่มฟิลด์ที่กำหนดเอง",
  "custom_field.updated": "แก้ไขฟิลด์ที่กำหนดเอง",
  "custom_field.deleted": "ลบฟิลด์ที่กำหนดเอง",
  "import.queued": "เริ่มนำเข้าข้อมูล",
  "import.completed": "นำเข้าข้อมูลสำเร็จ",
  "import.failed": "นำเข้าข้อมูลไม่สำเร็จ",
  "credit.top_up_submitted": "ส่งสลิปเติมเครดิต",
  "credit.top_up_approved": "อนุมัติการเติมเครดิต",
  "credit.top_up_rejected": "ไม่อนุมัติการเติมเครดิต",
  "subscription.purchased_with_credits": "ใช้เครดิตซื้อแพ็กเกจ",
  "subscription.auto_renew_updated": "ปรับต่ออายุอัตโนมัติ",
  "team.member_created": "เพิ่มพนักงานใหม่",
  "team.permissions_updated": "ปรับสิทธิ์พนักงาน",
  "team.member_password_reset": "รีเซ็ตรหัสผ่านพนักงาน",
  "team.member_removed": "นำพนักงานออกจากร้าน",
  "shop.updated": "อัปเดตข้อมูลร้าน",
  "shop.created": "สร้างร้าน",
  "shop.archived": "ระงับร้าน (โดยผู้ดูแลระบบ)",
  "shop.restored": "เปิดร้านอีกครั้ง (โดยผู้ดูแลระบบ)",
  "discord.connected": "เชื่อมต่อ Discord",
  "discord.demo_connected": "เชื่อมต่อ Discord (โหมดทดสอบ)",
  "discord.channels_created": "สร้างห้องแจ้งเตือน Discord",
  "discord.channels_updated": "ปรับห้องแจ้งเตือน Discord",
  "discord.test_notification_sent": "ทดสอบแจ้งเตือน Discord",
  "discord.disconnected": "ยกเลิกการเชื่อมต่อ Discord",
  "discord.user_linked": "พนักงานเชื่อมบัญชี Discord",
  "credentials.revealed": "เปิดดูข้อมูลเข้าสู่ระบบของไอดี",
  "auth.registered": "สมัครใช้งานและเปิดร้าน",
  "auth.logged_in": "เข้าสู่ระบบ",
  "auth.logged_out": "ออกจากระบบ",
  "auth.session_revoked": "นำอุปกรณ์ออกจากระบบ",
  "security.reauthenticated": "ยืนยันตัวตนซ้ำ (2FA)",
  "security.reauth_failed": "ยืนยันตัวตนซ้ำไม่สำเร็จ",
  "credit.top_up_reviewed": "ตรวจสอบการเติมเครดิต",
  "subscription.auto_renew_updated_by_admin": "ผู้ดูแลระบบปรับต่ออายุอัตโนมัติ",
};

export function activityLabel(event: string): string {
  return ACTIVITY_LABELS[event] ?? event;
}

export function activityIcon(event: string): ReactNode {
  const size = 15;
  if (event.startsWith("auth.") || event.startsWith("security."))
    return createElement(event.includes("failed") ? ShieldCheck : LogIn, { size });
  if (event.startsWith("team.")) return createElement(Users, { size });
  if (event.startsWith("discord.")) return createElement(MessageSquare, { size });
  if (event.includes("credentials")) return createElement(KeyRound, { size });
  if (event.includes("media")) return createElement(Image, { size });
  if (event.includes("sold")) return createElement(Check, { size });
  if (event.includes("reserv")) return createElement(Clock3, { size });
  if (event.includes("import")) return createElement(FileUp, { size });
  if (event.includes("credit") || event.includes("subscription"))
    return createElement(CreditCard, { size });
  if (event.startsWith("shop.") || event.startsWith("custom_field."))
    return createElement(Settings2, { size });
  return createElement(PackagePlus, { size });
}
