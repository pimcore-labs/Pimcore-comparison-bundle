<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Diff;

/**
 * The normalized diff of a single attribute (base-idea §6.2). A comparator returns one of these;
 * containers (localized fields, field collections, bricks, classification store) nest child
 * FieldDiffs via {@see $children}. The frontend renders this DTO directly (thin client, C-5).
 *
 * @phpstan-type InlineToken array{op: 'equal'|'insert'|'delete', text: string}
 */
final class FieldDiff implements \JsonSerializable
{
    /**
     * @param list<FieldDiff>            $children  nested rows (container fieldtypes / localized languages)
     * @param array<string, mixed>       $meta      free-form (e.g. relation add/remove counts, language code)
     * @param list<array{op: string, text: string}>|null $inlineDiff  token list for inline text diff
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $fieldtype,
        public readonly DiffStatus $status,
        public readonly mixed $leftDisplay = null,
        public readonly mixed $rightDisplay = null,
        public readonly ?array $inlineDiff = null,
        public readonly array $children = [],
        public readonly array $meta = [],
    ) {
    }

    public function withChildren(array $children): self
    {
        return new self(
            $this->name,
            $this->label,
            $this->fieldtype,
            $this->status,
            $this->leftDisplay,
            $this->rightDisplay,
            $this->inlineDiff,
            $children,
            $this->meta,
        );
    }

    /** Produce the mirror-image diff for the "swap sides" action. */
    public function swapped(): self
    {
        return new self(
            $this->name,
            $this->label,
            $this->fieldtype,
            $this->status->swapped(),
            $this->rightDisplay,
            $this->leftDisplay,
            $this->inlineDiff === null ? null : array_map(
                static fn (array $t): array => [
                    'op' => match ($t['op']) {
                        'insert' => 'delete',
                        'delete' => 'insert',
                        default => 'equal',
                    },
                    'text' => $t['text'],
                ],
                $this->inlineDiff,
            ),
            array_map(static fn (FieldDiff $c): FieldDiff => $c->swapped(), $this->children),
            $this->meta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $out = [
            'name' => $this->name,
            'label' => $this->label,
            'fieldtype' => $this->fieldtype,
            'status' => $this->status->value,
            'leftDisplay' => $this->leftDisplay,
            'rightDisplay' => $this->rightDisplay,
        ];
        if ($this->inlineDiff !== null) {
            $out['inlineDiff'] = $this->inlineDiff;
        }
        if ($this->children !== []) {
            $out['children'] = $this->children;
        }
        if ($this->meta !== []) {
            $out['meta'] = $this->meta;
        }

        return $out;
    }
}
