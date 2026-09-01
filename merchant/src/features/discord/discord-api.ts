import { shopRequest } from "../../api";
import type { DiscordOneTimeCode, DiscordSettings } from "../../types/models";

export async function loadDiscordSettings(
  shopId: number,
  signal?: AbortSignal,
): Promise<DiscordSettings> {
  const response = await shopRequest<{ data: DiscordSettings }>(
    "/discord/settings",
    shopId,
    { signal },
  );
  return response.data;
}

export async function createDiscordSetupCode(
  shopId: number,
): Promise<DiscordOneTimeCode> {
  const response = await shopRequest<{ data: DiscordOneTimeCode }>(
    "/discord/setup-code",
    shopId,
    { method: "POST", body: JSON.stringify({}) },
  );
  return response.data;
}

export async function createDiscordLinkCode(
  shopId: number,
): Promise<DiscordOneTimeCode> {
  const response = await shopRequest<{ data: DiscordOneTimeCode }>(
    "/discord/link-code",
    shopId,
    { method: "POST", body: JSON.stringify({}) },
  );
  return response.data;
}

export function connectDiscordDemo(
  shopId: number,
): Promise<{ message: string }> {
  return shopRequest("/discord/demo-connect", shopId, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export function autoCreateDiscordChannels(
  shopId: number,
): Promise<{ message: string }> {
  return shopRequest("/discord/channels/auto-create", shopId, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export function saveDiscordChannels(
  shopId: number,
  channels: Array<{ purpose: string; channel_id: string }>,
): Promise<{ message: string }> {
  return shopRequest("/discord/channels", shopId, {
    method: "PUT",
    body: JSON.stringify({ channels }),
  });
}

export function sendDiscordTestNotification(
  shopId: number,
): Promise<{ message: string }> {
  return shopRequest("/discord/test-notification", shopId, {
    method: "POST",
    body: JSON.stringify({ purpose: "system" }),
  });
}

export function disconnectDiscord(
  shopId: number,
): Promise<{ message: string }> {
  return shopRequest("/discord/disconnect", shopId, { method: "DELETE" });
}
