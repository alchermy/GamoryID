import { useCallback, useEffect, useMemo, useState } from "react";
import {
  BellRing,
  Bot,
  Check,
  CircleAlert,
  Copy,
  ExternalLink,
  Link2,
  ListChecks,
  MessageSquareCode,
  Plus,
  RefreshCw,
  Save,
  Server,
  ShieldCheck,
  Unplug,
} from "lucide-react";
import { writeClipboard } from "../../shared/lib/clipboard";
import { useModalLayer } from "../../shared/hooks/useModalLayer";
import { AsyncError } from "../../shared/ui/async-state";
import { DialogHead } from "../../shared/ui/form-controls";
import type { DiscordOneTimeCode, DiscordSettings } from "../../types/models";
import {
  autoCreateDiscordChannels,
  connectDiscordDemo,
  createDiscordLinkCode,
  createDiscordSetupCode,
  disconnectDiscord,
  loadDiscordSettings,
  saveDiscordChannels,
  sendDiscordTestNotification,
} from "./discord-api";

type ChannelDraft = Record<string, string>;

const DISCORD_COMMAND_GUIDE = [
  ["/ร้าน สรุป", "สมาชิกที่เชื่อมบัญชี"],
  ["/ร้าน รายการ", "จัดการสต็อก หรือ จองและขาย"],
  ["/ร้าน จอง", "จองและขาย"],
  ["/ร้าน ยกเลิกจอง", "จองและขาย"],
  ["/ร้าน ปิดการขาย", "จองและขาย"],
  ["/ร้าน โน้ต", "จัดการสต็อก หรือ จองและขาย"],
  ["/ร้าน เพิ่มไอดี", "จัดการสต็อก"],
  ["/ร้าน ช่วยเหลือ", "สมาชิกที่เชื่อมบัญชี"],
] as const;

function formatDate(value: string | null): string {
  if (!value) return "–";
  return new Intl.DateTimeFormat("th-TH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function OneTimeCode({
  code,
  command,
  onCopy,
}: {
  code: DiscordOneTimeCode;
  command: "setup" | "link";
  onCopy: (value: string) => void;
}) {
  const commandName = command === "setup" ? "ตั้งค่า" : "เชื่อมบัญชี";
  const commandText = `/ร้าน ${commandName} ${code.code}`;

  return (
    <div className="discord-code" aria-label="รหัสใช้ครั้งเดียว">
      <div>
        <span>รหัสหมดอายุ {formatDate(code.expires_at)}</span>
        <strong>{code.code}</strong>
        <code>{commandText}</code>
      </div>
      <button
        type="button"
        className="button"
        onClick={() => onCopy(commandText)}
      >
        <Copy size={16} />
        คัดลอกคำสั่ง
      </button>
    </div>
  );
}

export function DiscordSettingsPanel({
  shopId,
  canManage,
  notify,
}: {
  shopId?: number;
  canManage: boolean;
  notify: (message: string) => void;
}) {
  const [settings, setSettings] = useState<DiscordSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [setupCode, setSetupCode] = useState<DiscordOneTimeCode | null>(null);
  const [linkCode, setLinkCode] = useState<DiscordOneTimeCode | null>(null);
  const [channelDraft, setChannelDraft] = useState<ChannelDraft>({});
  const [disconnectOpen, setDisconnectOpen] = useState(false);
  useModalLayer(disconnectOpen ? "discord-disconnect" : null);

  const refresh = useCallback(
    async (signal?: AbortSignal) => {
      if (!shopId) return;
      setLoading(true);
      setError("");
      try {
        const next = await loadDiscordSettings(shopId, signal);
        setSettings(next);
        setChannelDraft(
          Object.fromEntries(
            (next.installation?.channels ?? []).map((channel) => [
              channel.purpose,
              channel.channel_id,
            ]),
          ),
        );
        if (next.connected) setSetupCode(null);
      } catch (cause) {
        if (cause instanceof Error && cause.name !== "AbortError")
          setError(cause.message);
      } finally {
        if (!signal?.aborted) setLoading(false);
      }
    },
    [shopId],
  );

  useEffect(() => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => void refresh(controller.signal), 0);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [refresh]);

  const availableChannels = useMemo(() => {
    const channels = new Map(
      (settings?.available_channels ?? []).map((channel) => [
        channel.id,
        channel,
      ]),
    );
    for (const channel of settings?.installation?.channels ?? [])
      channels.set(channel.channel_id, {
        id: channel.channel_id,
        name: channel.channel_name,
      });
    return [...channels.values()];
  }, [settings]);
  const commandPurpose = settings?.purposes.find(
    (purpose) => purpose.value === "commands",
  );
  const notificationPurposes =
    settings?.purposes.filter((purpose) => purpose.value !== "commands") ?? [];

  const run = async (
    key: string,
    action: () => Promise<{ message: string }>,
  ) => {
    if (busy) return;
    setBusy(key);
    setError("");
    try {
      const result = await action();
      notify(result.message);
      await refresh();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "ทำรายการไม่สำเร็จ");
    } finally {
      setBusy(null);
    }
  };

  const startConnection = async () => {
    if (!shopId || busy) return;
    setBusy("setup");
    setError("");
    try {
      setSetupCode(await createDiscordSetupCode(shopId));
    } catch (cause) {
      setError(
        cause instanceof Error ? cause.message : "สร้างรหัสเชื่อมร้านไม่สำเร็จ",
      );
    } finally {
      setBusy(null);
    }
  };

  const generateLinkCode = async () => {
    if (!shopId || busy) return;
    setBusy("link");
    setError("");
    try {
      setLinkCode(await createDiscordLinkCode(shopId));
    } catch (cause) {
      setError(
        cause instanceof Error
          ? cause.message
          : "สร้างรหัสเชื่อมบัญชีไม่สำเร็จ",
      );
    } finally {
      setBusy(null);
    }
  };

  const saveChannels = async () => {
    if (!shopId || !settings) return;
    const channels = settings.purposes.map((purpose) => ({
      purpose: purpose.value,
      channel_id: channelDraft[purpose.value] ?? "",
    }));
    if (channels.some((channel) => !channel.channel_id)) {
      setError(
        "กรุณาเลือกห้องคำสั่งและห้องแจ้งเตือนให้ครบทั้ง 5 ประเภทก่อนบันทึก",
      );
      return;
    }
    await run("save", () => saveDiscordChannels(shopId, channels));
  };

  const copyCommand = async (value: string) => {
    try {
      await writeClipboard(value);
      notify("คัดลอกคำสั่งแล้ว");
    } catch {
      setError("เบราว์เซอร์ไม่อนุญาตให้คัดลอก กรุณาเลือกข้อความและคัดลอกเอง");
    }
  };

  if (!shopId) return null;
  if (loading && !settings)
    return (
      <section className="panel discord-panel">
        <div className="discord-loading" role="status">
          <RefreshCw size={20} className="spin" />
          กำลังตรวจสอบการเชื่อมต่อ Discord…
        </div>
      </section>
    );
  if (!settings)
    return (
      <section className="panel discord-panel">
        <AsyncError
          error={error || "ไม่สามารถโหลดการตั้งค่า Discord ได้"}
          retry={() => void refresh()}
        />
      </section>
    );

  return (
    <div className="discord-layout">
      <section className="panel discord-panel" aria-labelledby="discord-title">
        <div className="panel-head discord-panel-head">
          <span className="discord-brand-icon" aria-hidden="true">
            <MessageSquareCode size={22} />
          </span>
          <div>
            <h2 id="discord-title">Discord ของร้าน</h2>
            <small>
              จัดการสต็อกและรับการแจ้งเตือนจากร้านนี้ในเซิร์ฟเวอร์ของคุณ
            </small>
          </div>
          <span
            className={`discord-connection-badge ${settings.connected ? "connected" : "disconnected"}`}
          >
            {settings.connected ? <Check size={14} /> : <Unplug size={14} />}
            {settings.connected ? "เชื่อมต่อแล้ว" : "ยังไม่เชื่อมต่อ"}
          </span>
        </div>

        {error ? (
          <div className="discord-inline-alert" role="alert">
            <CircleAlert size={18} />
            <span>{error}</span>
            <button
              type="button"
              className="button ghost"
              onClick={() => setError("")}
            >
              ปิด
            </button>
          </div>
        ) : null}

        {!settings.connected ? (
          <div className="discord-connect-body">
            <div className="discord-intro">
              <div>
                <span className="discord-kicker">GAMORY BOT</span>
                <h3>จัดการร้านจาก Discord ที่คุณใช้อยู่ทุกวัน</h3>
                <p>
                  เพิ่มบอทหนึ่งครั้ง
                  จากนั้นพนักงานค้นหาไอดีด้วยแท็กได้โดยข้อมูลสำคัญจะแสดงเฉพาะผู้สั่งคำสั่ง
                </p>
              </div>
              <Bot size={72} aria-hidden="true" />
            </div>
            <ol className="discord-steps" aria-label="ขั้นตอนเชื่อมต่อ">
              <li>
                <span>1</span>
                <div>
                  <strong>เพิ่ม Gamory Bot</strong>
                  <small>เลือกเซิร์ฟเวอร์ของร้าน</small>
                </div>
              </li>
              <li>
                <span>2</span>
                <div>
                  <strong>ยืนยันรหัสร้าน</strong>
                  <small>ใช้คำสั่ง /ร้าน ตั้งค่า</small>
                </div>
              </li>
              <li>
                <span>3</span>
                <div>
                  <strong>สร้างห้องภาษาไทย</strong>
                  <small>แยกห้องคำสั่งและการแจ้งเตือนให้ชัดเจน</small>
                </div>
              </li>
            </ol>

            {!settings.configured && !settings.test_mode ? (
              <div className="discord-config-note" role="status">
                <ShieldCheck size={19} />
                <div>
                  <strong>รอเปิดใช้งาน Discord App</strong>
                  <span>
                    ผู้ดูแลระบบต้องใส่ Application ID, Public Key และ Bot Token
                    บนเซิร์ฟเวอร์ก่อน
                  </span>
                </div>
              </div>
            ) : null}

            {setupCode ? (
              <div className="discord-setup-card">
                <div className="discord-setup-heading">
                  <div>
                    <strong>รหัสเชื่อมร้านพร้อมแล้ว</strong>
                    <span>
                      ใช้คำสั่งนี้สำหรับเชื่อมร้านครั้งแรก
                      จากนั้นระบบจะกำหนดให้คำสั่งทั่วไปทำงานเฉพาะห้องที่สร้างไว้
                    </span>
                  </div>
                  {setupCode.install_url ? (
                    <a
                      className="button blue"
                      href={setupCode.install_url}
                      target="_blank"
                      rel="noreferrer"
                    >
                      <ExternalLink size={16} /> เพิ่มบอทใน Discord
                    </a>
                  ) : null}
                </div>
                <OneTimeCode
                  code={setupCode}
                  command="setup"
                  onCopy={(value) => void copyCommand(value)}
                />
                <button
                  type="button"
                  className="button"
                  onClick={() => void refresh()}
                  disabled={loading}
                >
                  <RefreshCw size={16} className={loading ? "spin" : ""} />{" "}
                  ตรวจสอบสถานะ
                </button>
              </div>
            ) : (
              <div className="discord-connect-actions">
                <button
                  type="button"
                  className="button blue"
                  onClick={() => void startConnection()}
                  disabled={!canManage || !settings.configured || busy !== null}
                >
                  <Link2 size={17} />{" "}
                  {busy === "setup" ? "กำลังเตรียม…" : "เริ่มเชื่อมต่อ Discord"}
                </button>
                {settings.test_mode ? (
                  <button
                    type="button"
                    className="button"
                    onClick={() =>
                      void run("demo", () => connectDiscordDemo(shopId))
                    }
                    disabled={!canManage || busy !== null}
                  >
                    <Bot size={17} />{" "}
                    {busy === "demo" ? "กำลังเปิด…" : "ทดลองด้วยโหมดจำลอง"}
                  </button>
                ) : null}
                {!canManage ? (
                  <span className="discord-permission-note">
                    ต้องมีสิทธิ์จัดการ Discord ของร้านจึงจะเชื่อมต่อได้
                  </span>
                ) : null}
              </div>
            )}
          </div>
        ) : (
          <div className="discord-connected-body">
            <section
              className="discord-server-card"
              aria-labelledby="discord-server-title"
            >
              <span className="discord-server-icon">
                <Server size={22} />
              </span>
              <div>
                <small id="discord-server-title">เซิร์ฟเวอร์ที่เชื่อมต่อ</small>
                <strong>{settings.installation?.guild_name}</strong>
                <span>
                  ตรวจสอบล่าสุด{" "}
                  {formatDate(settings.installation?.last_verified_at ?? null)}
                </span>
              </div>
              <button
                type="button"
                className="button"
                onClick={() => void refresh()}
                disabled={loading}
              >
                <RefreshCw size={16} className={loading ? "spin" : ""} /> รีเฟรช
              </button>
            </section>

            {settings.channel_sync_error ? (
              <div className="discord-inline-alert warning" role="status">
                <CircleAlert size={18} />
                <span>{settings.channel_sync_error}</span>
              </div>
            ) : null}

            <section
              className="discord-section"
              aria-labelledby="discord-channel-title"
            >
              <div className="discord-section-head">
                <div>
                  <span className="discord-section-icon">
                    <BellRing size={18} />
                  </span>
                  <div>
                    <h3 id="discord-channel-title">
                      ห้องคำสั่งและการแจ้งเตือน
                    </h3>
                    <p>
                      ซิงก์คำสั่งล่าสุด กำหนดห้องใช้งานให้ชัดเจน
                      และแยกเหตุการณ์สำคัญเพื่อให้ทีมติดตามง่าย
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  className="button"
                  onClick={() =>
                    void run("create-channels", () =>
                      autoCreateDiscordChannels(shopId),
                    )
                  }
                  disabled={!canManage || busy !== null}
                >
                  <Plus size={16} />{" "}
                  {busy === "create-channels"
                    ? "กำลังซิงก์…"
                    : "ซิงก์ห้องและคำสั่ง"}
                </button>
              </div>
              {commandPurpose ? (
                <div className="discord-command-channel">
                  <span className="discord-section-icon" aria-hidden="true">
                    <MessageSquareCode size={18} />
                  </span>
                  <div>
                    <label
                      className="discord-channel-field"
                      htmlFor="discord-command-channel"
                    >
                      <span>ห้องคำสั่งทั่วไป</span>
                      {/* Native select is deliberate here: Discord owns the short channel list and native keyboard behavior is preferable. */}
                      <select
                        id="discord-command-channel"
                        value={channelDraft[commandPurpose.value] ?? ""}
                        onChange={(event) =>
                          setChannelDraft((current) => ({
                            ...current,
                            [commandPurpose.value]: event.target.value,
                          }))
                        }
                        disabled={!canManage || availableChannels.length === 0}
                      >
                        <option value="">เลือกห้อง Discord</option>
                        {availableChannels.map((channel) => (
                          <option key={channel.id} value={channel.id}>
                            #{channel.name}
                          </option>
                        ))}
                      </select>
                    </label>
                    <p>
                      การเชื่อมบัญชีและคำสั่งจัดการร้านทั้งหมดจะทำงานเฉพาะห้องนี้เท่านั้น
                    </p>
                  </div>
                </div>
              ) : null}
              <div className="discord-channel-subhead">
                <strong>ห้องรับการแจ้งเตือน</strong>
                <span>เลือกว่าแต่ละเหตุการณ์จะส่งไปที่ห้องใด</span>
              </div>
              <div className="discord-channel-grid">
                {notificationPurposes.map((purpose) => (
                  <label className="discord-channel-field" key={purpose.value}>
                    <span>{purpose.label}</span>
                    {/* Native select is deliberate here: channel names are short and platform-owned popup geometry is acceptable. */}
                    <select
                      value={channelDraft[purpose.value] ?? ""}
                      onChange={(event) =>
                        setChannelDraft((current) => ({
                          ...current,
                          [purpose.value]: event.target.value,
                        }))
                      }
                      disabled={!canManage || availableChannels.length === 0}
                    >
                      <option value="">เลือกห้อง Discord</option>
                      {availableChannels.map((channel) => (
                        <option key={channel.id} value={channel.id}>
                          #{channel.name}
                        </option>
                      ))}
                    </select>
                  </label>
                ))}
              </div>
              <div className="discord-section-actions">
                <button
                  type="button"
                  className="button blue"
                  onClick={() => void saveChannels()}
                  disabled={
                    !canManage ||
                    busy !== null ||
                    availableChannels.length === 0
                  }
                >
                  <Save size={16} />{" "}
                  {busy === "save" ? "กำลังบันทึก…" : "บันทึกห้องทั้งหมด"}
                </button>
                <button
                  type="button"
                  className="button"
                  onClick={() =>
                    void run("test", () => sendDiscordTestNotification(shopId))
                  }
                  disabled={!canManage || busy !== null || !channelDraft.system}
                >
                  <BellRing size={16} />{" "}
                  {busy === "test" ? "กำลังส่ง…" : "ส่งข้อความทดสอบ"}
                </button>
              </div>
            </section>

            <section
              className="discord-section"
              aria-labelledby="discord-account-title"
            >
              <div className="discord-section-head compact">
                <div>
                  <span className="discord-section-icon">
                    <ShieldCheck size={18} />
                  </span>
                  <div>
                    <h3 id="discord-account-title">บัญชี Discord ของฉัน</h3>
                    <p>
                      เชื่อมบัญชีเพื่อให้ระบบตรวจสิทธิ์ก่อนใช้คำสั่งจัดการร้านในห้องคำสั่งทั่วไป
                    </p>
                  </div>
                </div>
                {settings.user_link.linked ? (
                  <span className="discord-linked">
                    <Check size={14} /> เชื่อมแล้ว ·{" "}
                    {settings.user_link.discord_username}
                  </span>
                ) : null}
              </div>
              {linkCode ? (
                <OneTimeCode
                  code={linkCode}
                  command="link"
                  onCopy={(value) => void copyCommand(value)}
                />
              ) : (
                <button
                  type="button"
                  className="button"
                  onClick={() => void generateLinkCode()}
                  disabled={busy !== null}
                >
                  <Link2 size={16} />{" "}
                  {settings.user_link.linked
                    ? "สร้างรหัสเชื่อมใหม่"
                    : "สร้างรหัสเชื่อมบัญชี"}
                </button>
              )}
              <div className="discord-command-preview">
                <code>/ร้าน ช่วยเหลือ</code>
                <span>
                  บอทจะแสดงเฉพาะคำสั่งที่บัญชีของคุณได้รับอนุญาต
                  และตอบกลับแบบส่วนตัว
                </span>
              </div>
            </section>

            <section
              className="discord-section"
              aria-labelledby="discord-command-list-title"
            >
              <div className="discord-section-head compact">
                <div>
                  <span className="discord-section-icon">
                    <ListChecks size={18} />
                  </span>
                  <div>
                    <h3 id="discord-command-list-title">คำสั่งจัดการร้าน</h3>
                    <p>
                      ทุกคำสั่งใช้ได้เฉพาะห้องคำสั่งทั่วไป
                      และตรวจสิทธิ์สมาชิกจากเมนูทีมและสิทธิ์ทุกครั้ง
                    </p>
                  </div>
                </div>
              </div>
              <div className="discord-command-catalog">
                {DISCORD_COMMAND_GUIDE.map(([command, permission]) => (
                  <div key={command}>
                    <code>{command}</code>
                    <span>{permission}</span>
                  </div>
                ))}
              </div>
              <div className="discord-command-safety" role="note">
                <ShieldCheck size={17} aria-hidden="true" />
                <span>
                  คำสั่งเพิ่มไอดีไม่รับชื่อผู้ใช้หรือรหัสผ่าน
                  ข้อมูลลับต้องเพิ่มจากหน้ารายละเอียดไอดีใน GamoryID เท่านั้น
                </span>
              </div>
            </section>

            {canManage ? (
              <div className="discord-danger-zone">
                <div>
                  <strong>ยกเลิกการเชื่อมต่อ</strong>
                  <span>บอทจะออกจากเซิร์ฟเวอร์และหยุดรับคำสั่งของร้านนี้</span>
                </div>
                <button
                  type="button"
                  className="button danger"
                  onClick={() => setDisconnectOpen(true)}
                >
                  <Unplug size={16} /> ยกเลิกการเชื่อมต่อ
                </button>
              </div>
            ) : null}
          </div>
        )}
      </section>

      {disconnectOpen ? (
        <div className="dialog-backdrop" role="presentation">
          <section
            className="dialog management-dialog"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="discord-disconnect-title"
            aria-describedby="discord-disconnect-description"
          >
            <DialogHead
              id="discord-disconnect-title"
              title="ยกเลิกการเชื่อมต่อ Discord?"
              subtitle="คำสั่งและการแจ้งเตือนของร้านนี้จะหยุดทำงานทันที"
              close={() => setDisconnectOpen(false)}
            />
            <div className="dialog-body">
              <p id="discord-disconnect-description">
                Gamory Bot จะออกจากเซิร์ฟเวอร์{" "}
                {settings.installation?.guild_name}{" "}
                และการตั้งค่าห้องทั้งหมดจะถูกลบจาก GamoryID
              </p>
            </div>
            <div className="dialog-actions">
              <button
                type="button"
                className="button"
                data-dialog-initial-focus
                onClick={() => setDisconnectOpen(false)}
              >
                เก็บการเชื่อมต่อไว้
              </button>
              <button
                type="button"
                className="button danger"
                disabled={busy !== null}
                onClick={() =>
                  void run("disconnect", () => disconnectDiscord(shopId)).then(
                    () => setDisconnectOpen(false),
                  )
                }
              >
                {busy === "disconnect" ? "กำลังยกเลิก…" : "ยกเลิกการเชื่อมต่อ"}
              </button>
            </div>
          </section>
        </div>
      ) : null}
    </div>
  );
}
