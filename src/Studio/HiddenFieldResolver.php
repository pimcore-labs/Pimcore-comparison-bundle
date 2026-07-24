<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Studio;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Layout;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service as DataObjectService;
use Pimcore\Model\User;

/**
 * Resolves the set of field names the given user may NOT see on an object's layout (C-2, T-SEC-002).
 * Reuses Pimcore's own per-user layout enrichment — `Service::enrichLayoutDefinition()` stamps
 * `permissionView` on each field for the user — so there is no new permission model. The resolved
 * names are handed to ComparisonService, which emits those fields as `hidden` without values.
 *
 * Fail-safe: admins (and any case we cannot resolve) mask nothing; element-level view permission on
 * the object is enforced separately by the controller, so a resolution miss never exposes an object
 * the user could not already open.
 */
final class HiddenFieldResolver
{
    /**
     * @return list<string> field names to hide
     */
    public function hiddenFieldNames(Concrete $object, ?User $user): array
    {
        if ($user === null || $user->isAdmin()) {
            return [];
        }

        $class = $object->getClass();
        $root = $class->getLayoutDefinitions();
        if (!$root instanceof Layout) {
            return [];
        }

        try {
            // Deep-clone so we never mutate the class's shared layout instance.
            $layout = unserialize(serialize($root), ['allowed_classes' => true]);
            if (!$layout instanceof Layout) {
                return [];
            }
            DataObjectService::enrichLayoutDefinition($layout, $object, [], $user);

            $hidden = [];
            $this->collectHidden($layout, $hidden);

            return array_values(array_unique($hidden));
        } catch (\Throwable) {
            // Fail closed to "no masking" — the element-level view gate still applies.
            return [];
        }
    }

    /**
     * @param list<string> $hidden
     */
    private function collectHidden(Layout|Data $node, array &$hidden): void
    {
        if ($node instanceof Data) {
            // enrichLayoutPermissions sets permissionView === false on fields the user may not view.
            if (method_exists($node, 'getPermissionView') && $node->getPermissionView() === false) {
                $hidden[] = $node->getName();
            }

            return;
        }

        if (!$node->hasChildren()) {
            return;
        }
        foreach ($node->getChildren() as $child) {
            if ($child instanceof Layout || $child instanceof Data) {
                $this->collectHidden($child, $hidden);
            }
        }
    }
}
