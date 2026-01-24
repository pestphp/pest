This pull request adds a `--agent` format that gets automatically used when Pest is executed within **Claude Code** or **OpenCode**.

**Why:** 

The default Pest output is designed for humans - colorful, with progress indicators and formatted summaries. AI agents need something different: structured JSON they can reliably parse, unambiguous status values like `"status":"pass"` instead of inferring from formatted text, minimal output that saves context window and reduces token cost, and deterministic results without ANSI colors or formatting noise.

**Token Usage Impact:**

Running Pest's unit test suite demonstrates the dramatic reduction in output size:

| Format | Token Usage | Reduction |
|--------|-------------|-----------|
| Default output | ~18,643 tokens | - |
| `--compact` output | ~152 tokens | 99.2% reduction |
| `--agent` output | ~152 tokens | 99.2% reduction |

This reduction is **critical** for AI agents because it dramatically lowers token costs (99.2% reduction), preserves context window space for code and instructions, enables faster parsing, and provides reliable structured data instead of ambiguous formatted text.

**How it works:**

1. When Claude Code runs Pest, it sets the `CLAUDECODE` environment variable
2. When OpenCode runs Pest, it sets the `OPENCODE` environment variable
3. Pest detects these and automatically switches to the agent format - no flags needed

Output:

```json
{"status":"pass"}
```

or when tests fail:

```json
{"status":"fail","failures":[{"test":"failing test","message":"Failed asserting that true is false.","location":"tests/Example.php:10","trace":"..."}]}
```

- `status` is `pass` or `fail`
- `failures` only shows up when there are test failures
- Additional fields like `coverage`, `memory`, and `shard` are included when available

Can also be used explicitly with `--agent`.
