#!/usr/bin/env node

import { readdir, readFile } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import { createRequire } from 'node:module'
import { resolve, relative, extname, sep, join } from 'node:path'
import { pathToFileURL } from 'node:url'

const PAGE_EXTENSIONS = new Set([
  '.vue', '.svelte',
  '.tsx', '.jsx',
  '.ts', '.js',
  '.mts', '.cts', '.mjs', '.cjs',
])
const ASSET_EXT_RE = /\.(css|scss|sass|less|styl|stylus|svg|png|jpe?g|gif|webp|avif|ico|bmp|woff2?|ttf|eot|otf|md|mdx|txt|html|mp4|webm|mp3|wav|ogg|m4a|pdf|wasm|glsl|frag|vert)$/i
const PROJECT_ROOT = resolve(process.argv[2] ?? process.cwd())
const PAGE_DIR_CANDIDATES = [
  'resources/js/Pages',
  'resources/js/pages',
  'assets/js/Pages',
  'assets/js/pages',
  'assets/Pages',
  'assets/pages',
]

async function loadRolldown() {
  const projectRequire = createRequire(join(PROJECT_ROOT, 'package.json'))
  const path = projectRequire.resolve('rolldown')
  return await import(pathToFileURL(path).href)
}

async function readJsonWithComments(path) {
  const raw = await readFile(path, 'utf8')
  const stripped = raw
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/(^|[^:])\/\/[^\n]*/g, '$1')
  return JSON.parse(stripped)
}

async function loadAliasFromTsconfig() {
  const alias = {}
  for (const name of ['tsconfig.json', 'jsconfig.json']) {
    const p = join(PROJECT_ROOT, name)
    if (!existsSync(p)) continue
    let cfg
    try { cfg = await readJsonWithComments(p) } catch { continue }
    const baseUrl = resolve(PROJECT_ROOT, cfg?.compilerOptions?.baseUrl ?? '.')
    const paths = cfg?.compilerOptions?.paths ?? {}
    for (const [key, targets] of Object.entries(paths)) {
      if (!key.endsWith('/*')) continue
      const t0 = Array.isArray(targets) ? targets[0] : null
      if (typeof t0 !== 'string' || !t0.endsWith('/*')) continue
      const aliasKey = key.slice(0, -2)
      if (alias[aliasKey] !== undefined) continue
      alias[aliasKey] = resolve(baseUrl, t0.slice(0, -2))
    }
  }
  return alias
}

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

async function discoverPagesDir() {
  const override = process.env.TIA_VITE_PAGES_DIR
  if (override && override.length > 0) {
    return resolve(PROJECT_ROOT, override.replace(/\\/g, '/'))
  }

  for (const rel of PAGE_DIR_CANDIDATES) {
    const abs = resolve(PROJECT_ROOT, rel)
    if (!existsSync(abs)) continue
    const files = await listPageFiles(abs)
    if (files.length > 0) return abs
  }

  return null
}

function componentNameFor(pageAbs, pagesDir) {
  const rel = relative(pagesDir, pageAbs).split(sep).join('/')
  const ext = extname(rel)
  return rel.slice(0, rel.length - ext.length)
}

function isLocalSpecifier(source, aliasKeys) {
  if (source.startsWith('.') || source.startsWith('/')) return true
  for (const key of aliasKeys) {
    if (source === key || source.startsWith(key + '/')) return true
  }
  return false
}

async function main() {
  const pagesDir = await discoverPagesDir()

  if (pagesDir === null) {
    process.stdout.write('{}')
    return
  }

  const pages = await listPageFiles(pagesDir)

  if (pages.length === 0) {
    process.stdout.write('{}')
    return
  }

  const { rolldown } = await loadRolldown()
  const alias = await loadAliasFromTsconfig()
  const aliasKeys = Object.keys(alias)

  const graph = new Map()

  const collector = {
    name: 'pest-tia-collector',
    moduleParsed(info) {
      const id = info.id
      if (!id || id.startsWith('\0')) return
      const deps = new Set()
      for (const i of info.importedIds) if (i && !i.startsWith('\0')) deps.add(i)
      for (const i of info.dynamicallyImportedIds) if (i && !i.startsWith('\0')) deps.add(i)
      graph.set(id, deps)
    },
  }

  const externalBare = {
    name: 'pest-tia-external-bare',
    resolveId(source) {
      if (!source) return null
      if (isLocalSpecifier(source, aliasKeys)) return null
      return { id: source, external: true }
    },
  }

  const assetStub = {
    name: 'pest-tia-asset-stub',
    load(id) {
      if (!id) return null
      if (ASSET_EXT_RE.test(id)) {
        return { code: 'export default null', moduleSideEffects: false }
      }
      return null
    },
  }

  const input = Object.create(null)
  for (let i = 0; i < pages.length; i++) input[`p${i}`] = pages[i]

  const bundle = await rolldown({
    input,
    cwd: PROJECT_ROOT,
    resolve: {
      alias,
      extensions: ['.tsx', '.ts', '.jsx', '.js', '.mjs', '.cjs', '.json'],
    },
    transform: { jsx: 'preserve' },
    treeshake: false,
    plugins: [externalBare, assetStub, collector],
    logLevel: 'silent',
    onLog: () => {},
  })

  try {
    await bundle.generate({ format: 'esm' })
  } finally {
    await bundle.close()
  }

  const reverse = new Map()
  const transitiveCache = new Map()

  const computeTransitive = (id, stack) => {
    const cached = transitiveCache.get(id)
    if (cached) return cached
    if (stack.has(id)) return null

    stack.add(id)
    const acc = new Set()
    const deps = graph.get(id)
    if (deps) {
      for (const dep of deps) {
        if (!dep || dep.startsWith('\0')) continue
        if (dep.startsWith(PROJECT_ROOT)) {
          const rel = relative(PROJECT_ROOT, dep).split(sep).join('/')
          acc.add(rel)
        }
        if (stack.has(dep)) continue
        const child = computeTransitive(dep, stack)
        if (child) for (const r of child) acc.add(r)
      }
    }
    stack.delete(id)
    transitiveCache.set(id, acc)
    return acc
  }

  for (const page of pages) {
    const pageComponent = componentNameFor(page, pagesDir)
    const reachable = computeTransitive(page, new Set())
    if (!reachable) continue
    for (const rel of reachable) {
      const bucket = reverse.get(rel) ?? new Set()
      bucket.add(pageComponent)
      reverse.set(rel, bucket)
    }
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
