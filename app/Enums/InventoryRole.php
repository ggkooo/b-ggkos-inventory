<?php

namespace App\Enums;

enum InventoryRole: string
{
    case Owner = 'owner';
    case Purchasing = 'purchasing';
}
