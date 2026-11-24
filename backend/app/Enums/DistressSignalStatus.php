<?php

namespace App\Enums;

enum DistressSignalStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
