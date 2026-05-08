<?php

declare(strict_types=1);

namespace App\Domain\Alert;

final class IdleTooLongRule implements AlertRuleInterface
{
    public function evaluate(AlertContext $context): ?AlertDraft
    {
        return null;
    }
}
