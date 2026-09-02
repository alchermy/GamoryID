/** PromptPay account that credit top-ups are paid to. Override per deploy. */
export const promptPayMobile = (
  import.meta.env.VITE_PROMPTPAY_MOBILE ?? "0941379216"
).replace(/\D/g, "");

export const promptPayName =
  import.meta.env.VITE_PROMPTPAY_NAME ?? "นายธนวัฒน์ ว่องประสบโชค";
