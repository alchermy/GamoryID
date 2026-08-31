export type InventoryCopyItem = {
  tag: string;
  title: string;
  riotId: string;
  rank: string;
  level: number;
  price: number;
  description?: string | null;
};

export function buildInventoryCopyText(item: InventoryCopyItem, footer = "") {
  const lines = [
    item.tag,
    "",
    `RiotID=${item.riotId || "–"}`,
    "",
    `Rank=${item.rank || "–"}`,
    "",
    `Level=${item.level.toLocaleString("th-TH")}`,
    "",
    `รายละเอียด=${item.description?.trim() || item.title || "–"}`,
    "",
    `ราคา=${Number(item.price).toLocaleString("th-TH")} บาท`,
  ];
  const normalizedFooter = footer.trim();

  return normalizedFooter
    ? [...lines, "", normalizedFooter].join("\n")
    : lines.join("\n");
}
