<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Archived = 'archived';
}
