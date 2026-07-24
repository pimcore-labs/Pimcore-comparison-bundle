<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Controller;

use Pimcore\Bundle\ComparisonBundle\Security\ComparisonPermissions;
use Pimcore\Bundle\StudioBackendBundle\Controller\AbstractApiController;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Base for the comparison controllers. Enforces the master `plugin_comparison` gate (admin-bypassed
 * via Pimcore's own isAllowed). Element view-permission on the two compared objects is enforced
 * separately by ComparisonService, per request.
 */
abstract class AbstractComparisonController extends AbstractApiController
{
    public function __construct(
        SerializerInterface $serializer,
        protected readonly SecurityServiceInterface $securityService,
    ) {
        parent::__construct($serializer);
    }

    protected function requireComparisonAccess(string $permission = ComparisonPermissions::COMPARISON): void
    {
        $user = $this->securityService->getCurrentUser();
        if (method_exists($user, 'isAllowed') && !$user->isAllowed($permission)) {
            throw new AccessDeniedHttpException(sprintf('Missing Comparison permission: %s', $permission));
        }
    }
}
