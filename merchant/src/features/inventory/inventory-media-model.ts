export type InventoryMediaDraft = {
  displayImage: File | null;
  detailImages: File[];
  removedMediaIds: number[];
};

export function createEmptyMediaDraft(): InventoryMediaDraft {
  return { displayImage: null, detailImages: [], removedMediaIds: [] };
}
