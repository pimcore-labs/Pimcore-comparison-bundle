# REST API reference

All endpoints live under the Studio API prefix `/pimcore-studio/api/comparison` and therefore sit inside
the Studio authentication firewall — an unauthenticated request returns `401`. Every request also requires
the `plugin_comparison` permission and **view** permission on both objects. Responses are read-only,
computed on demand, and carry an `ETag` over the two objects' modification dates (send `If-None-Match` to
get a `304`).

## `GET /pimcore-studio/api/comparison/objects`

Full (filtered) diff.

| Query param | Required | Notes |
|---|---|---|
| `leftId` | yes | left object id |
| `rightId` | yes | right object id |
| `filter` | no | `all` \| `differences` \| `equal` (default from config, `differences`) |
| `query` | no | free-text field-label filter |
| `locales` | no | comma-separated locales for localized fields |

Response `200`:

```json
{
  "leftId": 5550,
  "rightId": 5551,
  "className": "Product",
  "fields": [
    { "name": "sku", "label": "SKU", "fieldtype": "input", "status": "changed",
      "leftDisplay": "AB-100002", "rightDisplay": "AB-100003" },
    { "name": "localizedfields", "label": "Localized Content", "fieldtype": "localizedfields",
      "status": "changed", "meta": { "section": "Localized Content" },
      "children": [
        { "name": "name.en", "label": "Product Name [en]", "fieldtype": "input", "status": "changed",
          "leftDisplay": "Smart Controller X200", "rightDisplay": "Power Supply Unit 500W",
          "meta": { "locale": "en" } }
      ] }
  ],
  "summary": { "total": 30, "differing": 13, "counts": { "equal": 17, "changed": 13, "only-left": 0, "only-right": 0, "reordered": 0, "not-comparable": 0, "hidden": 0 } }
}
```

Row `status` is one of `equal`, `changed`, `only-left`, `only-right`, `reordered`, `not-comparable`,
`hidden`. Textual rows may carry `inlineDiff` (a list of `{op: equal|insert|delete, text}` tokens);
relation rows carry `meta.chips` (`{label, state: kept|added|removed|moved}`); permission-hidden rows have
status `hidden` and no values.

## `GET /pimcore-studio/api/comparison/objects/summary`

Counts only (badges, dedup tooling). Same params (`leftId`, `rightId`, `locales`). Response:

```json
{ "leftId": 5550, "rightId": 5551, "className": "Product",
  "total": 30, "differing": 13,
  "counts": { "equal": 17, "changed": 13, "only-left": 0, "only-right": 0, "reordered": 0, "not-comparable": 0, "hidden": 0 } }
```

## `POST /pimcore-studio/api/comparison/objects/export`

XLSX or JSON export of the current filtered view. JSON body:

```json
{ "leftId": 5550, "rightId": 5551, "format": "xlsx", "filter": "differences", "query": "", "locales": "en,de" }
```

`format` is `xlsx` or `json` (must be enabled in `export.formats`). Returns a file download
(`Content-Disposition: attachment`) — a `.xlsx` workbook or a `.json` document — honouring the same
filtering and permission masking as the view (T-SEC-006).

## Errors

| Status | When |
|---|---|
| `400` | missing/invalid ids, class mismatch, same object, unsupported export format |
| `401` | unauthenticated |
| `403` | missing `plugin_comparison` or no view permission on an object |
| `404` | an id does not resolve to a concrete object |

## CLI

```bash
bin/console comparison:diff <leftId> <rightId> [--locales=en,de] [--filter=all|differences|equal]
```
