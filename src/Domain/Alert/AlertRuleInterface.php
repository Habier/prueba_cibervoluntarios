<?php

declare(strict_types=1);

namespace App\Domain\Alert;

interface AlertRuleInterface
{
    public function evaluate(AlertContext $context): ?AlertDraft;
}
