# Installation

## Requirements

- Pimcore ≥ 2026.1 (Studio UI)
- PHP ≥ 8.4
- `phpoffice/phpspreadsheet` (already a Pimcore dependency) for XLSX export

## Install

In this skeleton the bundle is wired through the root `composer.json` PSR-4 autoload and
`config/bundles.php`. Install it (this registers the `plugin_comparison` permission):

```bash
docker compose exec php php -d memory_limit=1024M bin/console pimcore:bundle:install PimcoreComparisonBundle
docker compose exec php php -d memory_limit=1024M bin/console cache:clear
```

As a standalone Composer package in another Pimcore project:

```bash
composer require pimcore/comparison-bundle
bin/console pimcore:bundle:install PimcoreComparisonBundle
```

## Grant access

The feature is gated by the `plugin_comparison` permission (category **Comparison**). Grant it to the
relevant roles/users in **Settings → Users / Roles**. Admins have it implicitly. Comparing two objects
additionally requires the user to have **view** permission on both objects.

## Verify

```bash
# The bundle should report as installed
docker compose exec php php -d memory_limit=1024M bin/console pimcore:bundle:list | grep Comparison

# The REST surface is registered and sits behind the Studio auth firewall
docker compose exec php php -d memory_limit=1024M bin/console debug:router | grep comparison

# Diff two objects from the CLI (a quick smoke of the whole engine)
docker compose exec php php -d memory_limit=1024M bin/console comparison:diff <leftId> <rightId>
```

## Configuration (optional)

```yaml
# config/packages/pimcore_comparison.yaml
pimcore_comparison:
    normalization:
        trim: true                   # trim-insensitive string comparison
        numeric_epsilon: 0.0         # tolerance for numeric equality
        empty_string_equals_null: true
    default_filter: differences      # all | differences | equal
    export:
        formats: [xlsx, json]
    excluded_fields:
        Product: [internalNotes]     # per-class field names to skip
```
