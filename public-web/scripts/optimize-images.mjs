import sharp from "sharp";
import { readdir, mkdir, stat } from "node:fs/promises";
import { join } from "node:path";

const SRC = "scripts/mascot-src";
const OUT = "public/mascot/opt";
const WIDTHS = [220, 440];

// Only the poses the landing page actually renders.
const USED = [
  "gammy-main.png",
  "gammy-secure.png",
  "gammy-hello.png",
  "gammy-search.png",
  "gammy-sold.png",
];

await mkdir(OUT, { recursive: true });
const files = (await readdir(SRC)).filter(
  (f) => f.endsWith(".png") && USED.includes(f),
);

for (const file of files) {
  const base = file.replace(/\.png$/, "");
  const input = join(SRC, file);
  for (const w of WIDTHS) {
    const suffix = w === WIDTHS[0] ? "" : "@2x";
    await sharp(input)
      .resize({ width: w, withoutEnlargement: true })
      .webp({ quality: 82, effort: 6 })
      .toFile(join(OUT, `${base}${suffix}.webp`));
    await sharp(input)
      .resize({ width: w, withoutEnlargement: true })
      .png({ compressionLevel: 9, palette: true, quality: 82 })
      .toFile(join(OUT, `${base}${suffix}.png`));
  }
}

for (const f of (await readdir(OUT)).sort()) {
  const s = await stat(join(OUT, f));
  console.log(`${f.padEnd(30)} ${(s.size / 1024).toFixed(1)} KB`);
}
