import sharp from "sharp";

const W = 1200;
const H = 630;

// Brand card: near-white field, cyan signal rail, headline, mascot.
const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#ffffff"/>
      <stop offset="1" stop-color="#eaf4ff"/>
    </linearGradient>
    <linearGradient id="rail" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#00c2ff"/>
      <stop offset="1" stop-color="#0b6bff"/>
    </linearGradient>
  </defs>
  <rect width="${W}" height="${H}" fill="url(#bg)"/>
  <rect x="0" y="0" width="10" height="${H}" fill="url(#rail)"/>
  <circle cx="1050" cy="120" r="220" fill="#00c2ff" opacity="0.10"/>
  <text x="86" y="150" font-family="IBM Plex Sans Thai, Segoe UI, sans-serif" font-size="30" font-weight="600" fill="#0b6bff" letter-spacing="1">GAMORYID</text>
  <text x="84" y="266" font-family="IBM Plex Sans Thai, Segoe UI, sans-serif" font-size="76" font-weight="700" fill="#071a33">ค้นด้วย <tspan fill="#0b6bff" font-family="IBM Plex Mono, monospace">#23DX5</tspan></text>
  <text x="84" y="360" font-family="IBM Plex Sans Thai, Segoe UI, sans-serif" font-size="76" font-weight="700" fill="#071a33">จองไม่ชน ขายไว</text>
  <text x="86" y="444" font-family="Noto Sans Thai, Segoe UI, sans-serif" font-size="32" fill="#64758b">ระบบจัดการสต็อกไอดีเกม การจอง</text>
  <text x="86" y="486" font-family="Noto Sans Thai, Segoe UI, sans-serif" font-size="32" fill="#64758b">ลูกค้า และประวัติการขาย</text>
  <rect x="86" y="512" width="360" height="66" rx="14" fill="#ff7900"/>
  <text x="266" y="555" text-anchor="middle" font-family="IBM Plex Sans Thai, Segoe UI, sans-serif" font-size="30" font-weight="700" fill="#ffffff">ทดลองใช้ฟรี 30 วัน</text>
</svg>`;

const mascot = await sharp("scripts/mascot-src/gammy-secure.png")
  .resize({ width: 360 })
  .png()
  .toBuffer();

await sharp(Buffer.from(svg))
  .composite([{ input: mascot, left: 830, top: 250 }])
  .png({ compressionLevel: 9 })
  .toFile("public/og-cover.png");

console.log("wrote public/og-cover.png");
