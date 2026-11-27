<!-- PROJECT BADGES -->
<div align="center">

[![Packagist][packagist-badge]][packagist-url]
[![CI][ci-badge]][ci-url]
[![Stars][stars-badge]][stars-url]
[![License][license-badge]][license-url]

</div>


<!-- PROJECT LOGO -->
<br />
<div align="center">
  <img src="https://raw.githubusercontent.com/presentkim-pm/awaitable-promise/main/assets/icon.png" alt="Logo" width="80" height="80"/>
  <h3>awaitable-promise</h3>
  <p align="center">
    A <a href="https://github.com/SOF3/await-generator">await-generator</a> friendly wrapper for PocketMine's <code>Promise</code>: from generators, to generators, with typed error handlers.

[Packagist][packagist-url] · [Changelog](CHANGELOG.md) · [Report a bug][issues-url] · [Request a feature][issues-url]

  </p>
</div>

```php
$promise = AwaitablePromise::f2p(function() : \Generator{
    return yield from Something::getAsync();
});

$promise->then(fn($result) => print("SUCCESS: $result"))
        ->catch(fn(\Throwable $e) => print("ERROR: " . $e->getMessage()));

$result = yield from $promise->await(); // inside another generator
```

-----

## 🌟 Why awaitable-promise?

`pocketmine\promise\Promise` can neither carry the error nor be awaited. `AwaitablePromise` wraps a generator into one that
can do both.

- **Both directions** : `g2p()` / `f2p()` turn a generator into a promise, `await()` turns it back
- **Typed errors** : `catch($handler, SomeException::class)` keeps the throwable, which the real `Promise` drops
- **Late handlers work** : handlers registered after the promise settled (even a generator that failed synchronously) are called right away
- **Settles once** : a handler that throws can no longer reject an already resolved promise
- **Tested** : on PHP 8.4 / 8.5

-----

## 📦 Installation

```sh
composer require presentkim/awaitable-promise
```

This also installs [`sof3/await-generator`](https://packagist.org/packages/sof3/await-generator).

| Requirement | Version                                                           |
|-------------|-------------------------------------------------------------------|
| Server      | [axolotl-pm/PocketMine-MP][axolotl-url] (fork of PocketMine-MP 5) |
| PHP         | **8.4** or newer                                                  |

> The Poggit virion for the original PocketMine-MP 5.44 and older is kept on the
> [`legacy/pmmp-5.44`][legacy-url] branch (no longer maintained).

-----

## 🚀 Quick start

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

$promise = Promise::f2p(function() : \Generator{
    return yield from Something::getAsync();
});
```

<details>
<summary><b>Create : <code>g2p()</code> / <code>f2p()</code> / <code>r2p()</code></b></summary>

```php
Promise::g2p(example());                        // from a Generator
Promise::f2p(function() : \Generator{ ... });   // from a callable that returns a Generator
Promise::r2p(Something::getSync());             // from an already available result (cached or synchronous)
```

</details>

<details>
<summary><b>Handlers : <code>then()</code> / <code>catch()</code> / <code>finally()</code></b></summary>

```php
Promise::r2p(Something::getSync())
    ->then(function($result){ echo "SUCCESS: " . $result . PHP_EOL; })
    ->catch(function(\Throwable $throwable){ echo "ERROR: " . $throwable->getMessage() . PHP_EOL; })
    ->finally(function($resultOrThrowable){ echo "FINALLY" . PHP_EOL; });
```

`catch()` takes an optional class: only the first registered handler that matches the throwable is called.

```php
$promise->catch($onRuntime, \RuntimeException::class)->catch($onAnything);
```

</details>

<details>
<summary><b>Real promise : <code>getRealPromise()</code></b></summary>

```php
Promise::r2p(Something::getSync())->getRealPromise()->onCompletion(
    function($result){ echo "SUCCESS: " . $result . PHP_EOL; },
    function(){ echo "FAILED" . PHP_EOL; } // the real promise has no throwable
);
```

</details>

<details>
<summary><b>await-generator : <code>await()</code></b></summary>

```php
$promise = Promise::r2p(Something::getSync());
$result = yield from $promise->await(); // throws the throwable when the promise was rejected
```

</details>

-----

## 📖 API reference

| Method                                                       | Description                                                        |
|--------------------------------------------------------------|--------------------------------------------------------------------|
| `AwaitablePromise::g2p(Generator)`                           | Run the generator and wrap its result                              |
| `AwaitablePromise::f2p(callable)`                            | Same, for a callable returning a generator                         |
| `AwaitablePromise::r2p(mixed $result)`                       | An already resolved promise                                        |
| `then(Closure)`                                              | Called with the result                                             |
| `catch(Closure, string $class = "")`                         | Called with the throwable, first matching handler only             |
| `finally(Closure)`                                           | Called with the result or the throwable                            |
| `await() : Generator`                                        | Generator that returns the result or throws the throwable          |
| `getRealPromise() : pocketmine\promise\Promise`              | The wrapped promise (resolved, or rejected without a throwable)    |

A throwable that nothing handles (no matching `catch()` handler, no `finally()` and no `await()`) is logged with `GlobalLogger` when the `AwaitablePromise` is destroyed, so it never vanishes silently. Handlers may be registered late, which is why it is not logged earlier.

-----

## 🧪 Development

```sh
composer install --ignore-platform-req='ext-*'
composer test
```

-----

## 📄 License

Distributed under the **MIT** License. See [LICENSE][license-url] for more information.


[packagist-badge]: https://img.shields.io/packagist/v/presentkim/awaitable-promise.svg?style=for-the-badge

[packagist-url]: https://packagist.org/packages/presentkim/awaitable-promise

[ci-badge]: https://img.shields.io/github/actions/workflow/status/presentkim-pm/awaitable-promise/ci.yml?branch=main&style=for-the-badge

[ci-url]: https://github.com/presentkim-pm/awaitable-promise/actions/workflows/ci.yml

[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/awaitable-promise.svg?style=for-the-badge

[license-badge]: https://img.shields.io/github/license/presentkim-pm/awaitable-promise.svg?style=for-the-badge

[stars-url]: https://github.com/presentkim-pm/awaitable-promise/stargazers

[issues-url]: https://github.com/presentkim-pm/awaitable-promise/issues

[license-url]: https://github.com/presentkim-pm/awaitable-promise/blob/main/LICENSE

[legacy-url]: https://github.com/presentkim-pm/awaitable-promise/tree/legacy/pmmp-5.44

[axolotl-url]: https://github.com/axolotl-pm/PocketMine-MP
