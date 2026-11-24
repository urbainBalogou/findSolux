<?php

namespace App\Enums;

enum BusinessStatus: string
{
    /** Visible dans les recherches. */
    case Published = 'published';

    /** Masquée par la modération. */
    case Suspended = 'suspended';
}
