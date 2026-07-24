import { defineConfig } from '@playwright/test'

/**
 * PimcoreComparisonBundle E2E. The `api` project drives the REST surface over an authenticated Studio
 * session (no browser needed); the `tour` project records the capability walkthroughs for docs.
 *
 * Requires a running Studio (STUDIO_BASE_URL, default http://localhost), the admin login
 * (STUDIO_USER/STUDIO_PASSWORD, default admin/admin), and two comparable Product objects
 * (COMPARISON_LEFT/COMPARISON_RIGHT, default 5550/5551). global-setup logs in once (shared session).
 */
export default defineConfig({
  testDir: './specs',
  timeout: 60_000,
  expect: { timeout: 15_000 },
  retries: 0,
  workers: 1,
  reporter: [['list'], ['json', { outputFile: 'pw.json' }]],
  globalSetup: './global-setup',
  use: {
    baseURL: process.env.STUDIO_BASE_URL ?? 'http://localhost',
    storageState: '.auth/state.json'
  },
  projects: [
    { name: 'api', testMatch: /api\/.*\.spec\.ts/ }
  ]
})
