// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "../api";

afterEach(() => {
  vi.restoreAllMocks();
  document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
});

describe("apiRequest CSRF handling", () => {
  it("แนบ XSRF token อัตโนมัติกับคำขอที่แก้ไขข้อมูล", async () => {
    document.cookie = "XSRF-TOKEN=inventory-token; path=/";
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ data: { status: "reserved" } }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );

    await apiRequest("/inventory/1/reserve", { method: "POST" });

    const request = fetchMock.mock.calls[0];
    expect(request[0]).toBe("http://localhost:8000/api/v1/inventory/1/reserve");
    expect(request[1]).toMatchObject({
      credentials: "include",
      method: "POST",
    });
    expect(request[1]?.headers).toMatchObject({
      "X-XSRF-TOKEN": "inventory-token",
    });
  });

  it("ขอ CSRF cookie ใหม่และลองคำขอซ้ำหนึ่งครั้งเมื่อได้รับ 419", async () => {
    document.cookie = "XSRF-TOKEN=expired-token; path=/";
    let apiAttempts = 0;
    const fetchMock = vi
      .spyOn(globalThis, "fetch")
      .mockImplementation(async (input) => {
        const url = String(input);
        if (url.endsWith("/sanctum/csrf-cookie")) {
          document.cookie = "XSRF-TOKEN=fresh%20token; path=/";
          return new Response(null, { status: 204 });
        }
        apiAttempts += 1;
        return apiAttempts === 1
          ? new Response(JSON.stringify({ message: "CSRF token mismatch." }), {
              status: 419,
              headers: { "Content-Type": "application/json" },
            })
          : new Response(JSON.stringify({ data: { status: "reserved" } }), {
              status: 200,
              headers: { "Content-Type": "application/json" },
            });
      });

    const result = await apiRequest<{ data: { status: string } }>(
      "/inventory/1/reserve",
      { method: "POST" },
    );

    expect(result.data.status).toBe("reserved");
    expect(fetchMock).toHaveBeenCalledTimes(3);
    expect(fetchMock.mock.calls[2][1]?.headers).toMatchObject({
      "X-XSRF-TOKEN": "fresh token",
    });
  });
});
