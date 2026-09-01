// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { DiscordSettings } from "../types/models";
import { DiscordSettingsPanel } from "../features/discord/DiscordSettingsPanel";
import * as discordApi from "../features/discord/discord-api";

vi.mock("../features/discord/discord-api", () => ({
  loadDiscordSettings: vi.fn(),
  createDiscordSetupCode: vi.fn(),
  createDiscordLinkCode: vi.fn(),
  connectDiscordDemo: vi.fn(),
  autoCreateDiscordChannels: vi.fn(),
  saveDiscordChannels: vi.fn(),
  sendDiscordTestNotification: vi.fn(),
  disconnectDiscord: vi.fn(),
}));

const disconnected: DiscordSettings = {
  configured: false,
  test_mode: true,
  connected: false,
  installation: null,
  available_channels: [],
  channel_sync_error: null,
  user_link: { linked: false, discord_username: null, linked_at: null },
  purposes: [
    { value: "commands", label: "คำสั่งทั่วไป" },
    { value: "system", label: "ระบบและข้อผิดพลาด" },
    { value: "sales", label: "รายการขาย" },
    { value: "reservations", label: "รายการจอง" },
    { value: "inventory", label: "คลังไอดี" },
  ],
};

const connected: DiscordSettings = {
  ...disconnected,
  configured: true,
  connected: true,
  installation: {
    guild_id: "guild-1",
    guild_name: "ร้านทดสอบ",
    status: "connected",
    installed_at: "2026-08-31T10:00:00+07:00",
    last_verified_at: "2026-08-31T10:00:00+07:00",
    channels: disconnected.purposes.map((purpose, index) => ({
      purpose: purpose.value,
      channel_id: `channel-${index + 1}`,
      channel_name: purpose.label,
      enabled: true,
    })),
  },
  available_channels: disconnected.purposes.map((purpose, index) => ({
    id: `channel-${index + 1}`,
    name: purpose.label,
  })),
};

afterEach(cleanup);

describe("Discord settings", () => {
  beforeEach(() => {
    vi.resetAllMocks();
    vi.mocked(discordApi.loadDiscordSettings).mockResolvedValue(disconnected);
  });

  it("อธิบาย flow เชื่อมต่อและเปิดโหมดจำลองได้โดยยังไม่มีคีย์", async () => {
    const user = userEvent.setup();
    const notify = vi.fn();
    vi.mocked(discordApi.connectDiscordDemo).mockResolvedValue({
      message: "เปิดโหมดจำลอง Discord แล้ว",
    });

    render(
      <DiscordSettingsPanel shopId={1} canManage={true} notify={notify} />,
    );

    expect(
      await screen.findByRole("heading", { name: "Discord ของร้าน" }),
    ).toBeInTheDocument();
    expect(screen.getByText("เพิ่ม Gamory Bot")).toBeInTheDocument();
    expect(screen.getByText("ยืนยันรหัสร้าน")).toBeInTheDocument();
    expect(screen.getByText("สร้างห้องภาษาไทย")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "เริ่มเชื่อมต่อ Discord" }),
    ).toBeDisabled();

    await user.click(
      screen.getByRole("button", { name: "ทดลองด้วยโหมดจำลอง" }),
    );
    expect(discordApi.connectDiscordDemo).toHaveBeenCalledWith(1);
    expect(notify).toHaveBeenCalledWith("เปิดโหมดจำลอง Discord แล้ว");
  });

  it("ผู้ไม่มีสิทธิ์เห็นเหตุผลที่ปุ่มเชื่อมต่อใช้งานไม่ได้", async () => {
    render(
      <DiscordSettingsPanel
        shopId={1}
        canManage={false}
        notify={() => undefined}
      />,
    );

    expect(
      await screen.findByText(
        "ต้องมีสิทธิ์จัดการ Discord ของร้านจึงจะเชื่อมต่อได้",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "ทดลองด้วยโหมดจำลอง" }),
    ).toBeDisabled();
  });

  it("แยกห้องคำสั่งทั่วไปและแสดงคำสั่งภาษาไทย", async () => {
    vi.mocked(discordApi.loadDiscordSettings).mockResolvedValue(connected);

    render(
      <DiscordSettingsPanel
        shopId={1}
        canManage={true}
        notify={() => undefined}
      />,
    );

    expect(
      await screen.findByRole("heading", {
        name: "ห้องคำสั่งและการแจ้งเตือน",
      }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("ห้องคำสั่งทั่วไป")).toHaveValue("channel-1");
    expect(screen.getAllByRole("combobox")).toHaveLength(5);
    expect(screen.getAllByText("/ร้าน ช่วยเหลือ")).not.toHaveLength(0);
    expect(screen.getByText("/ร้าน ปิดการขาย")).toBeInTheDocument();
    expect(screen.getByText("/ร้าน เพิ่มไอดี")).toBeInTheDocument();
    expect(
      screen.getByText(/คำสั่งเพิ่มไอดีไม่รับชื่อผู้ใช้หรือรหัสผ่าน/),
    ).toBeInTheDocument();
    expect(screen.getByText(/จะทำงานเฉพาะห้องนี้เท่านั้น/)).toBeInTheDocument();
  });
});
