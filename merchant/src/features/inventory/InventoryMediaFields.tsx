import { useEffect, useId, useMemo, useState } from "react";
import { ImageIcon, ImagePlus, Star, Trash2 } from "lucide-react";
import type { InventoryMedia } from "../../types/models";
import type { InventoryMediaDraft } from "./inventory-media-model";

const ACCEPTED_IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp"];
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const MAX_DETAIL_IMAGES = 4;

function validateImage(file: File): string | null {
  if (!ACCEPTED_IMAGE_TYPES.includes(file.type))
    return `${file.name}: รองรับเฉพาะไฟล์ JPEG, PNG หรือ WebP`;
  if (file.size > MAX_IMAGE_BYTES)
    return `${file.name}: รูปภาพต้องมีขนาดไม่เกิน 5 MB`;
  return null;
}

function DraftImage({ file, alt }: { file: File; alt: string }) {
  const url = useMemo(() => URL.createObjectURL(file), [file]);

  useEffect(() => {
    return () => URL.revokeObjectURL(url);
  }, [url]);

  return <img src={url} alt={alt} />;
}

function RemoveButton({
  label,
  onClick,
}: {
  label: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      className="inventory-media-remove"
      aria-label={label}
      title={label}
      onClick={onClick}
    >
      <Trash2 size={15} />
    </button>
  );
}

export function InventoryMediaFields({
  existing = [],
  value,
  onChange,
}: {
  existing?: InventoryMedia[];
  value: InventoryMediaDraft;
  onChange: (next: InventoryMediaDraft) => void;
}) {
  const displayInputId = useId();
  const detailInputId = useId();
  const [error, setError] = useState("");
  const activeExisting = useMemo(
    () => existing.filter((media) => !value.removedMediaIds.includes(media.id)),
    [existing, value.removedMediaIds],
  );
  const existingDisplay = activeExisting.find(
    (media) => media.role === "display",
  );
  const existingDetails = activeExisting.filter(
    (media) => media.role === "detail",
  );
  const detailCount = existingDetails.length + value.detailImages.length;

  const removeExisting = (media: InventoryMedia) => {
    onChange({
      ...value,
      removedMediaIds: [...value.removedMediaIds, media.id],
    });
    setError("");
  };

  return (
    <section
      className="inventory-media-fields"
      aria-labelledby={`${displayInputId}-title`}
    >
      <div className="inventory-media-fields-head">
        <div>
          <span className="eyebrow">รูปภาพสินค้า</span>
          <h3 id={`${displayInputId}-title`}>รูป Display และรูปเพิ่มเติม</h3>
        </div>
        <span className="inventory-media-count">
          {detailCount + (value.displayImage || existingDisplay ? 1 : 0)}/5 รูป
        </span>
      </div>
      <p className="inventory-media-help">
        รองรับ JPEG, PNG และ WebP ขนาดไม่เกิน 5 MB ต่อรูป — Display 1 รูป
        และรายละเอียดสูงสุด 4 รูป
      </p>

      <div className="inventory-media-group">
        <div className="inventory-media-label">
          <Star size={16} />
          <div>
            <strong>รูป Display</strong>
            <span>รูปหลักสำหรับแสดงสินค้า</span>
          </div>
        </div>
        {value.displayImage || existingDisplay ? (
          <div className="inventory-display-preview">
            {value.displayImage ? (
              <DraftImage
                file={value.displayImage}
                alt="ตัวอย่างรูป Display ที่เลือก"
              />
            ) : (
              <img src={existingDisplay?.url} alt="รูป Display ปัจจุบัน" />
            )}
            <div className="inventory-media-preview-meta">
              <span>
                {value.displayImage?.name ??
                  existingDisplay?.originalName ??
                  "รูป Display"}
              </span>
              <label className="button small" htmlFor={displayInputId}>
                เปลี่ยนรูป
              </label>
            </div>
            <RemoveButton
              label="นำรูป Display ออก"
              onClick={() => {
                if (value.displayImage)
                  onChange({ ...value, displayImage: null });
                else if (existingDisplay) removeExisting(existingDisplay);
              }}
            />
          </div>
        ) : (
          <label
            className="inventory-media-picker inventory-display-picker"
            htmlFor={displayInputId}
          >
            <ImagePlus size={22} />
            <strong>เลือกรูป Display</strong>
            <span>แนะนำภาพแนวนอน อัตราส่วน 4:3</span>
          </label>
        )}
        <input
          id={displayInputId}
          className="sr-only"
          type="file"
          accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
          onChange={(event) => {
            const file = event.target.files?.[0];
            event.target.value = "";
            if (!file) return;
            const message = validateImage(file);
            if (message) return setError(message);
            onChange({ ...value, displayImage: file });
            setError("");
          }}
        />
      </div>

      <div className="inventory-media-group">
        <div className="inventory-media-label">
          <ImageIcon size={16} />
          <div>
            <strong>รูปภาพรายละเอียด</strong>
            <span>{detailCount}/4 รูป</span>
          </div>
        </div>
        <div className="inventory-detail-previews">
          {existingDetails.map((media, index) => (
            <div className="inventory-detail-preview" key={media.id}>
              <img
                src={media.url}
                alt={`รูปภาพรายละเอียดปัจจุบัน ${index + 1}`}
              />
              <RemoveButton
                label={`นำรูปภาพรายละเอียด ${index + 1} ออก`}
                onClick={() => removeExisting(media)}
              />
            </div>
          ))}
          {value.detailImages.map((file, index) => (
            <div
              className="inventory-detail-preview"
              key={`${file.name}-${file.lastModified}`}
            >
              <DraftImage
                file={file}
                alt={`ตัวอย่างรูปภาพรายละเอียดใหม่ ${index + 1}`}
              />
              <RemoveButton
                label={`นำรูปภาพรายละเอียดใหม่ ${index + 1} ออก`}
                onClick={() => {
                  onChange({
                    ...value,
                    detailImages: value.detailImages.filter(
                      (_, candidateIndex) => candidateIndex !== index,
                    ),
                  });
                  setError("");
                }}
              />
            </div>
          ))}
          {detailCount < MAX_DETAIL_IMAGES && (
            <label
              className="inventory-media-picker inventory-detail-picker"
              htmlFor={detailInputId}
            >
              <ImagePlus size={20} />
              <span>เพิ่มรูป</span>
            </label>
          )}
        </div>
        <input
          id={detailInputId}
          className="sr-only"
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
          onChange={(event) => {
            const files = Array.from(event.target.files ?? []);
            event.target.value = "";
            const invalid = files.map(validateImage).find(Boolean);
            if (invalid) return setError(invalid);
            const availableSlots = MAX_DETAIL_IMAGES - detailCount;
            if (files.length > availableSlots)
              return setError(`เพิ่มได้อีกสูงสุด ${availableSlots} รูป`);
            onChange({
              ...value,
              detailImages: [...value.detailImages, ...files],
            });
            setError("");
          }}
        />
      </div>
      {error && (
        <p className="inventory-media-error" role="alert">
          {error}
        </p>
      )}
    </section>
  );
}

export function InventoryMediaGallery({
  itemTag,
  media,
}: {
  itemTag: string;
  media: InventoryMedia[];
}) {
  const orderedMedia = useMemo(
    () =>
      [...media].sort((a, b) =>
        a.role === "display"
          ? -1
          : b.role === "display"
            ? 1
            : a.sortOrder - b.sortOrder,
      ),
    [media],
  );
  const [selection, setSelection] = useState<{
    itemTag: string;
    mediaId: number;
  } | null>(null);
  const selectedId =
    selection?.itemTag === itemTag
      ? selection.mediaId
      : (orderedMedia[0]?.id ?? null);

  const selected =
    orderedMedia.find((image) => image.id === selectedId) ?? orderedMedia[0];

  if (!selected) {
    return (
      <section
        className="panel inventory-gallery inventory-gallery-empty"
        aria-label="รูปภาพสินค้า"
      >
        <ImageIcon size={28} />
        <strong>ไอดีนี้ยังไม่มีรูปภาพ</strong>
        <span>กด “แก้ไขข้อมูล” เพื่อเพิ่มรูป Display และรูปภาพรายละเอียด</span>
      </section>
    );
  }

  return (
    <section
      className="panel inventory-gallery"
      aria-labelledby="inventory-gallery-title"
    >
      <div className="inventory-gallery-head">
        <div>
          <span className="eyebrow">รูปภาพสินค้า</span>
          <h3 id="inventory-gallery-title">
            {selected.role === "display" ? "รูป Display" : "รูปภาพรายละเอียด"}
          </h3>
        </div>
        <span>{orderedMedia.length}/5 รูป</span>
      </div>
      <div className="inventory-gallery-stage">
        <img
          src={selected.url}
          alt={`${selected.role === "display" ? "รูป Display" : "รูปภาพรายละเอียด"} ของ ${itemTag}`}
        />
      </div>
      {orderedMedia.length > 1 && (
        <div
          className="inventory-gallery-thumbnails"
          aria-label="เลือกรูปภาพที่ต้องการดู"
        >
          {orderedMedia.map((image, index) => (
            <button
              type="button"
              key={image.id}
              className={image.id === selected.id ? "active" : ""}
              aria-label={`ดู${image.role === "display" ? "รูป Display" : `รูปภาพรายละเอียด ${index}`}`}
              aria-pressed={image.id === selected.id}
              onClick={() => setSelection({ itemTag, mediaId: image.id })}
            >
              <img loading="lazy" src={image.url} alt="" />
              {image.role === "display" && (
                <span>
                  <Star size={12} /> Display
                </span>
              )}
            </button>
          ))}
        </div>
      )}
    </section>
  );
}
