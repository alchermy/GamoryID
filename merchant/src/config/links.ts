/** Marketing site (public-web) — hosts the Terms and Privacy pages. */
export const publicWebUrl = (
  import.meta.env.VITE_PUBLIC_WEB_URL ?? "http://localhost:5174"
).replace(/\/$/, "");

export const termsUrl = `${publicWebUrl}/terms`;
export const privacyUrl = `${publicWebUrl}/privacy`;
