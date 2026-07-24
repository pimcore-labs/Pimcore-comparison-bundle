<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Controller;

use Pimcore\Bundle\ComparisonBundle\Comparison\ComparisonException;
use Pimcore\Bundle\ComparisonBundle\Comparison\ComparisonService;
use Pimcore\Bundle\ComparisonBundle\Comparison\DiffResult;
use Pimcore\Bundle\ComparisonBundle\Export\DiffExporter;
use Pimcore\Bundle\ComparisonBundle\Export\DiffFilter;
use Pimcore\Bundle\ComparisonBundle\Studio\HiddenFieldResolver;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The comparison REST surface under /pimcore-studio/api/comparison (inside the Studio auth firewall).
 * Read-only (C-3), stateless (C-4): every response is computed on demand, nothing is persisted, and
 * an ETag over the two modification dates makes results cache-friendly. The master `plugin_comparison`
 * gate plus element view-permission on BOTH objects (T-SEC-001) are enforced per request; fields the
 * user may not see are masked server-side (C-2, T-SEC-002).
 */
#[\Pimcore\Bundle\ComparisonBundle\Feature\Attribute\AsFeature(id: 'api.rest', group: 'api', name: 'Comparison REST API', description: 'Studio REST endpoints: objects, objects/summary, objects/export; permission-gated, ETag, ProblemDetails.', status: \Pimcore\Bundle\ComparisonBundle\Feature\FeatureStatus::IN_PROGRESS, openGaps: ['Verified via live HTTP smoke; API functional tests pending'], specRefs: ['FR-CMP-020', 'FR-CMP-026', 'FR-CMP-027', 'FR-CMP-028'], dependsOn: ['core.comparison-service'], since: '2026-07-24', backendOnly: true)]
final class ComparisonController extends AbstractComparisonController
{
    public function __construct(
        SerializerInterface $serializer,
        SecurityServiceInterface $securityService,
        private readonly ComparisonService $comparisonService,
        private readonly HiddenFieldResolver $hiddenFieldResolver,
        private readonly DiffFilter $diffFilter,
        private readonly DiffExporter $diffExporter,
        private readonly string $defaultFilter = 'differences',
        private readonly array $exportFormats = ['xlsx', 'json'],
    ) {
        parent::__construct($serializer, $securityService);
    }

    #[Route('/pimcore-studio/api/comparison/status', name: 'pimcore_studio_api_comparison_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $this->requireComparisonAccess();

        return new JsonResponse(['bundle' => 'pimcore/comparison-bundle', 'ok' => true, 'version' => 'v1', 'readOnly' => true]);
    }

    #[Route('/pimcore-studio/api/comparison/objects', name: 'pimcore_studio_api_comparison_objects', methods: ['GET'])]
    public function objects(Request $request): Response
    {
        [$left, $right] = $this->loadPair($request);
        $etag = $this->etag($request, $left, $right);
        if ($this->notModified($request, $etag)) {
            return $this->cacheable(new Response(null, Response::HTTP_NOT_MODIFIED), $etag);
        }

        $result = $this->diff($left, $right, $request);
        $fields = $this->diffFilter->apply(
            $result->fields,
            (string) $request->query->get('filter', $this->defaultFilter),
            (string) $request->query->get('query', ''),
        );

        return $this->cacheable(new JsonResponse([
            'leftId' => $result->leftId,
            'rightId' => $result->rightId,
            'className' => $result->className,
            'fields' => $fields,
            'summary' => ['total' => $result->total(), 'differing' => $result->differing(), 'counts' => $result->counts()],
        ]), $etag);
    }

    #[Route('/pimcore-studio/api/comparison/objects/summary', name: 'pimcore_studio_api_comparison_summary', methods: ['GET'])]
    public function summary(Request $request): Response
    {
        [$left, $right] = $this->loadPair($request);
        $etag = $this->etag($request, $left, $right);
        if ($this->notModified($request, $etag)) {
            return $this->cacheable(new Response(null, Response::HTTP_NOT_MODIFIED), $etag);
        }

        $result = $this->diff($left, $right, $request);

        return $this->cacheable(new JsonResponse([
            'leftId' => $result->leftId,
            'rightId' => $result->rightId,
            'className' => $result->className,
            'total' => $result->total(),
            'differing' => $result->differing(),
            'counts' => $result->counts(),
        ]), $etag);
    }

    #[Route('/pimcore-studio/api/comparison/objects/export', name: 'pimcore_studio_api_comparison_export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        [$left, $right] = $this->loadPair($request);
        $body = $this->body($request);
        $format = strtolower((string) ($body['format'] ?? 'xlsx'));
        if (!in_array($format, $this->exportFormats, true)) {
            throw new BadRequestHttpException(sprintf('Unsupported export format "%s".', $format));
        }

        $result = $this->diff($left, $right, $request);
        $fields = $this->diffFilter->apply(
            $result->fields,
            (string) ($body['filter'] ?? $this->defaultFilter),
            (string) ($body['query'] ?? ''),
        );

        $base = sprintf('comparison-%d-vs-%d', $result->leftId, $result->rightId);
        if ($format === DiffExporter::FORMAT_JSON) {
            return $this->download($this->diffExporter->toJson($result, $fields), $base . '.json', 'application/json');
        }

        return $this->download(
            $this->diffExporter->toXlsx($result, $fields),
            $base . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    // --- helpers ---

    /**
     * @return array{0: Concrete, 1: Concrete}
     */
    private function loadPair(Request $request): array
    {
        $this->requireComparisonAccess();

        $post = $request->getMethod() === 'POST';
        $body = $post ? $this->body($request) : [];
        $leftId = $post ? (int) ($body['leftId'] ?? 0) : $request->query->getInt('leftId');
        $rightId = $post ? (int) ($body['rightId'] ?? 0) : $request->query->getInt('rightId');

        if ($leftId <= 0 || $rightId <= 0) {
            throw new BadRequestHttpException('Both leftId and rightId are required.');
        }

        $left = DataObject::getById($leftId);
        $right = DataObject::getById($rightId);
        if (!$left instanceof Concrete) {
            throw new NotFoundHttpException(sprintf('Data object %d not found or not concrete.', $leftId));
        }
        if (!$right instanceof Concrete) {
            throw new NotFoundHttpException(sprintf('Data object %d not found or not concrete.', $rightId));
        }

        // Element view-permission on both objects (T-SEC-001) — throws ForbiddenException if denied.
        $user = $this->securityService->getCurrentUser();
        $this->securityService->hasElementPermission($left, $user, 'view');
        $this->securityService->hasElementPermission($right, $user, 'view');

        return [$left, $right];
    }

    private function diff(Concrete $left, Concrete $right, Request $request): DiffResult
    {
        $user = $this->securityService->getCurrentUser();
        $pimcoreUser = $user instanceof User ? $user : null;
        $hidden = $this->hiddenFieldResolver->hiddenFieldNames($left, $pimcoreUser);

        $localesRaw = (string) ($request->query->get('locales') ?? ($this->body($request)['locales'] ?? ''));
        $locales = array_values(array_filter(array_map('trim', explode(',', $localesRaw))));

        try {
            return $this->comparisonService->compare($left, $right, ['locales' => $locales, 'hiddenFields' => $hidden]);
        } catch (ComparisonException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $content = $request->getContent();
        if ($content === '') {
            return [];
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function etag(Request $request, Concrete $left, Concrete $right): string
    {
        $user = $this->securityService->getCurrentUser();
        $userId = method_exists($user, 'getId') ? (string) $user->getId() : '0';

        return '"' . hash('xxh128', implode('|', [
            (string) $left->getId(), (string) $left->getModificationDate(),
            (string) $right->getId(), (string) $right->getModificationDate(),
            (string) $request->query->get('locales', ''),
            (string) $request->query->get('filter', $this->defaultFilter),
            (string) $request->query->get('query', ''),
            $userId,
        ])) . '"';
    }

    private function notModified(Request $request, string $etag): bool
    {
        $ifNoneMatch = (string) $request->headers->get('If-None-Match', '');

        return $ifNoneMatch !== '' && in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true);
    }

    private function cacheable(Response $response, string $etag): Response
    {
        $response->setEtag(trim($etag, '"'));
        $response->setPrivate();

        return $response;
    }

    private function download(string $content, string $filename, string $contentType): Response
    {
        $response = new Response($content, Response::HTTP_OK);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
