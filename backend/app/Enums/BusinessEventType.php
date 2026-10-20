<?php

namespace App\Enums;

enum BusinessEventType: string
{
    /** Fiche consultée. */
    case View = 'view';

    /** Bouton « Appeler ». */
    case Call = 'call';

    /** Bouton « WhatsApp ». */
    case WhatsApp = 'whatsapp';

    /** Bouton « Itinéraire ». */
    case Directions = 'directions';

    /** Fiche partagée. */
    case Share = 'share';
}
