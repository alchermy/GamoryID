/** Marketing site (public-web) — hosts the Terms, Privacy, and shop storefront pages. */
export const publicWebUrl = (
  import.meta.env.VITE_PUBLIC_WEB_URL ?? "http://localhost:5174"
).replace(/\/$/, "");

export const termsUrl = `${publicWebUrl}/terms`;
export const privacyUrl = `${publicWebUrl}/privacy`;

/** Public storefront for a shop, addressed by its slug. */
export const storefrontUrl = (slug: string) => `${publicWebUrl}/s/${slug}`;
