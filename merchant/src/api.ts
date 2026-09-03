const API_BASE = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api/v1";

export function apiAssetUrl(path: string): string {
  if (/^https?:\/\//i.test(path)) return path;
  return new URL(path, API_BASE).toString();
}

function csrfHeader(): Record<string, string> {
  const token = document.cookie
    .split("; ")
    .find((value) => value.startsWith("XSRF-TOKEN="))
    ?.split("=")[1];
  return token ? { "X-XSRF-TOKEN": decodeURIComponent(token) } : {};
}

export async function prepareCsrf(): Promise<Record<string, string>> {
  const origin = API_BASE.replace(/\/api\/v1$/, "");
  const response = await fetch(`${origin}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  if (!response.ok)
    throw new Error("ไม่สามารถเตรียมการยืนยันความปลอดภัยได้ กรุณาลองใหม่");
  return csrfHeader();
}

export async function apiRequest<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const isFormData = init.body instanceof FormData;
  const method = (init.method ?? "GET").toUpperCase();
  const requiresCsrf = !["GET", "HEAD", "OPTIONS"].includes(method);
  const timeout = new AbortController();
  const timeoutId = window.setTimeout(() => timeout.abort(), 15_000);
  let response: Response;
  try {
    let securityHeaders = requiresCsrf ? csrfHeader() : {};
    if (requiresCsrf && !securityHeaders["X-XSRF-TOKEN"])
      securityHeaders = await prepareCsrf();

    const send = (headers: Record<string, string>) =>
      fetch(`${API_BASE}${path}`, {
        ...init,
        method,
        signal: init.signal ?? timeout.signal,
        credentials: "include",
        headers: {
          Accept: "application/json",
          ...(isFormData ? {} : { "Content-Type": "application/json" }),
          ...init.headers,
          ...headers,
        },
      });

    response = await send(securityHeaders);
    if (requiresCsrf && response.status === 419) {
      securityHeaders = await prepareCsrf();
      response = await send(securityHeaders);
    }
  } catch (error) {
    if (timeout.signal.aborted)
      throw new Error("การเชื่อมต่อใช้เวลานานเกินไป กรุณาลองใหม่");
    throw error;
  } finally {
    window.clearTimeout(timeoutId);
  }
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(body.message ?? "ไม่สามารถเชื่อมต่อระบบได้");
    Object.assign(error, {
      status: response.status,
      code: body.code,
      fields: body.errors ?? {},
    });
    throw error;
  }
  return body as T;
}

export function shopRequest<T>(
  path: string,
  shopId: number,
  init: RequestInit = {},
): Promise<T> {
  return apiRequest<T>(path, {
    ...init,
    headers: { "X-Shop-Id": String(shopId), ...init.headers },
  });
}

export async function downloadShopFile(
  path: string,
  shopId: number,
  filename: string,
): Promise<void> {
  const timeout = new AbortController();
  const timeoutId = window.setTimeout(() => timeout.abort(), 15_000);
  try {
    const response = await fetch(`${API_BASE}${path}`, {
      credentials: "include",
      headers: {
        Accept: "application/octet-stream",
        "X-Shop-Id": String(shopId),
      },
      signal: timeout.signal,
    });
    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      throw new Error(body.message ?? "ไม่สามารถดาวน์โหลดไฟล์ได้");
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 0);
  } catch (error) {
    if (timeout.signal.aborted) {
      throw new Error("การดาวน์โหลดใช้เวลานานเกินไป กรุณาลองใหม่");
    }
    throw error;
  } finally {
    window.clearTimeout(timeoutId);
  }
}
