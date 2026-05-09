<?php

declare(strict_types=1);

namespace App\Domain\Alert;

use App\Domain\Gps\GpsCoordinate;
use App\Domain\Vehicle\Vehicle;

final readonly class RuleBasedAlertEvaluationPolicy implements AlertEvaluationPolicyInterface
{
    /**
     * @param iterable<AlertRuleInterface> $alertRules
     */
    public function __construct(
        private iterable $alertRules,
    ) {
    }

    public function evaluate(Vehicle $vehicle, GpsCoordinate $observation): array
    {
        $alerts = [];

        foreach ($this->alertRules as $rule) {
            $alert = $rule->evaluate(new AlertContext($observation));

            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }
}
