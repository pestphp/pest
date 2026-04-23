#!/usr/bin/env node

/**
 * TIA Vite dependency resolver.
 *
 * Spins up a throwaway headless Vite dev server using the project's
 * `vite.config.*`, walks every `resources/js/Pages/**` entry to warm
 * up the module graph, then serializes the graph as a reverse map:
 *
 *   { "<abs source path>": ["<page component name>", ...], ... }
 *
 * The resulting JSON is written to stdout. Stderr is silent on
 * success so Pest can parse stdout without stripping.
 *
 * Why this exists: at TIA record time we need to know which Inertia
 * page components depend on each shared source file (Button.vue,
 * Layouts/*.vue, etc.) so a later edit to one of those files can
 * invalidate only the tests that rendered an affected page. Vite
 * already knows this via its module graph — we borrow it.
 *
 * Called from `Pest\Plugins\Tia\JsModuleGraph::build()` as:
 *
 *   node bin/pest-tia-vite-deps.mjs <absoluteProjectRoot>
 *
 * Environment:
 *   TIA_VITE_PAGES_DIR  override the `resources/js/Pages` default.
 *   TIA_VITE_TIMEOUT_MS  override the 20s internal watchdog.
 */

import { readdir } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { createRequire } from 'node:module'
import { resolve, relative, extname, posix, sep, join } from 'node:path'
import { pathToFileURL } from 'node:url'

const PAGE_EXTENSIONS = new Set(['.vue', '.tsx', '.jsx', '.svelte'])
const PROJECT_ROOT = resolve(process.argv[2] ?? process.cwd())
const PAGES_REL = (process.env.TIA_VITE_PAGES_DIR ?? 'resources/js/Pages').replace(/\\/g, '/')
const TIMEOUT_MS = Number.parseInt(process.env.TIA_VITE_TIMEOUT_MS ?? '20000', 10)

// Resolve Vite from the project's own `node_modules`, not from this
// helper's location (which lives under `vendor/pestphp/pest/bin/` and
// has no `node_modules`). `createRequire` anchored at the project
// root walks up from there, matching the resolution behaviour any
// project-local script would see.
async function loadVite() {
  const projectRequire = createRequire(join(PROJECT_ROOT, 'package.json'))
  const vitePath = projectRequire.resolve('vite')
  return await import(pathToFileURL(vitePath).href)
}

const { createServer } = await loadVite()

async function listPageFiles(pagesDir) {
  if (!existsSync(pagesDir)) return []

  const out = []
  const walk = async (dir) => {
    let entries
    try { entries = await readdir(dir, { withFileTypes: true }) } catch { return }
    for (const entry of entries) {
      const full = resolve(dir, entry.name)
      if (entry.isDirectory()) { await walk(full); continue }
      if (PAGE_EXTENSIONS.has(extname(entry.name))) out.push(full)
    }
  }

  await walk(pagesDir)
  return out
}

function componentNameFor(pageAbs, pagesDir) {
  const rel = relative(pagesDir, pageAbs).split(sep).join('/')
  const ext = extname(rel)
  return rel.slice(0, rel.length - ext.length)
}

async function main() {
  const pagesDir = resolve(PROJECT_ROOT, PAGES_REL)
  const pages = await listPageFiles(pagesDir)

  if (pages.length === 0) {
    process.stdout.write('{}')
    return
  }

  // Boot Vite in middleware mode (no port binding, no HMR server).
  // We only need the module graph; transformRequest per page warms
  // it without running a bundle.
  const server = await createServer({
    configFile: undefined, // auto-detect vite.config.*
    root: PROJECT_ROOT,
    logLevel: 'silent',
    clearScreen: false,
    server: {
      middlewareMode: true,
      hmr: false,
      watch: null,
    },
    appType: 'custom',
    optimizeDeps: { disabled: true },
  })

  // Watchdog — don't let a pathological config hang the record run.
  const killer = setTimeout(() => {
    server.close().catch(() => {}).finally(() => process.exit(2))
  }, TIMEOUT_MS)

  // Reverse map: depSourcePath → Set<component name>.
  const reverse = new Map()

  const pageComponentCache = new Map()
  for (const page of pages) {
    pageComponentCache.set(page, componentNameFor(page, pagesDir))
  }

  try {
    for (const pagePath of pages) {
      const pageComponent = pageComponentCache.get(pagePath)
      const pageUrl = '/' + posix.relative(
        PROJECT_ROOT.split(sep).join('/'),
        pagePath.split(sep).join('/'),
      )

      try {
        await server.transformRequest(pageUrl, { ssr: false })
      } catch {
        // Transform errors (missing deps, syntax issues) shouldn't
        // poison the whole graph — skip this page and continue.
        continue
      }

      const pageModule = await server.moduleGraph.getModuleByUrl(pageUrl, false)
      if (!pageModule) continue

      // BFS over importedModules, scoped to files inside the project.
      const visited = new Set()
      const queue = [pageModule]
      while (queue.length) {
        const mod = queue.shift()
        for (const imported of mod.importedModules) {
          const id = imported.file ?? imported.id
          if (!id || visited.has(id)) continue
          visited.add(id)

          // Skip files outside the project root (node_modules, etc.)
          // and virtual modules (`\0`-prefixed ids from plugins).
          if (id.startsWith('\0')) continue
          if (!id.startsWith(PROJECT_ROOT)) continue

          const rel = relative(PROJECT_ROOT, id).split(sep).join('/')
          const bucket = reverse.get(rel) ?? new Set()
          bucket.add(pageComponent)
          reverse.set(rel, bucket)

          queue.push(imported)
        }
      }
    }
  } finally {
    clearTimeout(killer)
    await server.close()
  }

  const payload = Object.create(null)
  const keys = [...reverse.keys()].sort()
  for (const key of keys) {
    payload[key] = [...reverse.get(key)].sort()
  }

  process.stdout.write(JSON.stringify(payload))
}

try {
  // Node 20 dynamic-import path — some environments are pickier than others.
  void pathToFileURL // retained to silence tree-shakers referencing the import
  await main()
} catch (err) {
  process.stderr.write(String(err?.stack ?? err ?? 'unknown error'))
  process.exit(1)
}
