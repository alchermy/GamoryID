/**
 * Downscale + re-encode an image to WebP in the browser before upload, so
 * storefront and inventory assets stay light (a 1 MB PNG logo shown at 20px
 * is pure waste). Returns the original file untouched if WebP is unsupported,
 * the source is a GIF/non-image, or the result would not actually be smaller.
 */
export async function shrinkImage(
  file: File,
  maxEdge: number,
  quality = 0.82,
): Promise<File> {
  if (!file.type.startsWith("image/") || file.type === "image/gif") {
    return file;
  }
  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxEdge / Math.max(bitmap.width, bitmap.height));
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));

    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      bitmap.close();
      return file;
    }
    ctx.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
      canvas.toBlob(resolve, "image/webp", quality),
    );
    // toBlob ignores an unsupported type and falls back to PNG — bail if we
    // did not actually get WebP, or if it came out no smaller than the source.
    if (!blob || blob.type !== "image/webp" || blob.size >= file.size) {
      return file;
    }

    const name = file.name.replace(/\.[^.]+$/, "") + ".webp";
    return new File([blob], name, { type: "image/webp" });
  } catch {
    return file;
  }
}
