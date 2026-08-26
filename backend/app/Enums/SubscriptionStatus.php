<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case GraceReadOnly = 'grace_read_only';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}
