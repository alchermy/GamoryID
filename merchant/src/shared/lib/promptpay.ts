/**
 * Build an EMVCo QR payload for a PromptPay transfer to a mobile number.
 * Pass an amount to make it a dynamic (fixed-amount) QR; omit it for a
 * static QR the payer types the amount into.
 */
export function promptPayPayload(mobile: string, amount?: number): string {
  const digits = mobile.replace(/\D/g, "");
  const account = ("0066" + digits.replace(/^0/, "")).padStart(13, "0");
  const hasAmount = typeof amount === "number" && amount > 0;

  const merchantAccountInfo =
    tlv("00", "A000000677010111") + tlv("01", account);

  let payload =
    tlv("00", "01") +
    tlv("01", hasAmount ? "12" : "11") +
    tlv("29", merchantAccountInfo) +
    tlv("53", "764") +
    (hasAmount ? tlv("54", amount.toFixed(2)) : "") +
    tlv("58", "TH") +
    "6304";

  return payload + crc16(payload);
}

function tlv(id: string, value: string): string {
  return id + value.length.toString().padStart(2, "0") + value;
}

function crc16(input: string): string {
  let crc = 0xffff;
  for (let i = 0; i < input.length; i += 1) {
    crc ^= input.charCodeAt(i) << 8;
    for (let bit = 0; bit < 8; bit += 1) {
      crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
    }
  }
  return crc.toString(16).toUpperCase().padStart(4, "0");
}
