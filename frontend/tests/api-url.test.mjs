import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import vm from 'node:vm'
import ts from 'typescript'

const source = readFileSync(new URL('../src/lib/api-url.ts', import.meta.url), 'utf8')
const { outputText } = ts.transpileModule(source, {
  compilerOptions: {
    module: ts.ModuleKind.CommonJS,
    target: ts.ScriptTarget.ES2022,
  },
})

const module = { exports: {} }
vm.runInNewContext(outputText, { exports: module.exports, module })
const { resolveApiUrl } = module.exports

test('uses explicit Vite API URL when configured', () => {
  assert.equal(
    resolveApiUrl({
      configuredUrl: 'https://api.example.com/api/v1',
      origin: 'https://app.example.com',
      port: '',
    }),
    'https://api.example.com/api/v1',
  )
})

test('uses local Laravel server for Vite dev on port 3000', () => {
  assert.equal(
    resolveApiUrl({
      origin: 'http://127.0.0.1:3000',
      port: '3000',
    }),
    'http://127.0.0.1:8000/api/v1',
  )
})

test('uses same-origin API path for production default ports', () => {
  assert.equal(
    resolveApiUrl({
      origin: 'https://banwohub.com',
      port: '',
    }),
    'https://banwohub.com/api/v1',
  )
})

