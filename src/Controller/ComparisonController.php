<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The comparison REST surface, under /pimcore-studio/api/comparison. Because it lives under the
 * Studio api prefix it sits inside the Studio auth firewall automatically.
 *
 * P1 ships only the `status` endpoint (proves routing + firewall + permission wiring). The real
 * diff endpoints — objects, objects/summary, objects/export — are added in P5 on top of the P2–P4
 * comparison engine.
 */
final class ComparisonController extends AbstractComparisonController
{
    #[Route('/pimcore-studio/api/comparison/status', name: 'pimcore_studio_api_comparison_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $this->requireComparisonAccess();

        return new JsonResponse([
            'bundle' => 'pimcore/comparison-bundle',
            'ok' => true,
            'version' => 'v1',
            'readOnly' => true,
        ]);
    }
}
