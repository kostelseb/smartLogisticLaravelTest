<?php

namespace App\Enums;

enum ProviderFailureMode: string
{
    case NONE = 'none';
    case PERMANENT = 'permanent';
    case TEMPORARY_ONCE = 'temporary_once';
}
