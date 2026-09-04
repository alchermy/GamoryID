import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import type { FormEvent } from "react";
import { useLocation, useNavigate, useOutletContext } from "react-router-dom";
import {
  Bell,
  BookOpen,
  Box,
  Check,
  CircleHelp,
  Clock3,
  Download,
  FileUp,
  House,
  Menu,
  PackagePlus,
  Rocket,
  Search,
  ShoppingBag,
  Tag,
  UserRound,
  WalletCards,
  X,
} from "lucide-react";
import { apiAssetUrl, apiRequest, prepareCsrf, shopRequest } from "../../api";
import {
  PAGE_PATHS,
  PATH_PAGES,
  mainNavigation,
  managementNavigation,
  permissionOptions,
} from "../../config/navigation";
import { buildInventoryCopyText } from "../../inventory-copy";
import { DEFAULT_COPY_FOOTER, initialInventoryItems } from "../inventory/data";
import { createIdempotencyKey, money } from "../../shared/lib/format";
import { writeClipboard } from "../../shared/lib/clipboard";
import { shrinkImage } from "../../shared/lib/image";
import { useModalLayer } from "../../shared/hooks/useModalLayer";
import type {
  BillingHistory,
  CustomerRecord,
  DashboardData,
  InventoryItem,
  InventoryResponse,
  InventoryStatus,
  MerchantPage,
  Paged,
  Payment,
  Plan,
  SalePayload,
  SaleRecord,
  SalesSeries,
  SessionUser,
  ShopDetails,
  TeamMember,
  ViewSeries,
} from "../../types/models";
import { ImportPanel } from "../imports/ImportPanel";
import {
  TeamPanel,
  CreateStaffDialog,
  EditStaffDialog,
  ResetPasswordDialog,
  PermissionDialog,
  RemoveMemberDialog,
} from "../team/team-components";
import {
  BillingPanel,
  PurchasePlanDialog,
  AutoRenewDialog,
  TopUpDialog,
} from "../billing/billing-components";
import { TransactionsPanel } from "../transactions/TransactionsPanel";
import { ManualPanel } from "../manual/ManualPanel";
import { OnboardingPanel } from "../onboarding/OnboardingPanel";
import { OnboardingCard } from "../onboarding/OnboardingCard";
import { buildOnboardingSteps } from "../onboarding/steps";
import type { OnboardingCtaAction } from "../onboarding/steps";
import { loadDiscordSettings } from "../discord/discord-api";
import { ExportDialog } from "./ExportDialog";
import { ActivityPanel } from "../activity/ActivityPanel";
import { AnalyticsPanel } from "../analytics/AnalyticsPanel";
import { SettingsPanel } from "../settings/SettingsPanel";
import { DashboardPanel, Kpi } from "../dashboard/DashboardPanel";
import {
  AddDialog,
  ArchiveDialog,
  EditDialog,
  InventoryDetailPage,
  InventoryNoteDialog,
  InventoryPanel,
  SellDialog,
} from "../inventory/inventory-components";
import type { InventoryMediaDraft } from "../inventory/inventory-media-model";
import { CustomersPanel, SalesPanel } from "../history/history-panels";
import { DiscordSettingsPanel } from "../discord/DiscordSettingsPanel";
import { SaleDetailPage } from "../sales/SaleDetailPage";

const inventoryUpdatedFormatter = new Intl.DateTimeFormat("th-TH", {
  dateStyle: "short",
  timeStyle: "short",
});

function mapInventoryItem(
  record: InventoryResponse["data"][number],
): InventoryItem {
  return {
    id: record.id,
    tag: record.tag,
    title: record.title,
    riotId: record.riot_id ?? record.title,
    username: record.username ?? "–",
    rank: record.rank ?? "–",
    level: record.level ?? 0,
    skins: record.skin_count,
    cost: Number(record.cost ?? 0),
    price: Number(record.list_price),
    status: record.status,
    updated: inventoryUpdatedFormatter.format(new Date(record.updated_at)),
    description: record.description,
    notes: record.notes,
    hasCredentials: record.has_credentials,
    viewCount: record.view_count ?? 0,
    media: (record.media ?? []).map((media) => ({
      id: media.id,
      role: media.role,
      originalName: media.original_name,
      mimeType: media.mime_type,
      sizeBytes: media.size_bytes,
      sortOrder: media.sort_order,
      url: apiAssetUrl(media.url),
    })),
  };
}

export function MerchantApp() {
  const authenticatedSession = useOutletContext<SessionUser | undefined>();
  const location = useLocation();
  const navigate = useNavigate();
  const saleDetailMatch = location.pathname.match(/^\/sales\/(\d+)$/);
  const saleDetailId = saleDetailMatch ? Number(saleDetailMatch[1]) : null;
  const page = saleDetailId
    ? "sales"
    : (PATH_PAGES.get(location.pathname) ?? "dashboard");
  const inventoryDetailId =
    page === "inventory"
      ? Number(new URLSearchParams(location.search).get("item")) || null
      : null;
  const [items, setInventoryItems] = useState(initialInventoryItems),
    [query, setQuery] = useState(""),
    [searchComposing, setSearchComposing] = useState(false),
    [status, setInventoryStatus] = useState<"all" | InventoryStatus>("all"),
    [dialog, setDialog] = useState<"add" | "edit" | "note" | "sell" | null>(
      null,
    ),
    [selected, setSelected] = useState<InventoryItem | null>(null),
    [noteCandidate, setNoteCandidate] = useState<InventoryItem | null>(null),
    [saleInventoryItem, setSaleInventoryItem] = useState<InventoryItem | null>(
      null,
    ),
    [archiveCandidate, setArchiveCandidate] = useState<InventoryItem | null>(
      null,
    ),
    [inventoryBusy, setInventoryBusy] = useState(false),
    [toast, setToast] = useState<{
      message: string;
      tone: "success" | "error";
    } | null>(null);
  const [session, setSession] = useState<SessionUser | null>(
      authenticatedSession ?? null,
    ),
    [dashboard, setDashboardData] = useState<DashboardData | null>(null),
    [storefrontViews, setStorefrontViews] = useState<ViewSeries | null>(null),
    [viewGranularity, setViewGranularity] = useState<
      "day" | "month" | "year"
    >("day"),
    [salesReport, setSalesReport] = useState<SalesSeries | null>(null),
    [salesGranularity, setSalesGranularity] = useState<
      "day" | "month" | "year"
    >("day"),
    [loadError, setLoadError] = useState("");
  const [sales, setSales] = useState<SaleRecord[]>([]),
    [customers, setCustomers] = useState<CustomerRecord[]>([]),
    [historyLoading, setHistoryLoading] = useState(false),
    [historyError, setHistoryError] = useState(""),
    [historyRevision, setHistoryRevision] = useState(0);
  const [shopDetails, setShopDetails] = useState<ShopDetails | null>(null),
    [team, setTeam] = useState<TeamMember[]>([]),
    [plans, setPlans] = useState<Plan[]>([]),
    [paymentBusy, setPaymentBusy] = useState(false),
    [managementLoading, setManagementLoading] = useState(false),
    [managementError, setManagementError] = useState(""),
    [managementRevision, setManagementRevision] = useState(0),
    [managementDialog, setManagementDialog] = useState<
      | "createStaff"
      | "editStaff"
      | "resetPassword"
      | "remove"
      | "permissions"
      | "purchase"
      | "autoRenew"
      | "topUp"
      | "export"
      | null
    >(null),
    [selectedMember, setSelectedMember] = useState<TeamMember | null>(null),
    [pendingPermissions, setPendingPermissions] = useState<{
      member: TeamMember;
      permissions: string[];
    } | null>(null),
    [pendingPlan, setPendingPlan] = useState<Plan | null>(null),
    [pendingCycle, setPendingCycle] = useState<"monthly" | "yearly">("monthly");
  const [billingHistory, setBillingHistory] = useState<BillingHistory | null>(
      null,
    ),
    [transactionsLoading, setTransactionsLoading] = useState(false),
    [transactionsError, setTransactionsError] = useState(""),
    [transactionsRevision, setTransactionsRevision] = useState(0);
  const [discordConnected, setDiscordConnected] = useState(false);
  const searchRef = useRef<HTMLInputElement>(null);
  const toastTimer = useRef<number | null>(null);
  useModalLayer(
    dialog ?? (archiveCandidate ? "archive" : null) ?? managementDialog,
  );
  const shop = session?.shops.find(
    (candidate) => candidate.id === session.current_shop_id,
  );
  const hasShopPermission = (permission: string) =>
    !shop ||
    shop.role === "owner" ||
    shop.permissions.includes(permission) === true;
  const planFeature = (key: string) =>
    shopDetails?.entitlements?.effective_plan.features[
      key as keyof typeof shopDetails.entitlements.effective_plan.features
    ] ?? true;
  const canViewAnalytics = planFeature("analytics");
  const onboardingDismissed = Boolean(shopDetails?.onboarding_dismissed_at);
  const onboardingSteps = useMemo(
    () => buildOnboardingSteps({ shopDetails, dashboard, discordConnected }),
    [shopDetails, dashboard, discordConnected],
  );
  const onboardingRequiredLeft = onboardingSteps.filter(
    (step) => step.kind === "required" && !step.done,
  ).length;
  const canAccessManagementPage = (key: MerchantPage) => {
    if (key === "activity")
      return hasShopPermission("team.manage") && planFeature("activity_log");
    if (key === "analytics")
      return (
        planFeature("analytics") &&
        (hasShopPermission("profit.view") || hasShopPermission("team.manage"))
      );
    if (key === "team" || key === "settings")
      return hasShopPermission("team.manage");
    if (key === "billing" || key === "transactions")
      return hasShopPermission("billing.manage");
    if (key === "discord")
      return (
        planFeature("discord") &&
        (hasShopPermission("discord.manage") ||
          hasShopPermission("inventory.manage") ||
          hasShopPermission("inventory.sell"))
      );
    return true;
  };
  const filtered = useMemo(
    () =>
      items.filter((i) => {
        const q = query.trim().toLowerCase();
        return (
          (!q ||
            `${i.tag} ${i.riotId} ${i.username} ${i.rank}`
              .toLowerCase()
              .includes(q)) &&
          (status === "all" || i.status === status)
        );
      }),
    [items, query, status],
  );
  const summary = useMemo(
    () => ({
      available:
        dashboard?.summary.available ??
        items.filter((i) => i.status === "available").length,
      reserved:
        dashboard?.summary.reserved ??
        items.filter((i) => i.status === "reserved").length,
      sold:
        dashboard?.summary.sold_this_month ??
        items.filter((i) => i.status === "sold").length,
      soldTotal:
        dashboard?.summary.sold_total ??
        items.filter((i) => i.status === "sold").length,
      value:
        dashboard?.summary.inventory_value ??
        items
          .filter((i) => i.status === "available" || i.status === "reserved")
          .reduce((n, i) => n + i.cost, 0),
    }),
    [dashboard, items],
  );
  const notify = (message: string) => {
    const tone: "success" | "error" =
      /ไม่สามารถ|ไม่สำเร็จ|ผิดพลาด|กรุณา|หมดอายุ/.test(message)
        ? "error"
        : "success";
    setToast({ message, tone });
    if (toastTimer.current) window.clearTimeout(toastTimer.current);
    toastTimer.current = window.setTimeout(
      () => setToast(null),
      tone === "error" ? 6000 : 4000,
    );
  };
  const syncInventoryMedia = async (
    inventoryId: number,
    media: InventoryMediaDraft,
  ): Promise<InventoryItem | null> => {
    if (!shop) return null;
    const hasChanges =
      media.removedMediaIds.length > 0 ||
      Boolean(media.displayImage) ||
      media.detailImages.length > 0;
    if (!hasChanges) return null;

    for (const mediaId of media.removedMediaIds) {
      await shopRequest(`/inventory/${inventoryId}/media/${mediaId}`, shop.id, {
        method: "DELETE",
      });
    }

    const upload = async (role: "display" | "detail", file: File) => {
      const payload = new FormData();
      payload.append("role", role);
      payload.append("image", await shrinkImage(file, 1600));
      await shopRequest(`/inventory/${inventoryId}/media`, shop.id, {
        method: "POST",
        body: payload,
      });
    };

    if (media.displayImage) await upload("display", media.displayImage);
    for (const detailImage of media.detailImages)
      await upload("detail", detailImage);

    const result = await shopRequest<{
      data: InventoryResponse["data"][number];
    }>(`/inventory/${inventoryId}`, shop.id);
    return mapInventoryItem(result.data);
  };
  const refreshInventory = useCallback(
    async (signal?: AbortSignal) => {
      if (!shop) return;
      try {
        const params = new URLSearchParams({
          per_page: "25",
          sort: "updated_at",
          direction: "desc",
        });
        if (query.trim()) params.set("q", query.trim());
        if (status !== "all") params.set("status", status);
        const result = await shopRequest<InventoryResponse>(
          `/inventory?${params}`,
          shop.id,
          { signal },
        );
        setInventoryItems(result.data.map(mapInventoryItem));
        setLoadError("");
      } catch (error) {
        if (error instanceof Error && error.name !== "AbortError")
          setLoadError(error.message);
        throw error;
      }
    },
    [query, shop, status],
  );
  const refreshDashboardData = useCallback(async () => {
    if (!shop) return;
    const result = await shopRequest<DashboardData>("/dashboard", shop.id);
    setDashboardData(result);
  }, [shop]);
  const refreshStorefrontViews = useCallback(
    async (granularity: "day" | "month" | "year") => {
      if (!shop || !canViewAnalytics) return;
      const result = await shopRequest<ViewSeries>(
        `/storefront/views?granularity=${granularity}`,
        shop.id,
      );
      setStorefrontViews(result);
    },
    [shop, canViewAnalytics],
  );
  const refreshSalesReport = useCallback(
    async (granularity: "day" | "month" | "year") => {
      if (!shop) return;
      const result = await shopRequest<SalesSeries>(
        `/reports/sales?granularity=${granularity}`,
        shop.id,
      );
      setSalesReport(result);
    },
    [shop],
  );
  const refreshHistory = useCallback(async () => {
    if (!shop || !["sales", "customers"].includes(page) || saleDetailId) return;
    const endpoint =
      page === "sales" ? "/sales?per_page=25" : "/customers?per_page=25";
    setHistoryLoading(true);
    setHistoryError("");
    try {
      const result = await shopRequest<Paged<SaleRecord | CustomerRecord>>(
        endpoint,
        shop.id,
      );
      if (page === "sales") setSales(result.data as SaleRecord[]);
      else setCustomers(result.data as CustomerRecord[]);
    } catch (error) {
      setHistoryError(
        error instanceof Error ? error.message : "ไม่สามารถโหลดข้อมูลได้",
      );
    } finally {
      setHistoryLoading(false);
    }
  }, [page, saleDetailId, shop]);
  const refreshManagement = useCallback(async () => {
    if (!shop || !["team", "billing", "settings"].includes(page)) return;
    setManagementLoading(true);
    setManagementError("");
    try {
      const shopResult = await shopRequest<{ data: ShopDetails }>(
        "/shop",
        shop.id,
      );
      setShopDetails(shopResult.data);
      if (page === "team") {
        const teamResult = await shopRequest<{ data: TeamMember[] }>(
          "/team",
          shop.id,
        );
        setTeam(teamResult.data);
      }
      if (page === "billing") {
        const planResult = await apiRequest<{ data: Plan[] }>("/plans");
        setPlans(planResult.data);
      }
    } catch (error) {
      setManagementError(
        error instanceof Error
          ? error.message
          : "ไม่สามารถโหลดข้อมูลจัดการร้านได้",
      );
    } finally {
      setManagementLoading(false);
    }
  }, [page, shop]);
  const refreshTransactions = useCallback(async () => {
    if (!shop || page !== "transactions") return;
    setTransactionsLoading(true);
    setTransactionsError("");
    try {
      const result = await shopRequest<{ data: BillingHistory }>(
        "/billing/history",
        shop.id,
      );
      setBillingHistory(result.data);
    } catch (error) {
      setTransactionsError(
        error instanceof Error
          ? error.message
          : "ไม่สามารถโหลดประวัติธุรกรรมได้",
      );
    } finally {
      setTransactionsLoading(false);
    }
  }, [page, shop]);
  useEffect(() => {
    if (!shop || searchComposing) return;
    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      void refreshInventory(controller.signal).catch(() => undefined);
    }, 300);
    return () => {
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [refreshInventory, searchComposing, shop]);
  useEffect(() => {
    if (!shop) return;
    void refreshDashboardData().catch(() => undefined);
  }, [refreshDashboardData, shop]);
  useEffect(() => {
    if (!shop || page !== "dashboard" || !canViewAnalytics) return;
    void refreshStorefrontViews(viewGranularity).catch(() => undefined);
  }, [refreshStorefrontViews, viewGranularity, page, shop, canViewAnalytics]);
  useEffect(() => {
    if (!shop || page !== "dashboard") return;
    void refreshSalesReport(salesGranularity).catch(() => undefined);
  }, [refreshSalesReport, salesGranularity, page, shop]);
  useEffect(() => {
    if (!shop) return;
    void shopRequest<{ data: ShopDetails }>("/shop", shop.id)
      .then((result) => {
        setShopDetails(result.data);
      })
      .catch(() => undefined);
  }, [shop]);
  useEffect(() => {
    if (!shop || page !== "onboarding" || !shopDetails || !planFeature("discord"))
      return;
    const controller = new AbortController();
    void loadDiscordSettings(shop.id, controller.signal)
      .then((settings) => setDiscordConnected(Boolean(settings.connected)))
      .catch(() => undefined);
    return () => controller.abort();
    // planFeature reads shopDetails; page + shop are the real triggers.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [shop, page, shopDetails?.entitlements]);
  useEffect(() => {
    void refreshHistory();
  }, [historyRevision, refreshHistory]);
  useEffect(() => {
    if (!shop || !inventoryDetailId) return;
    const controller = new AbortController();
    void shopRequest<{ data: InventoryResponse["data"][number] }>(
      `/inventory/${inventoryDetailId}`,
      shop.id,
      { signal: controller.signal },
    )
      .then((result) => {
        const item = mapInventoryItem(result.data);
        setSelected(item);
        setInventoryItems((current) => {
          const exists = current.some((candidate) => candidate.id === item.id);
          return exists
            ? current.map((candidate) =>
                candidate.id === item.id ? item : candidate,
              )
            : [item, ...current];
        });
      })
      .catch((error: unknown) => {
        if (error instanceof Error && error.name === "AbortError") return;
        notify(
          error instanceof Error
            ? error.message
            : "ไม่สามารถเปิดข้อมูลไอดีจากลิงก์ได้",
        );
      });

    return () => controller.abort();
  }, [inventoryDetailId, shop]);
  useEffect(() => {
    void refreshManagement();
  }, [managementRevision, refreshManagement]);
  useEffect(() => {
    void refreshTransactions();
  }, [transactionsRevision, refreshTransactions]);
  const go = (p: MerchantPage, hash?: string) => {
    navigate(PAGE_PATHS[p] + (hash ? `#${hash}` : ""));
    setSelected(null);
    if (p === "inventory")
      window.setTimeout(() => searchRef.current?.focus(), 0);
  };
  const dismissOnboarding = async () => {
    if (!shop || onboardingDismissed) return;
    try {
      const result = await shopRequest<{ data: ShopDetails }>(
        "/onboarding/dismiss",
        shop.id,
        { method: "PUT" },
      );
      setShopDetails(result.data);
      notify("ซ่อนไกด์เริ่มต้นใช้งานแล้ว");
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ไม่สามารถซ่อนไกด์ได้",
      );
    }
  };
  const handleOnboardingCta = (action: OnboardingCtaAction) => {
    switch (action) {
      case "settings-info":
        go("settings", "shop-info");
        break;
      case "branding":
        go("settings", "branding");
        break;
      case "storefront":
        go("settings", "storefront");
        break;
      case "add":
        setDialog("add");
        break;
      case "imports":
        go("imports");
        break;
      case "discord":
        go("discord");
        break;
      case "team":
        go("team");
        break;
      case "billing":
        go("billing");
        break;
    }
  };
  const changeInventoryStatus = async (
    i: InventoryItem,
    next: InventoryStatus,
  ) => {
    if (next === i.status) return;
    if (next === "sold") {
      setSaleInventoryItem(i);
      setDialog("sell");
      return;
    }
    if (
      i.status === "sold" ||
      i.status === "archived" ||
      !["available", "reserved"].includes(next)
    ) {
      notify("สถานะนี้ไม่สามารถเปลี่ยนจากตารางได้");
      return;
    }
    if (inventoryBusy) return;
    setInventoryBusy(true);
    try {
      if (shop) {
        if (i.status === "available" && next === "reserved")
          await shopRequest(`/inventory/${i.id}/reserve`, shop.id, {
            method: "POST",
            body: JSON.stringify({}),
          });
        else if (i.status === "reserved" && next === "available")
          await shopRequest(`/inventory/${i.id}/reserve`, shop.id, {
            method: "DELETE",
          });
        else throw new Error("ไม่สามารถเปลี่ยนเป็นสถานะที่เลือกได้");
        await Promise.all([refreshInventory(), refreshDashboardData()]);
      } else
        setInventoryItems((current) =>
          current.map((item) =>
            item.id === i.id
              ? { ...item, status: next, updated: "เมื่อสักครู่" }
              : item,
          ),
        );
      setSelected((current) =>
        current?.id === i.id
          ? { ...current, status: next, updated: "เมื่อสักครู่" }
          : current,
      );
      notify(
        next === "reserved"
          ? `จอง ${i.tag} แล้ว`
          : `เปลี่ยน ${i.tag} เป็นพร้อมขายแล้ว`,
      );
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ไม่สามารถเปลี่ยนสถานะได้",
      );
    } finally {
      setInventoryBusy(false);
    }
  };
  const reserve = async (i: InventoryItem) => {
    await changeInventoryStatus(i, "reserved");
  };
  const openInventoryNote = (item: InventoryItem) => {
    setNoteCandidate(item);
    setDialog("note");
  };
  const saveInventoryNote = async (notes: string): Promise<string | null> => {
    if (!noteCandidate) return "ไม่พบไอดีที่ต้องการบันทึกโน้ต";
    if (inventoryBusy) return "ระบบกำลังบันทึกรายการก่อนหน้า";
    setInventoryBusy(true);
    try {
      let updated: InventoryItem;
      if (shop) {
        const result = await shopRequest<{
          data: InventoryResponse["data"][number];
        }>(`/inventory/${noteCandidate.id}/note`, shop.id, {
          method: "PATCH",
          body: JSON.stringify({ notes: notes || null }),
        });
        updated = mapInventoryItem(result.data);
      } else {
        updated = {
          ...noteCandidate,
          notes: notes || null,
          updated: "เมื่อสักครู่",
        };
      }
      setInventoryItems((current) =>
        current.map((item) => (item.id === updated.id ? updated : item)),
      );
      setSelected((current) =>
        current?.id === updated.id ? updated : current,
      );
      setNoteCandidate(null);
      setDialog(null);
      notify(
        notes
          ? `บันทึกโน้ต ${updated.tag} แล้ว`
          : `ล้างโน้ต ${updated.tag} แล้ว`,
      );
      return null;
    } catch (error) {
      return error instanceof Error ? error.message : "ไม่สามารถบันทึกโน้ตได้";
    } finally {
      setInventoryBusy(false);
    }
  };
  const sell = (i: InventoryItem) => {
    if (i.status === "sold" || i.status === "archived") return;
    setSaleInventoryItem(i);
    setDialog("sell");
  };
  const confirmSell = async (payload: SalePayload): Promise<string | null> => {
    if (!saleInventoryItem) return "ไม่พบไอดีที่ต้องการขาย";
    if (inventoryBusy) return "ระบบกำลังบันทึกรายการก่อนหน้า";
    setInventoryBusy(true);
    try {
      if (shop) {
        await shopRequest(`/inventory/${saleInventoryItem.id}/sell`, shop.id, {
          method: "POST",
          headers: { "Idempotency-Key": createIdempotencyKey() },
          body: JSON.stringify(payload),
        });
        await Promise.all([refreshInventory(), refreshDashboardData()]);
        setHistoryRevision((value) => value + 1);
      } else
        setInventoryItems((current) =>
          current.map((item) =>
            item.id === saleInventoryItem.id
              ? { ...item, status: "sold", updated: "เมื่อสักครู่" }
              : item,
          ),
        );
      const soldInventoryItem = saleInventoryItem;
      setSelected((current) =>
        current?.id === soldInventoryItem.id
          ? { ...current, status: "sold", updated: "เมื่อสักครู่" }
          : current,
      );
      setDialog(null);
      setSaleInventoryItem(null);
      notify(`บันทึกขาย ${soldInventoryItem.tag} สำเร็จ`);
      return null;
    } catch (error) {
      return error instanceof Error
        ? error.message
        : "ไม่สามารถบันทึกการขายได้";
    } finally {
      setInventoryBusy(false);
    }
  };
  const addInventoryItem = async (
    e: FormEvent<HTMLFormElement>,
    media: InventoryMediaDraft,
  ) => {
    e.preventDefault();
    if (inventoryBusy) return;
    setInventoryBusy(true);
    const d = new FormData(e.currentTarget),
      abc = "23456789ABCDEFGHJKMNPQRSTUVWXYZ";
    let tag =
      "#" +
      Array.from(
        { length: 5 },
        () => abc[Math.floor(Math.random() * abc.length)],
      ).join("");
    const riotId = String(d.get("riot_id") ?? "");
    const username = String(d.get("username") ?? "");
    const description = String(d.get("description") ?? "");
    try {
      if (shop) {
        const result = await shopRequest<{
          data: InventoryResponse["data"][number];
        }>("/inventory", shop.id, {
          method: "POST",
          body: JSON.stringify({
            title: riotId,
            riot_id: riotId,
            username,
            description: description || null,
            rank: d.get("rank") || null,
            level: Number(d.get("level") || 0),
            cost: Number(d.get("cost")),
            list_price: Number(d.get("price")),
            credentials: d.get("password")
              ? { password: d.get("password") }
              : undefined,
          }),
        });
        const createdItem = mapInventoryItem(result.data);
        setInventoryItems((current) => [createdItem, ...current]);
        try {
          const withMedia = await syncInventoryMedia(createdItem.id, media);
          if (withMedia) {
            setInventoryItems((current) =>
              current.map((item) =>
                item.id === withMedia.id ? withMedia : item,
              ),
            );
          }
        } catch {
          setDialog(null);
          go("inventory");
          notify(
            `เพิ่ม ${createdItem.tag} แล้ว แต่บันทึกรูปภาพไม่ครบ กรุณาเปิดแก้ไขเพื่อลองอีกครั้ง`,
          );
          return;
        }
        await refreshDashboardData();
        tag = result.data.tag;
      } else
        setInventoryItems((a) => [
          {
            id: Date.now(),
            tag,
            title: description || riotId,
            description: description || null,
            riotId,
            username,
            rank: String(d.get("rank")),
            level: Number(d.get("level")),
            skins: 0,
            cost: Number(d.get("cost")),
            price: Number(d.get("price")),
            status: "available",
            updated: "เมื่อสักครู่",
            hasCredentials: Boolean(d.get("password")),
            media: [],
          },
          ...a,
        ]);
      setDialog(null);
      go("inventory");
      notify(`เพิ่ม ${tag} เข้าคลังแล้ว`);
    } catch (error) {
      notify(error instanceof Error ? error.message : "ไม่สามารถเพิ่มไอดีได้");
    } finally {
      setInventoryBusy(false);
    }
  };
  const openDetail = (i: InventoryItem) => {
    setSelected(i);
    navigate(`${PAGE_PATHS.inventory}?item=${i.id}`);
  };
  const editInventoryItem = async (
    e: FormEvent<HTMLFormElement>,
    media: InventoryMediaDraft,
  ) => {
    e.preventDefault();
    if (!selected || inventoryBusy) return;
    setInventoryBusy(true);
    const d = new FormData(e.currentTarget),
      riotId = String(d.get("riot_id") ?? ""),
      description = String(d.get("description") ?? ""),
      password = String(d.get("password") ?? "");
    try {
      let updated: InventoryItem;
      if (shop) {
        const result = await shopRequest<{
          data: InventoryResponse["data"][number];
        }>(`/inventory/${selected.id}`, shop.id, {
          method: "PUT",
          body: JSON.stringify({
            title: riotId,
            riot_id: riotId,
            username: d.get("username"),
            description: description || null,
            rank: d.get("rank") || null,
            level: Number(d.get("level") || 0),
            cost: Number(d.get("cost")),
            list_price: Number(d.get("price")),
            credentials: password ? { password } : undefined,
          }),
        });
        updated = mapInventoryItem(result.data);
        const withMedia = await syncInventoryMedia(updated.id, media);
        if (withMedia) updated = withMedia;
      } else
        updated = {
          ...selected,
          title: description || riotId,
          riotId,
          username: String(d.get("username") ?? ""),
          description: description || null,
          rank: String(d.get("rank") ?? ""),
          level: Number(d.get("level") || 0),
          cost: Number(d.get("cost")),
          price: Number(d.get("price")),
          updated: "เมื่อสักครู่",
          hasCredentials: selected.hasCredentials || Boolean(password),
          media: selected.media,
        };
      setInventoryItems((current) =>
        current.map((item) => (item.id === updated.id ? updated : item)),
      );
      setSelected(updated);
      setDialog(null);
      await refreshDashboardData().catch(() => undefined);
      notify(`บันทึกข้อมูล ${updated.tag} แล้ว`);
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ไม่สามารถแก้ไขข้อมูลไอดีได้",
      );
    } finally {
      setInventoryBusy(false);
    }
  };
  const copyTag = async (i: InventoryItem) => {
    try {
      await writeClipboard(i.tag);
      notify("คัดลอกแท็กแล้ว");
    } catch {
      notify("ไม่สามารถคัดลอกแท็กได้");
    }
  };
  const copyDetails = async (i: InventoryItem) => {
    try {
      let footer = shopDetails?.inventory_copy_footer;
      if (shop && shopDetails?.id !== shop.id) {
        const result = await shopRequest<{ data: ShopDetails }>(
          "/shop",
          shop.id,
        );
        setShopDetails(result.data);
        footer = result.data.inventory_copy_footer;
      }
      await writeClipboard(
        buildInventoryCopyText(i, footer ?? DEFAULT_COPY_FOOTER),
      );
      notify("คัดลอกรายละเอียดไอดีแล้ว");
    } catch {
      notify("ไม่สามารถคัดลอกรายละเอียดไอดีได้");
    }
  };
  const createStaff = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!shop) return;
    const data = new FormData(e.currentTarget);
    const permissions = permissionOptions
      .map(([key]) => key)
      .filter((key) => data.get(`permission-${key}`) === "on");
    try {
      await shopRequest("/team", shop.id, {
        method: "POST",
        body: JSON.stringify({
          name: data.get("name"),
          email: data.get("email"),
          password: data.get("password"),
          password_confirmation: data.get("password_confirmation"),
          permissions,
        }),
      });
      setManagementDialog(null);
      setManagementRevision((value) => value + 1);
      notify("เพิ่มพนักงานแล้ว");
    } catch (error) {
      notify(error instanceof Error ? error.message : "เพิ่มพนักงานไม่สำเร็จ");
    }
  };
  const editStaff = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!shop || !selectedMember) return;
    const data = new FormData(e.currentTarget);
    const permissions = permissionOptions
      .map(([key]) => key)
      .filter((key) => data.get(`permission-${key}`) === "on");
    try {
      await shopRequest(`/team/${selectedMember.id}`, shop.id, {
        method: "PUT",
        body: JSON.stringify({ name: data.get("name"), permissions }),
      });
      setManagementDialog(null);
      setSelectedMember(null);
      setManagementRevision((value) => value + 1);
      notify("บันทึกข้อมูลพนักงานแล้ว");
    } catch (error) {
      notify(error instanceof Error ? error.message : "บันทึกไม่สำเร็จ");
    }
  };
  const resetStaffPassword = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!shop || !selectedMember) return;
    const data = new FormData(e.currentTarget);
    try {
      await shopRequest(`/team/${selectedMember.id}/password`, shop.id, {
        method: "PUT",
        body: JSON.stringify({
          password: data.get("password"),
          password_confirmation: data.get("password_confirmation"),
        }),
      });
      setManagementDialog(null);
      setSelectedMember(null);
      notify("ตั้งรหัสผ่านใหม่แล้ว");
    } catch (error) {
      notify(error instanceof Error ? error.message : "ตั้งรหัสผ่านใหม่ไม่สำเร็จ");
    }
  };
  const updatePermissions = async () => {
    if (!shop || !pendingPermissions) return;
    try {
      await shopRequest(`/team/${pendingPermissions.member.id}`, shop.id, {
        method: "PUT",
        body: JSON.stringify({ permissions: pendingPermissions.permissions }),
      });
      setManagementDialog(null);
      setPendingPermissions(null);
      setManagementRevision((value) => value + 1);
      notify("อัปเดตสิทธิ์แล้ว");
    } catch (error) {
      notify(error instanceof Error ? error.message : "อัปเดตสิทธิ์ไม่สำเร็จ");
    }
  };
  const removeMember = async () => {
    if (!shop || !selectedMember) return;
    try {
      await shopRequest(`/team/${selectedMember.id}`, shop.id, {
        method: "DELETE",
      });
      setManagementDialog(null);
      setSelectedMember(null);
      setManagementRevision((value) => value + 1);
      notify("นำสมาชิกออกจากร้านแล้ว");
    } catch (error) {
      notify(error instanceof Error ? error.message : "นำสมาชิกออกไม่สำเร็จ");
    }
  };
  const anonymizeCustomer = async (customer: CustomerRecord) => {
    if (!shop) return;
    try {
      await shopRequest(`/customers/${customer.id}`, shop.id, {
        method: "DELETE",
      });
      setHistoryRevision((value) => value + 1);
      notify("ลบข้อมูลติดต่อของลูกค้าแล้ว");
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ลบข้อมูลติดต่อไม่สำเร็จ",
      );
    }
  };
  const saveShopSettings = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!shop) return;
    const data = new FormData(e.currentTarget);
    try {
      const result = await shopRequest<{ data: ShopDetails }>(
        "/shop",
        shop.id,
        {
          method: "PUT",
          body: JSON.stringify({
            name: data.get("name"),
            slug: data.get("slug"),
            description: data.get("description") || null,
            facebook_url: data.get("facebook_url") || null,
            line_url: data.get("line_url") || null,
            phone: data.get("phone") || null,
            inventory_copy_footer: data.get("inventory_copy_footer") || null,
            storefront_enabled: data.get("storefront_enabled") === "on",
          }),
        },
      );
      setShopDetails(result.data);
      setSession((current) =>
        current
          ? {
              ...current,
              shops: current.shops.map((candidate) =>
                candidate.id === shop.id
                  ? { ...candidate, ...result.data }
                  : candidate,
              ),
            }
          : current,
      );
      setManagementRevision((value) => value + 1);
      notify("บันทึกตั้งค่าร้านแล้ว");
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "บันทึกตั้งค่าร้านไม่สำเร็จ",
      );
    }
  };
  const saveShopBranding = async (
    body: FormData,
    method: "POST" | "DELETE",
  ) => {
    if (!shop) return;
    try {
      const result = await shopRequest<{ data: ShopDetails }>(
        method === "DELETE"
          ? `/shop/branding?target=${body.get("target")}`
          : "/shop/branding",
        shop.id,
        method === "DELETE" ? { method } : { method, body },
      );
      setShopDetails(result.data);
      notify("อัปเดตแบรนด์ร้านแล้ว");
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "อัปเดตแบรนด์ร้านไม่สำเร็จ",
      );
    }
  };
  const submitTopUp = async (
    credits: number,
    file: File,
  ): Promise<boolean> => {
    if (!shop || paymentBusy) return false;
    if (!Number.isInteger(credits) || credits < 1) {
      notify("ระบุจำนวนเครดิตอย่างน้อย 1 เครดิต");
      return false;
    }
    if (
      !["image/jpeg", "image/png"].includes(file.type) ||
      file.size === 0 ||
      file.size > 5 * 1024 * 1024
    ) {
      notify("ใช้สลิป JPEG หรือ PNG ขนาดไม่เกิน 5 MB");
      return false;
    }
    setPaymentBusy(true);
    try {
      const csrf = await prepareCsrf();
      const form = new FormData();
      form.append("credits", String(credits));
      form.append("slip", file);
      await shopRequest<{ data: Payment }>("/credits/top-ups", shop.id, {
        method: "POST",
        headers: { ...csrf, "Idempotency-Key": createIdempotencyKey() },
        body: form,
      });
      // The dialog shows its own "รอแอดมินตรวจ" confirmation — don't close it
      // or toast here; just refresh so the pending-review banner appears.
      setManagementRevision((value) => value + 1);
      return true;
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ส่งสลิปเติมเครดิตไม่สำเร็จ",
      );
      return false;
    } finally {
      setPaymentBusy(false);
    }
  };
  const purchasePlan = async () => {
    if (!shop || !pendingPlan || paymentBusy) return;
    setPaymentBusy(true);
    try {
      const csrf = await prepareCsrf();
      const result = await shopRequest<{
        data: {
          subscription: ShopDetails["subscription"];
          credit_balance: number;
        };
      }>("/subscriptions/purchase", shop.id, {
        method: "POST",
        headers: { ...csrf, "Idempotency-Key": createIdempotencyKey() },
        body: JSON.stringify({
          plan_id: pendingPlan.id,
          billing_cycle: pendingCycle,
          auto_renew: false,
        }),
      });
      setShopDetails((current) =>
        current
          ? {
              ...current,
              credit_balance: result.data.credit_balance,
              subscription: result.data.subscription,
            }
          : current,
      );
      setManagementDialog(null);
      setPendingPlan(null);
      notify(`ซื้อแพ็กเกจ ${pendingPlan.name} แล้ว`);
      setManagementRevision((value) => value + 1);
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ไม่สามารถซื้อแพ็กเกจได้",
      );
    } finally {
      setPaymentBusy(false);
    }
  };
  const updateAutoRenew = async (autoRenew: boolean) => {
    if (!shop || paymentBusy) return;
    setPaymentBusy(true);
    try {
      const csrf = await prepareCsrf();
      const result = await shopRequest<{ data: ShopDetails["subscription"] }>(
        "/subscriptions/auto-renew",
        shop.id,
        {
          method: "PUT",
          headers: csrf,
          body: JSON.stringify({ auto_renew: autoRenew }),
        },
      );
      setShopDetails((current) =>
        current ? { ...current, subscription: result.data } : current,
      );
      setManagementDialog(null);
      notify(
        autoRenew ? "เปิดต่ออายุอัตโนมัติแล้ว" : "ปิดต่ออายุอัตโนมัติแล้ว",
      );
      setManagementRevision((value) => value + 1);
    } catch (error) {
      notify(
        error instanceof Error
          ? error.message
          : "ไม่สามารถปรับการต่ออายุอัตโนมัติได้",
      );
    } finally {
      setPaymentBusy(false);
    }
  };
  const archive = async () => {
    if (!archiveCandidate || inventoryBusy) return;
    setInventoryBusy(true);
    try {
      if (shop) {
        await shopRequest(`/inventory/${archiveCandidate.id}`, shop.id, {
          method: "DELETE",
        });
        await Promise.all([refreshInventory(), refreshDashboardData()]);
      } else
        setInventoryItems((current) =>
          current.map((item) =>
            item.id === archiveCandidate.id
              ? { ...item, status: "archived" }
              : item,
          ),
        );
      notify(`เก็บ ${archiveCandidate.tag} ถาวรแล้ว`);
      setSelected(null);
      setArchiveCandidate(null);
    } catch (error) {
      notify(
        error instanceof Error ? error.message : "ไม่สามารถเก็บรายการถาวรได้",
      );
    } finally {
      setInventoryBusy(false);
    }
  };
  const activeTitle = saleDetailId
    ? "รายละเอียดการขาย"
    : page === "dashboard"
      ? "ภาพรวมร้าน"
      : page === "inventory"
        ? "คลังไอดี"
        : page === "manual"
          ? "คู่มือการใช้งานระบบ"
          : page === "onboarding"
            ? "เริ่มต้นใช้งาน"
          : (mainNavigation.find((n) => n[0] === page)?.[1] ??
          managementNavigation.find((n) => n[0] === page)?.[1] ??
          "GamoryID");
  useEffect(() => {
    document.title = `${activeTitle} — GamoryID`;
  }, [activeTitle]);
  const planEnt = shopDetails?.entitlements;
  const planMeterDays = (() => {
    const end = planEnt?.current_period_ends_at ?? shopDetails?.trial_ends_at;
    if (!end) return null;
    return Math.max(
      0,
      Math.ceil((new Date(end).getTime() - Date.now()) / 86_400_000),
    );
  })();
  const planMeterLimit = planEnt?.effective_plan.active_inventory_limit ?? null;
  const planMeterPct =
    planEnt && planMeterLimit
      ? Math.min(
          100,
          Math.round((planEnt.usage.inventory_active / planMeterLimit) * 100),
        )
      : 0;
  return (
    <div className="app-shell">
      <aside className="sidebar" aria-label="เมนูหลัก">
        <div className="brand">
          <img src="/mascot/gammy-main.png" alt="Gammy" />
          <span>
            Gamory<span className="brand-accent">ID</span>
          </span>
        </div>
        <div className="nav-label">พื้นที่ทำงาน</div>
        <nav className="nav">
          {mainNavigation.map(([key, label, Icon]) => (
            <button
              key={key}
              className={`nav-button ${page === key ? "active" : ""}`}
              onClick={() => go(key)}
            >
              <Icon size={18} />
              {label}
            </button>
          ))}
        </nav>
        <div className="nav-label">จัดการร้าน</div>
        <nav className="nav">
          {managementNavigation
            .filter(([key]) => canAccessManagementPage(key))
            .map(([key, label, Icon]) => (
              <button
                key={key}
                className={`nav-button ${page === key ? "active" : ""}`}
                onClick={() => go(key)}
              >
                <Icon size={18} />
                {label}
              </button>
            ))}
        </nav>
        <div className="sidebar-bottom">
          <div className="plan-meter">
            <strong>
              {planEnt?.effective_plan.name ?? "แพ็กเกจร้าน"}
              {planMeterDays != null
                ? ` · เหลือ ${planMeterDays.toLocaleString("th-TH")} วัน`
                : ""}
            </strong>
            <span>
              {planEnt
                ? `สต็อก ${planEnt.usage.inventory_active.toLocaleString("th-TH")} / ${
                    planMeterLimit == null
                      ? "ไม่จำกัด"
                      : planMeterLimit.toLocaleString("th-TH")
                  } รายการ`
                : "กำลังโหลดข้อมูลแพ็กเกจ…"}
            </span>
            <div className="meter">
              <i style={{ width: `${planMeterPct}%` }} />
            </div>
          </div>
          <button
            className={`nav-button ${page === "onboarding" ? "active" : ""}`}
            onClick={() => go("onboarding")}
          >
            <Rocket size={18} />
            เริ่มต้นใช้งาน
            {onboardingRequiredLeft > 0 && (
              <span className="nav-badge">{onboardingRequiredLeft}</span>
            )}
          </button>
          <button className="nav-button" onClick={() => go("manual")}>
            <BookOpen size={18} />
            คู่มือการใช้งานระบบ
          </button>
        </div>
      </aside>
      <header className="topbar">
        <div className="account">
          <button
            className="icon-button"
            aria-label="การแจ้งเตือน"
            onClick={() => notify("ยังไม่มีการแจ้งเตือนใหม่")}
          >
            <Bell size={19} />
          </button>
          <div className="avatar">
            {(session?.name ?? "PT").slice(0, 2).toUpperCase()}
          </div>
          <div className="account-text">
            <strong>{session?.name ?? "พีท เจ้าของร้าน"}</strong>
            <span>{shop?.name ?? "Nexus Store"}</span>
          </div>
        </div>
      </header>
      <main
        className={`page ${page === "dashboard" ? "dashboard-page" : ""} ${page === "inventory" ? "inventory-page" : ""} ${page === "inventory" && selected ? "inventory-detail-page" : ""} ${page === "sales" ? "sales-page" : ""} ${saleDetailId ? "sale-detail-page" : ""} ${page === "customers" ? "customers-page" : ""} ${["team", "billing", "transactions", "discord", "settings", "manual", "onboarding"].includes(page) ? "management-page" : ""}`}
      >
        <div className="page-head">
          <div>
            <div className="eyebrow">
              {shop?.name ?? "ร้านของคุณ"} /{" "}
              {page === "dashboard" ? "DASHBOARD" : "พื้นที่ทำงาน"}
            </div>
            <h1>
              {page === "dashboard"
                ? `สวัสดี, ${(session?.name ?? "เจ้าของร้าน").split(" ")[0]}`
                : activeTitle}
            </h1>
            <p>
              {page === "dashboard"
                ? "ภาพรวมร้านสำหรับวางแผนสต็อกและยอดขายวันนี้"
                : page === "transactions"
                  ? "ตรวจสอบประวัติแพ็กเกจและเครดิตของร้านจากรายการล่าสุด"
                  : page === "discord"
                    ? "เชื่อมเซิร์ฟเวอร์ ตั้งค่าห้องแจ้งเตือน และจัดการร้านด้วยคำสั่งภาษาไทย"
                    : page === "manual"
                      ? "สรุปการใช้งานหลักของ GamoryID สำหรับเจ้าของร้านและพนักงาน"
                      : "ค้นหา จอง และขายไอดีได้จากที่เดียว"}
            </p>
          </div>
          <div className="actions">
            {hasShopPermission("data.export") && (
              <button
                className="button"
                onClick={() => setManagementDialog("export")}
              >
                <Download size={17} />
                ส่งออก
              </button>
            )}
            <button className="button" onClick={() => go("imports")}>
              <FileUp size={17} />
              นำเข้าข้อมูล
            </button>
            {hasShopPermission("inventory.manage") && (
              <button
                className="button primary"
                aria-label="เพิ่มไอดี"
                onClick={() => setDialog("add")}
              >
                <PackagePlus size={18} />
                <span>เพิ่มไอดี</span>
              </button>
            )}
          </div>
        </div>
        {["team", "billing", "transactions", "discord", "settings"].includes(
          page,
        ) && (
          <nav
            className="mobile-manage-nav"
            aria-label="เมนูจัดการร้านบนมือถือ"
          >
            {managementNavigation
              .filter(([key]) => canAccessManagementPage(key))
              .map(([key, label]) => (
                <button
                  key={key}
                  className={page === key ? "active" : ""}
                  onClick={() => go(key)}
                >
                  {label}
                </button>
              ))}
          </nav>
        )}
        {loadError && (
          <div className="auth-error" role="alert">
            {loadError}{" "}
            <button
              className="button ghost"
              onClick={() => void refreshInventory()}
            >
              ลองใหม่
            </button>
          </div>
        )}
        {page === "imports" && (
          <ImportPanel
            shopId={shop?.id}
            onComplete={() => {
              void Promise.all([refreshInventory(), refreshDashboardData()]);
            }}
          />
        )}
        {page === "sales" && saleDetailId && shop ? (
          <SaleDetailPage
            key={saleDetailId}
            shopId={shop.id}
            saleId={saleDetailId}
            canViewProfit={hasShopPermission("profit.view")}
          />
        ) : null}
        {page === "sales" && !saleDetailId && (
          <SalesPanel
            records={sales}
            loading={historyLoading}
            error={historyError}
            retry={() => setHistoryRevision((value) => value + 1)}
            canViewProfit={hasShopPermission("profit.view")}
          />
        )}{" "}
        {page === "customers" && (
          <CustomersPanel
            records={customers}
            loading={historyLoading}
            error={historyError}
            retry={() => setHistoryRevision((value) => value + 1)}
            canManage={hasShopPermission("team.manage")}
            onAnonymize={(customer) => void anonymizeCustomer(customer)}
          />
        )}{" "}
        {page === "team" && (
          <TeamPanel
            members={team}
            loading={managementLoading}
            error={managementError}
            canManage={hasShopPermission("team.manage")}
            createStaff={() => setManagementDialog("createStaff")}
            onPermissionsChange={(member, permissions) => {
              setPendingPermissions({ member, permissions });
              setManagementDialog("permissions");
            }}
            onEdit={(member) => {
              setSelectedMember(member);
              setManagementDialog("editStaff");
            }}
            onResetPassword={(member) => {
              setSelectedMember(member);
              setManagementDialog("resetPassword");
            }}
            onRemove={(member) => {
              setSelectedMember(member);
              setManagementDialog("remove");
            }}
            retry={() => setManagementRevision((value) => value + 1)}
          />
        )}{" "}
        {page === "billing" && (
          <BillingPanel
            plans={plans}
            shop={shopDetails}
            loading={managementLoading}
            error={managementError}
            canManage={hasShopPermission("billing.manage")}
            busy={paymentBusy}
            onOpenTopUp={() => setManagementDialog("topUp")}
            onPurchase={(plan, cycle) => {
              setPendingPlan(plan);
              setPendingCycle(cycle);
              setManagementDialog("purchase");
            }}
            onAutoRenewChange={(autoRenew) => {
              if (autoRenew) setManagementDialog("autoRenew");
              else void updateAutoRenew(false);
            }}
            retry={() => setManagementRevision((value) => value + 1)}
          />
        )}{" "}
        {page === "transactions" && (
          <TransactionsPanel
            history={billingHistory}
            loading={transactionsLoading}
            error={transactionsError}
            retry={() => setTransactionsRevision((value) => value + 1)}
          />
        )}{" "}
        {page === "activity" && shop && <ActivityPanel shopId={shop.id} />}{" "}
        {page === "analytics" && shop && (
          <AnalyticsPanel
            shopId={shop.id}
            canViewProfit={hasShopPermission("profit.view")}
          />
        )}{" "}
        {page === "discord" && (
          <DiscordSettingsPanel
            shopId={shop?.id}
            canManage={hasShopPermission("discord.manage")}
            notify={notify}
          />
        )}{" "}
        {page === "settings" && (
          <SettingsPanel
            shop={shopDetails ?? shop ?? null}
            loading={managementLoading}
            error={managementError}
            canUseStorefront={planFeature("storefront")}
            logoUrl={
              shopDetails?.logo_url ? apiAssetUrl(shopDetails.logo_url) : null
            }
            bannerUrl={
              shopDetails?.banner_url
                ? apiAssetUrl(shopDetails.banner_url)
                : null
            }
            onSubmit={saveShopSettings}
            onUploadBranding={(target, file) => {
              void (async () => {
                const sized = await shrinkImage(
                  file,
                  target === "logo" ? 512 : 1600,
                );
                const body = new FormData();
                body.append(target, sized);
                void saveShopBranding(body, "POST");
              })();
            }}
            onRemoveBranding={(target) => {
              const body = new FormData();
              body.append("target", target);
              void saveShopBranding(body, "DELETE");
            }}
            retry={() => setManagementRevision((value) => value + 1)}
          />
        )}
        {page === "manual" && <ManualPanel />}
        {page === "onboarding" && (
          <OnboardingPanel
            steps={onboardingSteps}
            dismissed={onboardingDismissed}
            onCta={handleOnboardingCta}
            onDismiss={() => void dismissOnboarding()}
          />
        )}
        {page === "dashboard" &&
          !onboardingDismissed &&
          onboardingRequiredLeft > 0 && (
            <OnboardingCard
              steps={onboardingSteps}
              onOpen={() => go("onboarding")}
              onDismiss={() => void dismissOnboarding()}
            />
          )}
        {page === "dashboard" && (
          <DashboardPanel
            dashboard={dashboard}
            summary={summary}
            canViewProfit={hasShopPermission("profit.view")}
            canViewAnalytics={canViewAnalytics}
            storefrontViews={storefrontViews}
            viewGranularity={viewGranularity}
            onViewGranularityChange={setViewGranularity}
            salesReport={salesReport}
            salesGranularity={salesGranularity}
            onSalesGranularityChange={setSalesGranularity}
            onOpenInventory={() => go("inventory")}
            onOpenImport={() => go("imports")}
            onOpenAdd={() => setDialog("add")}
            onRefresh={() =>
              void Promise.all([
                refreshDashboardData(),
                refreshSalesReport(salesGranularity),
              ]).catch(() => notify("ไม่สามารถรีเฟรชข้อมูลได้"))
            }
          />
        )}
        {page === "inventory" && selected && (
          <InventoryDetailPage
            item={selected}
            canManage={hasShopPermission("inventory.manage")}
            canSell={hasShopPermission("inventory.sell")}
            canViewAnalytics={canViewAnalytics}
            canNote={
              hasShopPermission("inventory.manage") ||
              hasShopPermission("inventory.sell")
            }
            onBack={() => {
              setSelected(null);
              navigate(PAGE_PATHS.inventory);
            }}
            onEdit={() => setDialog("edit")}
            onCopyDetails={() => void copyDetails(selected)}
            onReserve={() => void reserve(selected)}
            onSell={() => sell(selected)}
            onArchive={() => setArchiveCandidate(selected)}
            onEditNote={() => openInventoryNote(selected)}
          />
        )}
        <section
          className={`kpis ${page === "dashboard" ? "dashboard-legacy" : ""}`}
          aria-label="ตัวเลขภาพรวม"
        >
          <Kpi
            label="พร้อมขาย"
            value={`${summary.available}`}
            note="รายการในสต็อก"
            icon={<Box size={16} />}
          />
          <Kpi
            label="ถูกจอง"
            value={`${summary.reserved}`}
            note="รายการที่ล็อกให้ลูกค้า"
            icon={<Clock3 size={16} />}
          />
          <Kpi
            label="ขายเดือนนี้"
            value={`${summary.sold}`}
            note="รายการที่ปิดการขายแล้ว"
            icon={<Check size={16} />}
          />
          <Kpi
            label="มูลค่าสต็อก"
            value={money.format(summary.value ?? 0)}
            note="เฉพาะรายการที่ยังไม่ขาย"
            icon={<WalletCards size={16} />}
          />
        </section>
        {page === "inventory" && !selected && (
          <div
            className="inventory-search-row"
            role="search"
            aria-label="ค้นหาในคลังไอดี"
          >
            <div className="top-search inventory-search">
              <Search size={18} />
              <input
                ref={searchRef}
                inputMode="search"
                aria-label="ค้นหาไอดี"
                aria-describedby="inventory-search-status"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                onCompositionStart={() => setSearchComposing(true)}
                onCompositionEnd={(event) => {
                  setQuery(event.currentTarget.value);
                  setSearchComposing(false);
                }}
                placeholder="ค้นหาแท็ก เช่น #23DX5 หรือชื่อไอดี"
              />
              {query && (
                <button
                  type="button"
                  className="clear-search"
                  aria-label="ล้างคำค้น"
                  onClick={() => {
                    setQuery("");
                    searchRef.current?.focus();
                  }}
                >
                  <X size={17} />
                </button>
              )}
            </div>
            <span
              id="inventory-search-status"
              className="sr-only"
              role="status"
            >
              {query
                ? `พบ ${filtered.length} รายการสำหรับ ${query}`
                : `แสดงไอดี ${filtered.length} รายการ`}
            </span>
          </div>
        )}
        <div
          className={`work-grid ${page === "dashboard" ? "dashboard-legacy" : ""}`}
        >
          <InventoryPanel
            items={filtered}
            query={query}
            status={status}
            setInventoryStatus={setInventoryStatus}
            canSell={hasShopPermission("inventory.sell")}
            canNote={
              hasShopPermission("inventory.manage") ||
              hasShopPermission("inventory.sell")
            }
            canViewAnalytics={canViewAnalytics}
            busy={inventoryBusy}
            onStatusChange={changeInventoryStatus}
            onSelect={openDetail}
            onReserve={reserve}
            onSell={sell}
            onCopyTag={copyTag}
            onCopyDetails={copyDetails}
            onNote={openInventoryNote}
          />
        </div>
      </main>
      <nav className="bottom-nav" aria-label="เมนูมือถือ">
        <button
          className={page === "dashboard" ? "active" : ""}
          onClick={() => go("dashboard")}
        >
          <House size={19} />
          ภาพรวม
        </button>
        <button
          className={page === "inventory" ? "active" : ""}
          onClick={() => go("inventory")}
        >
          <ShoppingBag size={19} />
          คลังไอดี
        </button>
        <button
          className={page === "sales" ? "active" : ""}
          onClick={() => go("sales")}
        >
          <Tag size={19} />
          ขาย
        </button>
        <button
          className={page === "customers" ? "active" : ""}
          onClick={() => go("customers")}
        >
          <UserRound size={19} />
          ลูกค้า
        </button>
        <button onClick={() => go("settings")}>
          <Menu size={19} />
          เพิ่มเติม
        </button>
      </nav>
      {dialog === "add" && (
        <AddDialog
          close={() => setDialog(null)}
          submit={addInventoryItem}
          busy={inventoryBusy}
        />
      )}{" "}
      {dialog === "edit" && selected && (
        <EditDialog
          item={selected}
          close={() => setDialog(null)}
          submit={editInventoryItem}
          busy={inventoryBusy}
        />
      )}{" "}
      {dialog === "note" && noteCandidate && (
        <InventoryNoteDialog
          item={noteCandidate}
          busy={inventoryBusy}
          close={() => {
            setNoteCandidate(null);
            setDialog(null);
          }}
          submit={saveInventoryNote}
        />
      )}{" "}
      {dialog === "sell" && saleInventoryItem && (
        <SellDialog
          item={saleInventoryItem}
          close={() => {
            setSaleInventoryItem(null);
            setDialog(null);
          }}
          submit={confirmSell}
        />
      )}{" "}
      {archiveCandidate && (
        <ArchiveDialog
          item={archiveCandidate}
          close={() => setArchiveCandidate(null)}
          confirm={archive}
          busy={inventoryBusy}
        />
      )}{" "}
      {managementDialog === "createStaff" && (
        <CreateStaffDialog
          close={() => setManagementDialog(null)}
          submit={createStaff}
        />
      )}{" "}
      {managementDialog === "editStaff" && selectedMember && (
        <EditStaffDialog
          member={selectedMember}
          close={() => {
            setManagementDialog(null);
            setSelectedMember(null);
          }}
          submit={editStaff}
        />
      )}{" "}
      {managementDialog === "resetPassword" && selectedMember && (
        <ResetPasswordDialog
          member={selectedMember}
          close={() => {
            setManagementDialog(null);
            setSelectedMember(null);
          }}
          submit={resetStaffPassword}
        />
      )}{" "}
      {managementDialog === "permissions" && pendingPermissions && (
        <PermissionDialog
          member={pendingPermissions.member}
          permissions={pendingPermissions.permissions}
          close={() => {
            setPendingPermissions(null);
            setManagementDialog(null);
          }}
          confirm={updatePermissions}
        />
      )}{" "}
      {managementDialog === "remove" && selectedMember && (
        <RemoveMemberDialog
          member={selectedMember}
          close={() => {
            setManagementDialog(null);
            setSelectedMember(null);
          }}
          confirm={removeMember}
        />
      )}{" "}
      {managementDialog === "purchase" && pendingPlan && (
        <PurchasePlanDialog
          plan={pendingPlan}
          cycle={pendingCycle}
          balance={shopDetails?.credit_balance ?? 0}
          busy={paymentBusy}
          close={() => {
            setPendingPlan(null);
            setManagementDialog(null);
          }}
          confirm={() => void purchasePlan()}
        />
      )}{" "}
      {managementDialog === "autoRenew" && (
        <AutoRenewDialog
          close={() => setManagementDialog(null)}
          confirm={() => void updateAutoRenew(true)}
        />
      )}{" "}
      {managementDialog === "topUp" && (
        <TopUpDialog
          busy={paymentBusy}
          close={() => setManagementDialog(null)}
          submit={submitTopUp}
        />
      )}{" "}
      {managementDialog === "export" && shop && (
        <ExportDialog
          shopId={shop.id}
          shopSlug={shopDetails?.slug ?? shop.slug ?? "shop"}
          canSalesExport={planFeature("advanced_export")}
          notify={notify}
          close={() => setManagementDialog(null)}
        />
      )}{" "}
      {toast && (
        <div
          className={`toast ${toast.tone}`}
          role={toast.tone === "error" ? "alert" : "status"}
        >
          {toast.tone === "error" ? (
            <CircleHelp size={18} />
          ) : (
            <Check size={18} />
          )}
          <span>{toast.message}</span>
        </div>
      )}
    </div>
  );
}
