<?php

namespace App\Enums;

/**
 * Origine d'un contact échangé entre un client et un garage.
 */
enum ContactSource: string
{
    case DistressSignal = 'distress_signal';
    case ContactRequest = 'contact_request';
}
