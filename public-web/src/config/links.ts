const merchantAppUrl = (
  import.meta.env.VITE_MERCHANT_APP_URL ?? "http://localhost:5173"
).replace(/\/$/, "");

export const merchantLoginUrl = `${merchantAppUrl}/login`;
export const merchantRegisterUrl = `${merchantAppUrl}/register`;
