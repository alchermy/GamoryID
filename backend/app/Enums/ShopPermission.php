<?php

namespace App\Enums;

enum ShopPermission: string
{
    case InventoryManage = 'inventory.manage';
    case InventorySell = 'inventory.sell';
    case ProfitView = 'profit.view';
    case DataExport = 'data.export';
    case CredentialsReveal = 'credentials.reveal';
    case TeamManage = 'team.manage';
    case BillingManage = 'billing.manage';
    case DiscordManage = 'discord.manage';
}
