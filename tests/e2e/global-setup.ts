import { request, type FullConfig } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

/**
 * Logs into Pimcore Studio once (session cookie shared via storageState) and records the pair of
 * object ids under test, so the specs run against an authenticated session like the real UI.
 */
export default async function globalSetup (config: FullConfig): Promise<void> {
  const baseURL = (config.projects[0]?.use?.baseURL as string | undefined) ?? process.env.STUDIO_BASE_URL ?? 'http://localhost'
  const user = process.env.STUDIO_USER ?? 'admin'
  const password = process.env.STUDIO_PASSWORD ?? 'admin'

  const ctx = await request.newContext({ baseURL })

  const login = await ctx.post('/pimcore-studio/api/login', {
    headers: { 'Content-Type': 'application/json' },
    data: { username: user, password }
  })
  if (!login.ok()) {
    throw new Error(`Studio login failed (${login.status()}): ${await login.text()}`)
  }

  const authDir = path.join(__dirname, '.auth')
  fs.mkdirSync(authDir, { recursive: true })
  await ctx.storageState({ path: path.join(authDir, 'state.json') })
  await ctx.dispose()

  process.env.COMPARISON_LEFT = process.env.COMPARISON_LEFT ?? '5550'
  process.env.COMPARISON_RIGHT = process.env.COMPARISON_RIGHT ?? '5551'
}
