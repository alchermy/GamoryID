import { describe, expect, it } from "vitest";
import { mapInventoryItem } from "../features/merchant/MerchantApp";
import type { InventoryResponse } from "../types/models";

type ApiRecord = InventoryResponse["data"][number];

function apiRecord(over: Partial<ApiRecord> = {}): ApiRecord {
  return {
    id: 1,
    tag: "#TEST1",
    title: "Test#TH01",
    riot_id: "Test#TH01",
    username: null,
    email: null,
    rank: null,
    level: null,
    skin_count: 0,
    description: null,
    notes: null,
    cost: 100,
    list_price: 200,
    status: "available",
    updated_at: "2026-09-04T12:00:00Z",
    has_credentials: false,
    ...over,
  };
}

describe("mapInventoryItem", () => {
  // Regression: the backend's create response returns skin_count: null right
  // after a freshly-created item (Eloquent doesn't pick up the column's DB
  // default until the model is reloaded — see InventoryController@store).
  // Passing that straight through used to crash InventoryDetailPage's
  // item.skins.toLocaleString() with "Cannot read properties of null".
  it("defaults a null skin_count to 0 instead of passing null through", () => {
    const item = mapInventoryItem(apiRecord({ skin_count: null as never }));
    expect(item.skins).toBe(0);
  });

  it("defaults a null level to 0", () => {
    const item = mapInventoryItem(apiRecord({ level: null }));
    expect(item.level).toBe(0);
  });

  it("keeps a real skin_count value", () => {
    const item = mapInventoryItem(apiRecord({ skin_count: 42 }));
    expect(item.skins).toBe(42);
  });
});
