<?php

namespace App\Enums;

enum SquatDepth: string
{
    case ABOVE_PARALLEL = 'ABOVE_PARALLEL';
    case PARALLEL = 'PARALLEL';
    case BELOW_PARALLEL = 'BELOW_PARALLEL';
}
