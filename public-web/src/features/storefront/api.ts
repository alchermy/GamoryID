import { apiBaseUrl } from "../../config/links";

export type ShopProfile = {
  name: string;
  slug: string;
  description: string | null;
  facebook_url: string | null;
  line_url: string | null;
  phone: string | null;
  inventory_copy_footer: string | null;
  timezone: string | null;
};

export type ShopListing = {
  tag: string;
  title: string | null;
  rank: string | null;
  level: number | null;
  skin_count: number | null;
  battlepass_level: number | null;
  description: string | null;
  list_price: string | number | null;
  updated_at: string | null;
  image: string | null;
};

export type ShopMedia = { id: number; role: "display" | "detail"; image_url: string };

export type ShopItemDetail = ShopListing & { media: ShopMedia[] };

export class HttpError extends Error {
  status: number;

  constructor(status: number) {
    super(`HTTP ${status}`);
    this.status = status;
  }
}

async function getJson<T>(path: string, signal: AbortSignal): Promise<T> {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    signal,
    headers: { Accept: "application/json" },
  });
  if (!response.ok) throw new HttpError(response.status);
  const body = (await response.json()) as { data?: T };
  if (body.data === undefined) throw new HttpError(response.status);
  return body.data;
}

export function fetchShop(slug: string, signal: AbortSignal) {
  return getJson<ShopProfile>(`/public/shops/${encodeURIComponent(slug)}`, signal);
}

export function fetchInventory(slug: string, page: number, signal: AbortSignal) {
  return fetch(
    `${apiBaseUrl}/public/shops/${encodeURIComponent(slug)}/inventory?page=${page}`,
    { signal, headers: { Accept: "application/json" } },
  ).then(async (response) => {
    if (!response.ok) throw new HttpError(response.status);
    return (await response.json()) as {
      data: ShopListing[];
      meta: { current_page: number; last_page: number; total: number };
    };
  });
}

export function fetchItem(slug: string, tag: string, signal: AbortSignal) {
  return getJson<ShopItemDetail>(
    `/public/shops/${encodeURIComponent(slug)}/items/${encodeURIComponent(tag)}`,
    signal,
  );
}

export function priceLabel(value: string | number | null): string {
  const n = typeof value === "string" ? Number(value) : value;
  return n == null || Number.isNaN(n)
    ? "สอบถามราคา"
    : `${n.toLocaleString("th-TH")} บาท`;
}
