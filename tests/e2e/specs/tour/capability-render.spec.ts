import { test } from '@playwright/test'
import * as path from 'path'
import { pathToFileURL, fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

/**
 * Records the narrated capability walkthrough from a self-contained, real-shape diff-table render
 * (tour/capability-walkthrough.html). Unlike the live-Studio tour, this loads instantly and plays a
 * deterministic ~28s timeline, so the recording is stable frame-for-frame for the voiceover mux.
 */
test('@tour capability-walkthrough render', async ({ page }) => {
  test.setTimeout(90_000)
  const file = pathToFileURL(path.join(__dirname, '..', '..', 'tour', 'capability-walkthrough.html')).href
  await page.goto(file, { waitUntil: 'load' })
  await page.waitForTimeout(600)
  await page.evaluate(async () => { await (window as unknown as { __play: () => Promise<void> }).__play() })
  await page.waitForTimeout(800)
})
