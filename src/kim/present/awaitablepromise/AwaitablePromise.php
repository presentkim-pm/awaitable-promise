<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\awaitablepromise;

use Closure;
use Generator;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use SOFe\AwaitGenerator\Await;
use Throwable;

/**
 * Wrapper class for {@see Promise} with {@see Generator} exporting and register {@see Throwable} handlers
 *
 * @phpstan-template TValue
 */
final class AwaitablePromise{

    /**
     * Throwable handlers
     *
     * @var callable[]
     * @phpstan-var array<class-string<Throwable>|"", callable(Throwable): void>
     */
    private array $catches = [];

    /**
     * Last error thrown by the generator
     */
    private Throwable|null $lastError = null;

    /**
     * @internal Use static factory methods to create an instance
     *
     * @phpstan-param Promise<TValue> $promise
     */
    private function __construct(private readonly Promise $promise){}

    /**
     * Get real Promise object for get result.
     * Use this method for callback chaining
     *
     * @return Promise<TValue>
     */
    public function getRealPromise() : Promise{
        return $this->promise;
    }

    /**
     * Get generator that returned the result.
     * Use this method for await-generator
     *
     * @return Generator<TValue>
     */
    public function await() : Generator{
        return yield from Await::promise(fn($resolve, $reject) => $this->then($resolve)->catch($reject));
    }

    /**
     * Register a resolve handler
     *
     * @phpstan-param Closure(TValue) : void $resultHandler
     */
    public function then(Closure $resultHandler) : self{
        $this->promise->onCompletion($resultHandler, static fn() => null);
        return $this;
    }

    /**
     * Register a reject handler
     *
     * @phpstan-param Closure(Throwable) : void  $throwableHandler
     * @phpstan-param class-string<Throwable>|"" $throwableClass The class name of the throwable to catch,
     *                                                           or an empty string to catch all throwables
     */
    public function catch(Closure $throwableHandler, string $throwableClass = "") : self{
        $this->catches[$throwableClass] = $throwableHandler;
        return $this;
    }

    /**
     * Register a finally handler (handle both result and throwable)
     *
     * @phpstan-param Closure(TValue|Throwable) : void $handler
     */
    public function finally(Closure $handler) : self{
        $this->promise->onCompletion($handler, $handler);
        return $this;
    }

    /**
     * Converts a result to a promise
     * Use this method when the result is already available (e.g. synchronous operation or cached result)
     *
     * @phpstan-param TValue $result
     */
    public static function r2p(mixed $result) : self{
        $resolver = new PromiseResolver();
        $resolver->resolve($result);
        return new self($resolver->getPromise());
    }

    /**
     * Converts a generator to a promise
     * Use this method when the result is not available yet (e.g. asynchronous operation)
     *
     * @phpstan-param Generator<TValue> $generator
     */
    public static function g2p(Generator $generator) : self{
        /** @phpstan-var PromiseResolver<TValue> $resolver */
        $resolver = new PromiseResolver();
        $self = new self($resolver->getPromise());
        $self->promise->onCompletion(static fn() => null, function() use ($self) : void{
            if(empty($self->lastError) || empty($self->catches)){ // Pass no error handler
                return;
            }

            foreach($self->catches as $class => $onError){
                if($class === "" || is_a($self->lastError, $class)){
                    $onError($self->lastError);
                    return;
                }
            }

            // Throw unhandled error
            throw $self->lastError;
        });

        Await::f2c(static function() use ($resolver, $generator, $self) : Generator{
            try{
                $value = yield from $generator;
                $resolver->resolve($value);
            }catch(Throwable $throwable){
                $self->lastError = $throwable;
                $resolver->reject();
            }
        });
        return $self;
    }

    /**
     * Converts a generator function to a promise
     * Use this method when the result is not available yet (e.g. asynchronous operation)
     *
     * @phpstan-param callable() : Generator<TValue> $callable
     */
    public static function f2p(callable $callable) : self{
        return self::g2p($callable());
    }
}
