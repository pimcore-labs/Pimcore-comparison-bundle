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
    { name: 'api', testMatch: /api\/.*\.spec\.ts/ },
    // Screen-recording "tours" for the narrated capability videos (docs/02_Capabilities). NOT part of
    // any gate. Video on, clean 16:10 viewport, slowMo for watchability. Recordings land in
    // ./tour-recordings; scripts/gen-narration.mjs voices + muxes them into docs MP4s.
    {
      name: 'tour',
      testMatch: /tour\/.*\.spec\.ts/,
      outputDir: './tour-recordings',
      use: {
        headless: true,
        channel: 'chromium',
        viewport: { width: 1600, height: 1000 },
        video: { mode: 'on', size: { width: 1600, height: 1000 } },
        launchOptions: { slowMo: 300 },
        actionTimeout: 20_000
      }
    }
  ]
})
