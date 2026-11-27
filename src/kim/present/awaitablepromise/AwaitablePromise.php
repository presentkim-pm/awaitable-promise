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
use GlobalLogger;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use SOFe\AwaitGenerator\Await;
use Throwable;

/**
 * Wrapper class for {@see Promise} with {@see Generator} exporting and register {@see Throwable} handlers
 *
 * Handlers may be registered before or after the promise settles, a handler registered late is called immediately.
 *
 * @phpstan-template TValue
 */
final class AwaitablePromise{

    /**
     * Throwable handlers
     *
     * @var Closure[]
     * @phpstan-var array<class-string<Throwable>|"", Closure(Throwable): void>
     */
    private array $catches = [];

    /** null while pending, true when resolved, false when rejected */
    private ?bool $state = null;

    /** @phpstan-var TValue|null */
    private mixed $value = null;

    /** Throwable thrown by the generator, set when rejected */
    private Throwable|null $lastError = null;

    /** Whether a {@see catch()} handler, {@see finally()} or {@see await()} took the throwable */
    private bool $errorHandled = false;

    /**
     * Listeners of {@see then()}, {@see finally()} and {@see await()}, called in registration order
     *
     * @var array<int, array{Closure, Closure, bool}> [onSuccess, onFailure, whether the failure counts as handled]
     * @phpstan-var array<int, array{Closure(TValue): void, Closure(Throwable): void, bool}>
     */
    private array $listeners = [];

    /**
     * @phpstan-param Promise<TValue> $promise
     * @phpstan-param PromiseResolver<TValue> $resolver
     */
    private function __construct(private readonly Promise $promise, private readonly PromiseResolver $resolver){}

    public function __destruct(){
        if($this->state === false && !$this->errorHandled && $this->lastError !== null){
            GlobalLogger::get()->logException($this->lastError);
        }
    }

    /**
     * Get real Promise object for get result.
     * Use this method for callback chaining
     *
     * The real promise carries no throwable, it is only rejected. Use {@see catch()} or {@see await()} to get it.
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
     * The generator throws the throwable if the promise is rejected.
     *
     * @return Generator<TValue>
     */
    public function await() : Generator{
        return yield from Await::promise(fn(Closure $resolve, Closure $reject) => $this->listen($resolve, $reject));
    }

    /**
     * Register a resolve handler
     *
     * @phpstan-param Closure(TValue) : void $resultHandler
     */
    public function then(Closure $resultHandler) : self{
        $this->listen($resultHandler, static function(Throwable $throwable) : void{}, false);
        return $this;
    }

    /**
     * Register a reject handler
     *
     * Only the first registered handler matching the throwable is called.
     * A throwable that nothing handled (no matching handler, and no {@see finally()} or {@see await()}) is logged
     * with {@see GlobalLogger} when this object is destroyed, so handlers may still be registered late.
     *
     * @phpstan-param Closure(Throwable) : void  $throwableHandler
     * @phpstan-param class-string<Throwable>|"" $throwableClass The class name of the throwable to catch,
     *                                                           or an empty string to catch all throwables
     */
    public function catch(Closure $throwableHandler, string $throwableClass = "") : self{
        $this->catches[$throwableClass] = $throwableHandler;
        if($this->state === false && !$this->errorHandled && $this->lastError !== null){
            if($throwableClass === "" || is_a($this->lastError, $throwableClass)){
                $this->errorHandled = true;
                $throwableHandler($this->lastError);
            }
        }
        return $this;
    }

    /**
     * Register a finally handler (handle both result and throwable)
     *
     * @phpstan-param Closure(TValue|Throwable) : void $handler
     */
    public function finally(Closure $handler) : self{
        $this->listen($handler, $handler);
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
        $self = new self($resolver->getPromise(), $resolver);
        $self->settle($result);
        return $self;
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
        $self = new self($resolver->getPromise(), $resolver);

        Await::f2c(static function() use ($generator, $self) : Generator{
            try{
                $value = yield from $generator;
            }catch(Throwable $throwable){
                $self->settle(null, $throwable);
                return;
            }
            // Outside the try block: an exception thrown by a handler must not turn the promise into a rejected one
            $self->settle($value);
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

    /**
     * Call the listener now if the promise is settled, otherwise when it settles
     *
     * @phpstan-param Closure(TValue) : void $onSuccess
     * @phpstan-param Closure(Throwable) : void $onFailure
     */
    private function listen(Closure $onSuccess, Closure $onFailure, bool $handlesFailure = true) : void{
        if($this->state === true){
            $onSuccess($this->value);
        }elseif($this->state === false){
            $this->errorHandled = $this->errorHandled || $handlesFailure;
            $onFailure($this->lastError);
        }else{
            $this->listeners[] = [$onSuccess, $onFailure, $handlesFailure];
        }
    }

    /** @phpstan-param TValue $value */
    private function settle(mixed $value, ?Throwable $throwable = null) : void{
        if($this->state !== null){
            return; // already settled, never resolve or reject the real promise twice
        }

        $listeners = $this->listeners;
        $this->listeners = [];
        if($throwable === null){
            $this->state = true;
            $this->value = $value;
            $this->resolver->resolve($value);
            foreach($listeners as [$onSuccess]){
                $onSuccess($value);
            }
            return;
        }

        $this->state = false;
        $this->lastError = $throwable;
        $this->resolver->reject();
        foreach($this->catches as $class => $onError){
            if($class === "" || is_a($throwable, $class)){
                $this->errorHandled = true;
                $onError($throwable);
                break;
            }
        }
        foreach($listeners as [, $onFailure, $handlesFailure]){
            $this->errorHandled = $this->errorHandled || $handlesFailure;
            $onFailure($throwable);
        }
    }
}
