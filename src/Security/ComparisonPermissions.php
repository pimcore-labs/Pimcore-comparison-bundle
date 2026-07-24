<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Security;

/**
 * The permission catalogue (base-idea §7). Registered into Pimcore's permission system on install so
 * the keys appear as ordinary checkboxes in the user/role editor and are checked with the usual
 * $user->isAllowed(...). Default-deny for non-admins; admins bypass via Pimcore's own isAllowed().
 *
 * v1 ships a single master gate — `plugin_comparison` — that enables the compare feature per role.
 * Element view-permission on the two compared objects is enforced separately, per request.
 */
final class ComparisonPermissions
{
    public const CATEGORY = 'Comparison';

    /** Master gate — use the object comparison feature at all. */
    public const COMPARISON = 'plugin_comparison';

    /** @return string[] every permission key, in catalogue order */
    public static function all(): array
    {
        return [
            self::COMPARISON,
        ];
    }
}
