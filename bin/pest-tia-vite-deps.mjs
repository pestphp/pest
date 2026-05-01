#!/usr/bin/env node

import { readdir } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { createRequire } from 'node:module'
import { resolve, relative, extname, posix, sep, join } from 'node:path'
import { pathToFileURL } from 'node:url'

const PAGE_EXTENSIONS = new Set(['.vue', '.tsx', '.jsx', '.svelte'])
const PROJECT_ROOT = resolve(process.argv[2] ?? process.cwd())
const PAGES_REL = (process.env.TIA_VITE_PAGES_DIR ?? 'resources/js/Pages').replace(/\\/g, '/')
const TIMEOUT_MS = Number.parseInt(process.env.TIA_VITE_TIMEOUT_MS ?? '20000', 10)

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

  const killer = setTimeout(() => {
    server.close().catch(() => {}).finally(() => process.exit(2))
  }, TIMEOUT_MS)

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
        continue
      }

      const pageModule = await server.moduleGraph.getModuleByUrl(pageUrl, false)
      if (!pageModule) continue

      const visited = new Set()
      const queue = [pageModule]
      while (queue.length) {
        const mod = queue.shift()
        for (const imported of mod.importedModules) {
          const id = imported.file ?? imported.id
          if (!id || visited.has(id)) continue
          visited.add(id)

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
  void pathToFileURL
  await main()
} catch (err) {
  process.stderr.write(String(err?.stack ?? err ?? 'unknown error'))
  process.exit(1)
}
