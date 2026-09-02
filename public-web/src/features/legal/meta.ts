/**
 * Legal-document metadata. Keep in sync with backend `config/legal.php`
 * (LEGAL_TERMS_VERSION / LEGAL_PRIVACY_VERSION / LEGAL_EFFECTIVE_DATE) and with
 * the source-of-record markdown in `docs/terms-of-service.md` /
 * `docs/privacy-notice.md`.
 */
export const TERMS_VERSION = "2026-09-03";
export const PRIVACY_VERSION = "2026-09-03";
export const EFFECTIVE_DATE = "3 กันยายน 2026";

/** Service provider / data-controller identity shown in both documents. */
export const CONTROLLER_NAME = "[ระบุชื่อผู้ให้บริการ]";
export const CONTROLLER_EMAIL = "[ระบุอีเมลติดต่อ]";
export const CONTROLLER_ADDRESS = "[ระบุที่อยู่ ถ้ามี]";

/**
 * While true, each legal page shows a "under legal review" notice. Flip to
 * false once a lawyer has signed off on the wording.
 */
export const LEGAL_DRAFT = true;
