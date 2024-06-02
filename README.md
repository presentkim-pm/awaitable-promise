<!-- PROJECT BADGES -->
<div align="center">

[![Poggit CI][poggit-ci-badge]][poggit-ci-url]
[![Stars][stars-badge]][stars-url]
[![License][license-badge]][license-url]

</div>

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <img src="https://raw.githubusercontent.com/presentkim-pm/awaitable-promise/main/assets/icon.png" alt="Logo" width="80" height="80"/>
  <h3>awaitable-promise</h3>
  <p align="center">
    Provides a wrapper class for Promise with Generator exporting and register Throwable handlers!

[View in Poggit][poggit-ci-url] · [Report a bug][issues-url] · [Request a feature][issues-url]

  </p>
</div>


<!-- ABOUT THE PROJECT -->

## About The Project

:heavy_check_mark: Provides a wrapper class for Promise with Generator exporting and register Throwable handlers!

- `kim\present\awaitablepromise\AwaitablePromise`

-----

## Installation

See [Official Poggit Virion Documentation](https://github.com/poggit/support/blob/master/virion.md)

-----

## How to use?

### 1-1. Create `AwaitablePromise` from `Generator`

Use `AwaitablePromise::g2p(\Generator $generator) : AwaitablePromise`

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function example() : \Generator{
        return yield from Something::getAsync();
    }
    
    public function getSomething() : Promise{
        return Promise::g2p(example());
    }
}
```

<br/>

### 1-2. create `AwaitablePromise` from `callable` that returns `Generator`

Use `AwaitablePromise::f2p(callable $callable) : AwaitablePromise`

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function getSomething() : Promise{
        return Promise::f2p(function() : \Generator{
            return yield from Something::getAsync();
        });
    }
}
```

<br/>

### 1-3. create `AwaitablePromise` from result

Use `AwaitablePromise::r2p($result) : AwaitablePromise`  
It for synchronous operation or cached result that you want to wrap with AwaitablePromise.

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function getSomething() : Promise{
        return Promise::r2p(Something::getSync());
    }
}
```

<br/>

### 2-1. Register a resolve handler (handle result

Use `AwaitablePromise::then(callable $func) : AwaitablePromise`

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function getSomething() : void{
        Promise::r2p(Something::getSync())->then(function($result){
            echo "SUCCESS: " . $result . PHP_EOL;
        });
    }
}
```

<br/>

### 2-2. Register a reject handler (handle throwable)

Use `AwaitablePromise::catch(callable $func) : AwaitablePromise`

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function getSomething() : void{
        Promise::r2p(Something::getSync())->catch(function(\Throwable $throwable){
            echo "ERROR: " . $throwable->getMessage() . PHP_EOL;
        });
    }
}
```

<br/>

### 2-3. Register a finally handler (handle both result and throwable)

Use `AwaitablePromise::finally(callable $func) : AwaitablePromise`

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function getSomething() : void{
        Promise::r2p(Something::getSync())->finally(function(){
            echo "FINALLY" . PHP_EOL;
        });
    }
}
```

<br/>

### 3. Get real Promise object (`pocketmine\promise\Promise`)

Use `AwaitablePromise::getRealPromise() : \pocketmine\promise\Promise`

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function getSomething() : void{
        Promise::r2p(Something::getSync())->getRealPromise()->onCompletion(
            function($result){ echo "SUCCESS: " . $result . PHP_EOL; },
            function(\Throwable $throwable){ echo "ERROR: " . $throwable->getMessage() . PHP_EOL; }
        );
    }
}
```

<br/>

### 4. Using with [`await-generator`](https://github.com/SOF3/await-generator)

Use `AwaitablePromise::await() : \Generator`

```php
use kim\present\awaitablepromise\AwaitablePromise as Promise;

class Test {
    public function getSomething() : \Generator{
        $promise = Promise::r2p(Something::getSync());
        $result = yield from $promise->await();
        echo "SUCCESS: " . $result . PHP_EOL;
    }
}
```

-----

## License

Distributed under the **MIT**. See [LICENSE][license-url] for more information


[poggit-ci-badge]: https://poggit.pmmp.io/ci.shield/presentkim-pm/awaitable-promise/awaitable-promise?style=for-the-badge

[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/awaitable-promise.svg?style=for-the-badge

[license-badge]: https://img.shields.io/github/license/presentkim-pm/awaitable-promise.svg?style=for-the-badge

[poggit-ci-url]: https://poggit.pmmp.io/ci/presentkim-pm/awaitable-promise/awaitable-promise

[stars-url]: https://github.com/presentkim-pm/awaitable-promise/stargazers

[issues-url]: https://github.com/presentkim-pm/awaitable-promise/issues

[license-url]: https://github.com/presentkim-pm/awaitable-promise/blob/main/LICENSE

[project-icon]: https://raw.githubusercontent.com/presentkim-pm/awaitable-promise/main/assets/icon.png
