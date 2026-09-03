/**
 * Downscale an image file in the browser before upload so storefront assets
 * stay small (a 1 MB logo rendered at 20px is pure waste and loads slowly).
 * Falls back to the original file if anything goes wrong or it is already small.
 */
export async function shrinkImage(
  file: File,
  maxEdge: number,
  quality = 0.85,
): Promise<File> {
  if (!file.type.startsWith("image/") || file.type === "image/gif") {
    return file;
  }
  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxEdge / Math.max(bitmap.width, bitmap.height));
    if (scale === 1 && file.size < 300_000) {
      bitmap.close();
      return file;
    }
    const width = Math.round(bitmap.width * scale);
    const height = Math.round(bitmap.height * scale);
    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d");
    if (!ctx) return file;
    ctx.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    const hasAlpha = file.type === "image/png" || file.type === "image/webp";
    const outType = hasAlpha ? "image/png" : "image/jpeg";
    const blob = await new Promise<Blob | null>((resolve) =>
      canvas.toBlob(resolve, outType, quality),
    );
    if (!blob || blob.size >= file.size) return file;

    const ext = outType === "image/png" ? "png" : "jpg";
    const name = file.name.replace(/\.[^.]+$/, "") + `.${ext}`;
    return new File([blob], name, { type: outType });
  } catch {
    return file;
  }
}
