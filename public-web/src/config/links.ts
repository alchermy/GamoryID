const merchantAppUrl = (
  import.meta.env.VITE_MERCHANT_APP_URL ?? "http://localhost:5173"
).replace(/\/$/, "");

export const merchantLoginUrl = `${merchantAppUrl}/login`;
export const merchantRegisterUrl = `${merchantAppUrl}/register`;

/** GamoryID API base — the marketing site only calls unauthenticated /public/* routes. */
export const apiBaseUrl = (
  import.meta.env.VITE_API_URL ?? "http://localhost:8000/api/v1"
).replace(/\/$/, "");
