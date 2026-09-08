<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Archived = 'archived';
    // Added to the shop's inventory but deliberately not for sale yet.
    case Draft = 'draft';
}
