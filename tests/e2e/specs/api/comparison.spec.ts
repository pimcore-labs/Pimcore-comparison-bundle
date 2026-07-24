import { test, expect, type APIResponse } from '@playwright/test'

const LEFT = () => Number(process.env.COMPARISON_LEFT ?? 5550)
const RIGHT = () => Number(process.env.COMPARISON_RIGHT ?? 5551)
const OBJECTS = () => `/pimcore-studio/api/comparison/objects?leftId=${LEFT()}&rightId=${RIGHT()}&filter=differences`

/**
 * Parse a JSON response, tolerating the Symfony DEV web-profiler artefact: with display_errors on, the
 * profiler's LoggerDataCollector can append a `<!-- Warning: unserialize(): … -->` after the body. That
 * is dev-environment noise, not part of the API payload (prod responses are clean), so we cut it off.
 */
async function readJson (res: APIResponse): Promise<any> {
  const text = await res.text()
  return JSON.parse(text.replace(/<!--[\s\S]*$/, '').trim())
}

test.describe('comparison REST API', () => {
  test('E2E-CMP-003 @feature:api.rest objects returns a same-class diff tree', async ({ request }) => {
    const res = await request.get(OBJECTS())
    expect(res.status()).toBe(200)
    const body = await readJson(res)
    expect(body.className).toBeTruthy()
    expect(body.leftId).toBe(LEFT())
    expect(body.rightId).toBe(RIGHT())
    expect(Array.isArray(body.fields)).toBeTruthy()
    expect(body.summary.total).toBeGreaterThan(0)
    // a differences-only view contains only difference statuses (+ container rows)
    for (const f of body.fields) {
      if (f.children === undefined || f.children.length === 0) {
        expect(['changed', 'only-left', 'only-right', 'reordered', 'not-comparable']).toContain(f.status)
      }
    }
  })

  test('E2E-CMP-021 @feature:api.rest objects/summary returns per-status counts', async ({ request }) => {
    const res = await request.get(`/pimcore-studio/api/comparison/objects/summary?leftId=${LEFT()}&rightId=${RIGHT()}`)
    expect(res.status()).toBe(200)
    const body = await readJson(res)
    expect(body.total).toBeGreaterThan(0)
    expect(body.counts).toHaveProperty('changed')
    expect(body.differing).toBeGreaterThanOrEqual(0)
  })

  test('@feature:api.rest ETag makes a conditional GET return 304', async ({ request }) => {
    const first = await request.get(OBJECTS())
    const etag = first.headers()['etag']
    expect(etag).toBeTruthy()
    const second = await request.get(OBJECTS(), { headers: { 'If-None-Match': etag } })
    expect(second.status()).toBe(304)
  })

  test('E2E-CMP-019 @feature:api.rest export XLSX returns a workbook', async ({ request }) => {
    const res = await request.post('/pimcore-studio/api/comparison/objects/export', {
      headers: { 'Content-Type': 'application/json' },
      data: { leftId: LEFT(), rightId: RIGHT(), format: 'xlsx', filter: 'differences' }
    })
    expect(res.status()).toBe(200)
    expect(res.headers()['content-type']).toContain('spreadsheetml')
    const buf = await res.body()
    expect(buf.length).toBeGreaterThan(0)
    expect(buf.subarray(0, 2).toString('latin1')).toBe('PK') // xlsx is a zip
  })

  test('E2E-CMP-020 @feature:api.rest export JSON honors the filter', async ({ request }) => {
    const res = await request.post('/pimcore-studio/api/comparison/objects/export', {
      headers: { 'Content-Type': 'application/json' },
      data: { leftId: LEFT(), rightId: RIGHT(), format: 'json', filter: 'all' }
    })
    expect(res.status()).toBe(200)
    const body = await readJson(res)
    expect(body.className).toBeTruthy()
    expect(Array.isArray(body.fields)).toBeTruthy()
  })

  test('T-SEC-001 @feature:api.rest an unauthenticated request is refused', async ({ playwright }) => {
    const anon = await playwright.request.newContext({
      baseURL: process.env.STUDIO_BASE_URL ?? 'http://localhost',
      storageState: { cookies: [], origins: [] }
    })
    const res = await anon.get(OBJECTS())
    expect(res.status()).toBe(401)
    await anon.dispose()
  })

  test('@feature:ui.comparison-view the Studio shell wires the comparison plugin remote', async ({ request }) => {
    const res = await request.get('/pimcore-studio/')
    expect(res.status()).toBe(200)
    const html = await res.text()
    // the shell server-side injects each plugin's exposeRemote; ours is under pimcorecomparison
    expect(html).toContain('pimcorecomparison')
  })
})
