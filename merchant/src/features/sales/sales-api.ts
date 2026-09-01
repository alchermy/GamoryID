import { shopRequest } from "../../api";
import type { SaleRecord } from "../../types/models";

export async function loadSaleDetail(
  shopId: number,
  saleId: number,
  signal?: AbortSignal,
): Promise<SaleRecord> {
  const response = await shopRequest<{ data: SaleRecord }>(
    `/sales/${saleId}`,
    shopId,
    { signal },
  );

  return response.data;
}
