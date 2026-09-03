/**
 * Imperative <head> updater for the client-rendered storefront pages.
 * Helps JS-executing crawlers, link unfurlers that render, and the tab title.
 * Full crawler coverage still needs SSR/prerender (planned separately).
 */
type PageMeta = {
  title: string;
  description?: string | null;
  image?: string | null;
  canonical?: string | null;
};

function upsert(selector: string, make: () => HTMLElement): HTMLElement {
  let el = document.head.querySelector<HTMLElement>(selector);
  if (!el) {
    el = make();
    document.head.appendChild(el);
  }
  return el;
}

function setMeta(attr: "property" | "name", key: string, value: string) {
  const el = upsert(`meta[${attr}="${key}"]`, () => {
    const m = document.createElement("meta");
    m.setAttribute(attr, key);
    return m;
  });
  el.setAttribute("content", value);
}

export function applyPageMeta({ title, description, image, canonical }: PageMeta) {
  document.title = title;
  setMeta("property", "og:title", title);
  setMeta("name", "twitter:title", title);

  if (description) {
    setMeta("name", "description", description);
    setMeta("property", "og:description", description);
    setMeta("name", "twitter:description", description);
  }
  if (image) {
    setMeta("property", "og:image", image);
    setMeta("name", "twitter:image", image);
  }
  if (canonical) {
    setMeta("property", "og:url", canonical);
    const link = upsert('link[rel="canonical"]', () => {
      const l = document.createElement("link");
      l.rel = "canonical";
      return l;
    }) as HTMLLinkElement;
    link.href = canonical;
  }
}
