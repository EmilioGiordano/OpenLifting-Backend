<?php

namespace App\Enums;

enum RiskLevel: string
{
    case NORMAL = 'NORMAL';
    case MONITOR = 'MONITOR';
    case RISK = 'RISK';
}
