<?php

declare(strict_types=1);

namespace App\Domain\Alert;

enum AlertSeverity: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';
}
