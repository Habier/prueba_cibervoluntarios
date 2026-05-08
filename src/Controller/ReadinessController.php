<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Health\DependencyReadinessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ReadinessController
{
    public function __construct(
        private DependencyReadinessChecker $checker,
    ) {
    }

    #[Route('/ready', name: 'app_ready', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $dependencies = $this->checker->check();
        $ready = ! in_array(false, $dependencies, true);

        return new JsonResponse(
            [
                'status' => $ready ? 'READY' : 'NOT_READY',
                'dependencies' => $dependencies,
            ],
            $ready ? 200 : 503,
        );
    }
}
