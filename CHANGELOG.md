# Changelog

## 2.0.0 (2026-11-27)

The Poggit virion version for the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP) 5.x is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/awaitable-promise/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Targets [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) and **PHP 8.4+**.
- Distributed with Composer: the Poggit virion files (`.poggit.yml`, `virion.yml`) are removed.
  [await-generator](https://github.com/SOF3/await-generator) is the Composer package `sof3/await-generator`
  (`^3.6.1`) instead of a bundled virion.
- A throwable that matches none of the registered `catch()` handlers is no longer rethrown (it surfaced as an `AwaitException`
  from whatever resumed the generator). It is logged with `GlobalLogger` (when the promise is destroyed, so late handlers still count), see the README.

### 🐛 Fixes
- `await()` hung forever on a generator that failed synchronously (the rejection happened before `await()` registered its handler),
  and its `catch($reject)` replaced any catch-all handler registered by the caller. It now uses its own listener.
- `catch()` handlers registered after the generator failed (always the case for a synchronous failure) were never called, and the
  error vanished. They are now called immediately.
- A `then()` handler that threw after the result was delivered made the wrapper reject the already resolved promise, ending in a
  `LogicException` ("Promise has already been resolved/rejected"). The promise now settles exactly once.
- `finally()` handlers were called without arguments on failure (`ArgumentCountError`). They now receive the throwable.

### 🧪 Tests
- PHPUnit tests (synchronous / asynchronous results and failures, late handlers, `await()`) and GitHub Actions CI on PHP 8.4 / 8.5.
