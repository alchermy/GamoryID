export type InventoryStatus = "available" | "reserved" | "sold" | "archived";

export type InventoryMedia = {
  id: number;
  role: "display" | "detail";
  originalName: string | null;
  mimeType: string;
  sizeBytes: number;
  sortOrder: number;
  url: string;
};

export type MerchantPage =
  | "dashboard"
  | "inventory"
  | "sales"
  | "customers"
  | "imports"
  | "team"
  | "billing"
  | "transactions"
  | "discord"
  | "settings";

export type InventoryItem = {
  id: number;
  tag: string;
  title: string;
  riotId: string;
  username: string;
  rank: string;
  level: number;
  skins: number;
  cost: number;
  price: number;
  status: InventoryStatus;
  updated: string;
  description?: string | null;
  notes?: string | null;
  hasCredentials?: boolean;
  media: InventoryMedia[];
};

export type Shop = {
  id: number;
  name: string;
  slug?: string;
  status: string;
  role: string;
  permissions: string[];
  description?: string | null;
  facebook_url?: string | null;
  line_url?: string | null;
  phone?: string | null;
};

export type SessionUser = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  current_shop_id: number;
  shops: Shop[];
};

export type DashboardData = {
  summary: {
    available: number;
    reserved: number;
    sold_this_month: number;
    sold_total: number;
    inventory_value: number | null;
    revenue_this_month: number;
    profit_this_month: number | null;
  };
  activity: Array<{
    id: number;
    event: string;
    created_at: string;
    metadata?: Record<string, unknown>;
  }>;
  sales_last_7_days: Array<{ date: string; sales: number; revenue: number }>;
  subscription: {
    status: string;
    trial_ends_at: string | null;
    grace_ends_at?: string | null;
    writable: boolean;
  };
};

export type InventoryResponse = {
  data: Array<{
    id: number;
    tag: string;
    title: string;
    riot_id: string | null;
    username: string | null;
    rank: string | null;
    level: number | null;
    skin_count: number;
    description: string | null;
    notes: string | null;
    cost: number | null;
    list_price: number;
    status: InventoryStatus;
    updated_at: string;
    has_credentials: boolean;
    media?: Array<{
      id: number;
      role: "display" | "detail";
      original_name: string | null;
      mime_type: string;
      size_bytes: number;
      sort_order: number;
      url: string;
    }>;
  }>;
  meta?: { total: number; from: number | null; to: number | null };
};

export type Paged<T> = {
  data: T[];
  meta?: {
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
  };
};

export type SaleRecord = {
  id: number;
  sold_price: number | string;
  cost_snapshot?: number | string | null;
  profit?: number | string | null;
  sold_at: string;
  has_warranty: boolean;
  warranty_ends_at: string | null;
  notes: string | null;
  inventory_item: {
    id: number;
    tag: string;
    title: string;
    riot_id: string | null;
    rank: string | null;
    level: number | null;
    list_price: number | string;
  } | null;
  customer: {
    id: number;
    name: string;
    phone: string | null;
    line_id: string | null;
    facebook_url: string | null;
  } | null;
  creator: { id: number; name: string } | null;
};

export type SalePayload = {
  customer: {
    name: string;
    facebook_url: string | null;
    line_id: string | null;
    phone: string | null;
  };
  sold_price: number;
  has_warranty: boolean;
  warranty_ends_at: string | null;
  notes: string | null;
};

export type CustomerRecord = {
  id: number;
  name: string;
  phone: string | null;
  line_id: string | null;
  facebook_url: string | null;
  updated_at: string;
  sales_count: number;
};

export type TeamMember = {
  id: number;
  role: "owner" | "staff";
  permissions: string[];
  user: { id: number; name: string; email: string };
};

export type Payment = {
  id: number;
  status: string;
  credits: number;
  verified_at: string | null;
  created_at?: string;
  review_note?: string | null;
};

export type ShopDetails = Shop & {
  trial_ends_at: string | null;
  grace_ends_at: string | null;
  credit_balance: number;
  inventory_copy_footer?: string | null;
  subscription: {
    status: string;
    auto_renew: boolean;
    ends_at: string | null;
    plan?: {
      name: string;
      code: string;
      active_inventory_limit: number;
      member_limit: number;
      price_thb: number;
      duration_days: number;
    };
  } | null;
  latest_top_up?: Payment | null;
};

export type Plan = {
  id: number;
  name: string;
  code: string;
  active_inventory_limit: number;
  member_limit: number;
  price_thb: number;
  duration_days: number;
};

export type SubscriptionHistoryRecord = {
  id: number;
  status: string;
  starts_at: string | null;
  ends_at: string | null;
  created_at: string;
  auto_renew: boolean;
  plan: {
    name: string;
    code: string;
    price_thb: number;
    duration_days: number;
  } | null;
};

export type TopUpHistoryRecord = {
  id: number;
  status: string;
  credits: number;
  amount: number;
  created_at: string;
  verified_at: string | null;
  review_note: string | null;
  submitted_by: { name: string } | null;
};

export type BillingHistory = {
  subscriptions: { items: SubscriptionHistoryRecord[]; total: number };
  top_ups: { items: TopUpHistoryRecord[]; total: number };
};

export type ToastMessage = { message: string; tone: "success" | "error" };

export type DiscordChannelPurpose = {
  value: "commands" | "system" | "sales" | "reservations" | "inventory";
  label: string;
};

export type DiscordSettings = {
  configured: boolean;
  test_mode: boolean;
  connected: boolean;
  installation: {
    guild_id: string;
    guild_name: string;
    status: string;
    installed_at: string | null;
    last_verified_at: string | null;
    channels: Array<{
      purpose: DiscordChannelPurpose["value"];
      channel_id: string;
      channel_name: string;
      enabled: boolean;
    }>;
  } | null;
  available_channels: Array<{ id: string; name: string }>;
  channel_sync_error: string | null;
  user_link: {
    linked: boolean;
    discord_username: string | null;
    linked_at: string | null;
  };
  purposes: DiscordChannelPurpose[];
};

export type DiscordOneTimeCode = {
  code: string;
  expires_at: string;
  install_url?: string;
};
