// Google Analytics 4 — loaded only when VITE_GA_ID is set at build time.
// No id means no script, no dataLayer, no cookies.
export function initAnalytics(): void {
  const id = import.meta.env.VITE_GA_ID;
  if (!id || typeof document === "undefined") return;

  const tag = document.createElement("script");
  tag.async = true;
  tag.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(id);
  document.head.appendChild(tag);

  window.dataLayer = window.dataLayer || [];
  const gtag = (...args: unknown[]) => {
    window.dataLayer!.push(args);
  };
  gtag("js", new Date());
  gtag("config", id);
  window.gtag = gtag;
}
