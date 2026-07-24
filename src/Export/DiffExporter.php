<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\Export;

use Pimcore\Bundle\ComparisonBundle\Comparison\DiffResult;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Serializes a (filtered) diff to XLSX or JSON (FR-CMP-021/022). The tree is flattened to one row
 * per leaf, keeping the container path so nested field-collection/brick/localized rows stay legible.
 * Callers pass an already-filtered field list so the file honours the active view (T-SEC-006).
 */
final class DiffExporter
{
    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_JSON = 'json';

    /**
     * @param list<FieldDiff> $fields already filtered/masked field diffs
     */
    public function toJson(DiffResult $result, array $fields): string
    {
        return (string) json_encode([
            'leftId' => $result->leftId,
            'rightId' => $result->rightId,
            'className' => $result->className,
            'fields' => $fields,
            'summary' => [
                'total' => $result->total(),
                'differing' => $result->differing(),
                'counts' => $result->counts(),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<FieldDiff> $fields already filtered/masked field diffs
     *
     * @return string binary XLSX content
     */
    public function toXlsx(DiffResult $result, array $fields): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comparison');

        $sheet->fromArray(['Section', 'Field', 'Left (#' . $result->leftId . ')', 'Right (#' . $result->rightId . ')', 'Status'], null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $row = 2;
        foreach ($this->flatten($fields) as $line) {
            $sheet->fromArray([
                $line['section'],
                $line['field'],
                $line['left'],
                $line['right'],
                $line['status'],
            ], null, 'A' . $row, true);
            ++$row;
        }

        foreach (['A' => 24, 'B' => 32, 'C' => 40, 'D' => 40, 'E' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $writer = new Xlsx($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'cmp_') ?: (sys_get_temp_dir() . '/cmp_' . uniqid());
        try {
            $writer->save($tmp);

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param list<FieldDiff> $fields
     *
     * @return list<array{section: string, field: string, left: string, right: string, status: string}>
     */
    private function flatten(array $fields, string $prefix = ''): array
    {
        $rows = [];
        foreach ($fields as $field) {
            $label = $prefix === '' ? $field->label : $prefix . ' › ' . $field->label;
            if ($field->children !== []) {
                $rows = array_merge($rows, $this->flatten($field->children, $label));

                continue;
            }
            $rows[] = [
                'section' => (string) ($field->meta['section'] ?? ''),
                'field' => $label,
                'left' => $this->scalarize($field->leftDisplay),
                'right' => $this->scalarize($field->rightDisplay),
                'status' => $field->status->value,
            ];
        }

        return $rows;
    }

    private function scalarize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
