import { test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const REC = path.join(__dirname, '..', '..', 'tour-recordings')

const LEFT = Number(process.env.COMPARISON_LEFT ?? 5550)
const RIGHT = Number(process.env.COMPARISON_RIGHT ?? 5551)

/**
 * Records a narrated walkthrough of the object-comparison capability. A fresh browser context (no
 * restored Studio tabs) plus the deep link renders the populated diff cleanly. `marks` captures the
 * elapsed seconds at each screen so gen-narration.mjs lines the voiceover up with the recording.
 */
test('@tour comparison-capability', async ({ page }) => {
  test.setTimeout(120_000) // Studio SDK boots slowly headless
  fs.mkdirSync(REC, { recursive: true })
  const t0 = Date.now()
  const marks: Array<{ at: number, screen: string }> = []
  const mark = (screen: string) => marks.push({ at: (Date.now() - t0) / 1000, screen })

  try {
    // Screen 1 — open the comparison view via the deep link (Studio never goes network-idle → DCL only).
    mark('intro')
    await page.goto(`/pimcore-studio/?left=${LEFT}&right=${RIGHT}`, { waitUntil: 'domcontentloaded', timeout: 45000 })
    // Studio SDK boots slowly headless; wait for the comparison view to render (populated or empty state).
    await page.getByText(/read-only \(v1\)|Select two objects to compare|fields differ/i)
      .first().waitFor({ timeout: 60000 }).catch(() => {})
    await page.waitForTimeout(2500)

    // Screen 2 — the diff table (differences by default). Snapshot so the content is verifiable.
    mark('difftable')
    await page.screenshot({ path: path.join(REC, 'tour-difftable.png') }).catch(() => {})
    await page.waitForTimeout(6000)

    // Screen 3 — filters (best-effort; the recording continues regardless).
    mark('filters')
    for (const label of ['All fields', 'Equal only', 'Differences only']) {
      const seg = page.getByText(label, { exact: false }).first()
      if (await seg.isVisible().catch(() => false)) {
        await seg.click({ timeout: 4000 }).catch(() => {})
        await page.waitForTimeout(2200)
      }
    }

    // Screen 4 — export + summary.
    mark('export')
    const exportBtn = page.getByText('Export', { exact: false }).first()
    if (await exportBtn.isVisible().catch(() => false)) {
      await exportBtn.click({ timeout: 4000 }).catch(() => {})
      await page.waitForTimeout(2500)
      await page.keyboard.press('Escape').catch(() => {})
    }
    await page.waitForTimeout(4000)
    mark('end')
  } finally {
    fs.writeFileSync(path.join(REC, 'comparison-capability.marks.json'), JSON.stringify(marks, null, 2))
  }
})
