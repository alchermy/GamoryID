/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_MERCHANT_APP_URL?: string;
  readonly VITE_API_URL?: string;
  /** Google Analytics 4 measurement id, e.g. "G-XXXXXXXXXX". Empty = analytics off. */
  readonly VITE_GA_ID?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

interface Window {
  dataLayer?: unknown[];
  gtag?: (...args: unknown[]) => void;
}
