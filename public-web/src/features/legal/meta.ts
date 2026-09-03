/**
 * Legal-document metadata. Keep in sync with backend `config/legal.php`
 * (LEGAL_TERMS_VERSION / LEGAL_PRIVACY_VERSION / LEGAL_EFFECTIVE_DATE) and with
 * the source-of-record markdown in `docs/terms-of-service.md` /
 * `docs/privacy-notice.md`.
 */
export const TERMS_VERSION = "2026-09-04";
export const PRIVACY_VERSION = "2026-09-04";
export const EFFECTIVE_DATE = "4 กันยายน 2026";

/** Service provider / data-controller identity shown in both documents. */
export const CONTROLLER_NAME = "นายธนวัฒน์ ว่องประสบโชค";
export const CONTROLLER_EMAIL = "thanawat.won01@gmail.com";
export const CONTROLLER_ADDRESS =
  "62/137 ซ.เสรีไทย72 ถ.เสรีไทย แขวงมีนบุรี เขตมีนบุรี กรุงเทพมหานคร 10510";

/**
 * When true, each legal page shows an "under legal review" notice. This is the
 * in-force published version — no draft banner. Set true only if a future
 * revision needs review before taking effect.
 */
export const LEGAL_DRAFT = false;
