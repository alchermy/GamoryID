import type { ReactNode } from "react";
import { Link2, MessageCircle, Phone } from "lucide-react";
import type { ShopProfile } from "./api";

export function ContactChips({ shop }: { shop: ShopProfile }) {
  const chips: { key: string; href: string; label: string; icon: ReactNode }[] = [];
  if (shop.line_url)
    chips.push({
      key: "line",
      href: shop.line_url,
      label: "LINE",
      icon: <MessageCircle size={16} />,
    });
  if (shop.facebook_url)
    chips.push({
      key: "fb",
      href: shop.facebook_url,
      label: "Facebook",
      icon: <Link2 size={16} />,
    });
  if (shop.phone)
    chips.push({
      key: "phone",
      href: `tel:${shop.phone.replace(/[^\d+]/g, "")}`,
      label: shop.phone,
      icon: <Phone size={16} />,
    });

  if (chips.length === 0)
    return (
      <p className="storefront-nocontact">ร้านนี้ยังไม่ได้ระบุช่องทางติดต่อ</p>
    );

  return (
    <div className="storefront-contact">
      {chips.map((chip) => (
        <a
          key={chip.key}
          className="storefront-chip"
          href={chip.href}
          target={chip.key === "phone" ? undefined : "_blank"}
          rel="noopener noreferrer"
        >
          {chip.icon}
          {chip.label}
        </a>
      ))}
    </div>
  );
}
