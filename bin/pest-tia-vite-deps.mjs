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
  let path = null

  try { path = projectRequire.resolve('rolldown') } catch {}

  if (path === null) {
    // rolldown-vite installs (vite@npm:rolldown-vite) ship rolldown as a
    // dependency of the vite package rather than a top-level install.
    try {
      const viteRequire = createRequire(projectRequire.resolve('vite/package.json'))
      path = viteRequire.resolve('rolldown')
    } catch {}
  }

  if (path === null) return null

  return await import(pathToFileURL(path).href)
}

export function stripJsonComments(raw) {
  let out = ''
  let inString = false
  let quote = ''
  let inLine = false
  let inBlock = false

  for (let i = 0; i < raw.length; i++) {
    const c = raw[i]
    const n = raw[i + 1]

    if (inLine) {
      if (c === '\n') { inLine = false; out += c }
      continue
    }
    if (inBlock) {
      if (c === '*' && n === '/') { inBlock = false; i++ }
      continue
    }
    if (inString) {
      out += c
      if (c === '\\') { out += n ?? ''; i++; continue }
      if (c === quote) inString = false
      continue
    }
    if (c === '"' || c === "'") { inString = true; quote = c; out += c; continue }
    if (c === '/' && n === '/') { inLine = true; i++; continue }
    if (c === '/' && n === '*') { inBlock = true; i++; continue }
    if (c === '}' || c === ']') out = out.replace(/,\s*$/, '')
    out += c
  }

  return out
}

async function readJsonWithComments(path) {
  const raw = await readFile(path, 'utf8')
  return JSON.parse(stripJsonComments(raw))
}

export async function loadAliasFromTsconfig(projectRoot = PROJECT_ROOT) {
  const alias = {}
  for (const name of ['tsconfig.json', 'jsconfig.json']) {
    const p = join(projectRoot, name)
    if (!existsSync(p)) continue
    let cfg
    try { cfg = await readJsonWithComments(p) } catch { continue }
    const baseUrl = resolve(projectRoot, cfg?.compilerOptions?.baseUrl ?? '.')
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

const VITE_CONFIG_FILES = [
  'vite.config.ts',
  'vite.config.js',
  'vite.config.mjs',
  'vite.config.cjs',
  'vite.config.mts',
  'vite.config.cts',
]

function resolveAliasTarget(projectRoot, target) {
  if (target.startsWith('/')) {
    // Vite resolves a leading slash against the project root, unless the
    // config author used a genuinely absolute path.
    return existsSync(target) ? target : resolve(projectRoot, '.' + target)
  }

  return resolve(projectRoot, target)
}

async function usesLaravelVitePlugin(projectRoot) {
  const p = join(projectRoot, 'package.json')
  if (!existsSync(p)) return false

  try {
    const pkg = JSON.parse(await readFile(p, 'utf8'))
    return Boolean(pkg.dependencies?.['laravel-vite-plugin'] ?? pkg.devDependencies?.['laravel-vite-plugin'])
  } catch {
    return false
  }
}

export async function loadAliasFromViteConfig(projectRoot = PROJECT_ROOT) {
  const alias = {}
  let source = null

  for (const name of VITE_CONFIG_FILES) {
    const p = join(projectRoot, name)
    if (!existsSync(p)) continue
    try { source = await readFile(p, 'utf8') } catch { continue }
    break
  }

  if (source !== null) {
    // The config is executable code we cannot import safely, so extract alias
    // entries textually: a quoted `@…`/`~…` key, then the last quoted path in
    // the value expression — covers `'@': '/resources/js'` as well as
    // `'@': path.resolve(__dirname, 'resources/js')`. The value stops at a
    // top-level comma; call arguments are kept via the paren group.
    for (const m of source.matchAll(/['"`]([@~][\w./-]*)['"`]\s*:\s*((?:\([^)\n]*\)|[^,\n])*)/g)) {
      const key = m[1]
      if (alias[key] !== undefined) continue

      const paths = [...m[2].matchAll(/['"`]([^'"`]+)['"`]/g)].map((p) => p[1])
      const target = paths.length > 0 ? paths[paths.length - 1] : null
      if (!target || target.includes('*')) continue

      alias[key] = resolveAliasTarget(projectRoot, target)
    }
  }

  // laravel-vite-plugin registers '@' → resources/js by default.
  if (alias['@'] === undefined && (await usesLaravelVitePlugin(projectRoot))) {
    alias['@'] = resolve(projectRoot, 'resources/js')
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

// `existsSync()` ignores casing on APFS and NTFS, and on a macOS bind mount seen from
// inside a Linux container, so the `resources/js/Pages` candidate resolves in a project
// whose directory is really `resources/js/pages`. Every page path derives from the
// directory accepted below, so a wrong-cased one detaches the pages tree from the paths
// the PHP side reports. `readdir()` is case-exact everywhere.
export async function matchesDiskCasing(projectRoot, rel) {
  let current = projectRoot

  for (const segment of rel.split('/')) {
    let entries
    try { entries = await readdir(current) } catch { return false }
    if (!entries.includes(segment)) return false
    current = join(current, segment)
  }

  return true
}

async function discoverPagesDir() {
  const override = process.env.TIA_VITE_PAGES_DIR
  if (override && override.length > 0) {
    return resolve(PROJECT_ROOT, override.replace(/\\/g, '/'))
  }

  for (const rel of PAGE_DIR_CANDIDATES) {
    const abs = resolve(PROJECT_ROOT, rel)
    if (!existsSync(abs)) continue
    if (!(await matchesDiskCasing(PROJECT_ROOT, rel))) continue
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

  const loaded = await loadRolldown()

  if (loaded === null) {
    process.stdout.write('{}')
    return
  }

  const { rolldown } = loaded
  // The vite config is what the dev server actually resolves with — let it win.
  const alias = { ...(await loadAliasFromTsconfig()), ...(await loadAliasFromViteConfig()) }
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
      extensions: ['.tsx', '.ts', '.jsx', '.js', '.mts', '.cts', '.mjs', '.cjs', '.json', '.vue', '.svelte'],
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
    // A set computed while skipping an in-stack (cyclic) dependency is missing
    // that dependency's subtree and must not be memoized — the ancestor call
    // completes it for the current traversal, but a cached copy would leak the
    // incomplete set into other pages' traversals.
    let complete = true
    const deps = graph.get(id)
    if (deps) {
      for (const dep of deps) {
        if (!dep || dep.startsWith('\0')) continue
        if (dep.startsWith(PROJECT_ROOT)) {
          const rel = relative(PROJECT_ROOT, dep).split(sep).join('/')
          acc.add(rel)
        }
        if (stack.has(dep)) {
          complete = false
          continue
        }
        const child = computeTransitive(dep, stack)
        if (child) for (const r of child) acc.add(r)
        if (!transitiveCache.has(dep)) complete = false
      }
    }
    stack.delete(id)
    if (complete) transitiveCache.set(id, acc)
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

const invokedDirectly = process.argv[1] !== undefined
  && import.meta.url === pathToFileURL(process.argv[1]).href

if (invokedDirectly) {
  try {
    await main()
  } catch (err) {
    process.stderr.write(String(err?.stack ?? err ?? 'unknown error'))
    process.exit(1)
  }
}
