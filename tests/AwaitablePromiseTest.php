<?php

declare(strict_types=1);

namespace kim\present\awaitablepromise\tests;

use Generator;
use kim\present\awaitablepromise\AwaitablePromise;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use pocketmine\promise\PromiseResolver;
use SOFe\AwaitGenerator\Await;

#[CoversClass(AwaitablePromise::class)]
final class AwaitablePromiseTest extends TestCase{

    private \Logger $previousLogger;

    protected function setUp() : void{
        // Unhandled throwables are logged on destruction, keep the test output clean
        $this->previousLogger = \GlobalLogger::get();
        \GlobalLogger::set($this->createStub(\Logger::class));
    }

    protected function tearDown() : void{
        \GlobalLogger::set($this->previousLogger);
    }

    /** A generator that stays suspended until the returned handle is resolved or rejected. */
    private function pending() : object{
        return new class{
            public Generator $generator;
            private ?\Closure $resolve = null;
            private ?\Closure $reject = null;

            public function __construct(){
                $this->generator = (function() : Generator{
                    return yield from Await::promise(function(\Closure $res, \Closure $rej) : void{
                        $this->resolve = $res;
                        $this->reject = $rej;
                    });
                })->call($this);
            }

            public function resolve(mixed $value) : void{
                ($this->resolve)($value);
            }

            public function reject(\Throwable $throwable) : void{
                ($this->reject)($throwable);
            }
        };
    }

    private function failing(\Throwable $e) : Generator{
        yield from [];
        throw $e;
    }

    public function testR2pThenFires() : void{
        $got = [];
        AwaitablePromise::r2p(5)->then(function($v) use (&$got) : void{
            $got[] = $v;
        });
        self::assertSame([5], $got);
    }

    public function testG2pSynchronousResult() : void{
        $got = null;
        AwaitablePromise::g2p((function() : Generator{
            yield from [];
            return "x";
        })())->then(function($v) use (&$got) : void{
            $got = $v;
        });
        self::assertSame("x", $got);
    }

    public function testF2pCallsCallable() : void{
        $got = null;
        AwaitablePromise::f2p(function() : Generator{
            yield from [];
            return 7;
        })->then(function($v) use (&$got) : void{
            $got = $v;
        });
        self::assertSame(7, $got);
    }

    public function testAsyncResolveCallsHandlersRegisteredEarly() : void{
        $pending = $this->pending();
        $got = [];
        $promise = AwaitablePromise::g2p($pending->generator);
        $promise->then(function($v) use (&$got) : void{
            $got[] = "then $v";
        })->finally(function($v) use (&$got) : void{
            $got[] = "finally $v";
        });
        self::assertSame([], $got);
        $pending->resolve(3);
        self::assertSame(["then 3", "finally 3"], $got);
        self::assertTrue($promise->getRealPromise()->isResolved());
    }

    public function testSynchronousFailureReachesCatchRegisteredAfterwards() : void{
        $error = new \RuntimeException("sync");
        $caught = null;
        AwaitablePromise::g2p($this->failing($error))->catch(function(\Throwable $t) use (&$caught) : void{
            $caught = $t;
        });
        self::assertSame($error, $caught);
    }

    public function testAsyncFailureReachesCatch() : void{
        $pending = $this->pending();
        $error = new \LogicException("late");
        $caught = null;
        AwaitablePromise::g2p($pending->generator)->catch(function(\Throwable $t) use (&$caught) : void{
            $caught = $t;
        });
        self::assertNull($caught);
        $pending->reject($error);
        self::assertSame($error, $caught);
    }

    public function testCatchSelectsByClassAndOnlyFirstMatchRuns() : void{
        $calls = [];
        AwaitablePromise::g2p($this->failing(new \LogicException("l")))
            ->catch(function() use (&$calls) : void{
                $calls[] = "runtime";
            }, \RuntimeException::class)
            ->catch(function() use (&$calls) : void{
                $calls[] = "logic";
            }, \LogicException::class)
            ->catch(function() use (&$calls) : void{
                $calls[] = "all";
            });
        self::assertSame(["logic"], $calls);
    }

    /** @return list<\Throwable> the throwables logged while $body ran */
    private function captureLogged(\Closure $body) : array{
        $logged = [];
        $logger = $this->createStub(\Logger::class);
        $logger->method('logException')->willReturnCallback(function(\Throwable $e) use (&$logged) : void{
            $logged[] = $e;
        });
        $previous = \GlobalLogger::get();
        \GlobalLogger::set($logger);
        try{
            $body();
        }finally{
            \GlobalLogger::set($previous);
        }
        return $logged;
    }

    public function testUnmatchedThrowableIsLoggedNotThrown() : void{
        $error = new \LogicException("l");
        $calls = 0;
        $logged = $this->captureLogged(function() use ($error, &$calls) : void{
            $promise = AwaitablePromise::g2p($this->failing($error))
                ->catch(function() use (&$calls) : void{
                    ++$calls;
                }, \RuntimeException::class);
            unset($promise);
        });
        self::assertSame(0, $calls);
        self::assertSame([$error], $logged);
    }

    public function testThrowableWithoutAnyHandlerIsLogged() : void{
        $error = new \LogicException("l");
        $logged = $this->captureLogged(function() use ($error) : void{
            AwaitablePromise::g2p($this->failing($error));
        });
        self::assertSame([$error], $logged);
    }

    public function testHandledThrowableIsNotLogged() : void{
        $logged = $this->captureLogged(function() : void{
            AwaitablePromise::g2p($this->failing(new \LogicException("a")))->catch(static function() : void{});
            AwaitablePromise::g2p($this->failing(new \LogicException("b")))->finally(static function() : void{});
        });
        self::assertSame([], $logged);
    }

    public function testFailureWithoutCatchIsSilentAndRejectsRealPromise() : void{
        $failed = false;
        AwaitablePromise::g2p($this->failing(new \RuntimeException("x")))
            ->getRealPromise()->onCompletion(static fn() => null, function() use (&$failed) : void{
                $failed = true;
            });
        self::assertTrue($failed);
    }

    public function testFinallyReceivesThrowableOnFailure() : void{
        $error = new \RuntimeException("x");
        $got = null;
        AwaitablePromise::g2p($this->failing($error))->finally(function($v) use (&$got) : void{
            $got = $v;
        });
        self::assertSame($error, $got);
    }

    public function testThenIsNotCalledOnFailure() : void{
        $called = false;
        AwaitablePromise::g2p($this->failing(new \RuntimeException("x")))->then(function() use (&$called) : void{
            $called = true;
        });
        self::assertFalse($called);
    }

    public function testAwaitReturnsResultOfSettledPromise() : void{
        $got = null;
        Await::f2c(function() use (&$got) : Generator{
            $got = yield from AwaitablePromise::r2p("done")->await();
        });
        self::assertSame("done", $got);
    }

    public function testAwaitWaitsForPendingPromise() : void{
        $pending = $this->pending();
        $promise = AwaitablePromise::g2p($pending->generator);
        $got = null;
        Await::f2c(function() use ($promise, &$got) : Generator{
            $got = yield from $promise->await();
        });
        self::assertNull($got);
        $pending->resolve("later");
        self::assertSame("later", $got);
    }

    public function testAwaitThrowsOnSynchronousFailure() : void{
        $error = new \RuntimeException("sync");
        $caught = null;
        $promise = AwaitablePromise::g2p($this->failing($error));
        Await::f2c(function() use ($promise, &$caught) : Generator{
            try{
                yield from $promise->await();
            }catch(\Throwable $t){
                $caught = $t;
            }
        });
        self::assertSame($error, $caught);
    }

    public function testAwaitThrowsOnAsyncFailureAndDoesNotReplaceCatchHandlers() : void{
        $pending = $this->pending();
        $promise = AwaitablePromise::g2p($pending->generator);
        $error = new \RuntimeException("async");
        $viaCatch = $viaAwait = null;
        $promise->catch(function(\Throwable $t) use (&$viaCatch) : void{
            $viaCatch = $t;
        });
        Await::f2c(function() use ($promise, &$viaAwait) : Generator{
            try{
                yield from $promise->await();
            }catch(\Throwable $t){
                $viaAwait = $t;
            }
        });
        $pending->reject($error);
        self::assertSame($error, $viaCatch);
        self::assertSame($error, $viaAwait);
    }

    public function testMultipleAwaitersAreAllResumed() : void{
        $pending = $this->pending();
        $promise = AwaitablePromise::g2p($pending->generator);
        $got = [];
        for($i = 0; $i < 3; ++$i){
            Await::f2c(function() use ($promise, $i, &$got) : Generator{
                $got[$i] = yield from $promise->await();
            });
        }
        $pending->resolve(1);
        self::assertSame([1, 1, 1], $got);
    }

    public function testThrowingThenHandlerDoesNotRejectResolvedPromise() : void{
        $pending = $this->pending();
        $promise = AwaitablePromise::g2p($pending->generator);
        $caught = 0;
        $promise->catch(function() use (&$caught) : void{
            ++$caught;
        });
        $promise->then(static function() : void{
            throw new \DomainException("handler failed");
        });
        try{
            $pending->resolve(1);
            self::fail("The handler exception should propagate");
        }catch(\Throwable){
            // propagated to the resumer, as with any callback
        }
        self::assertSame(0, $caught, "a resolved promise must not be rejected afterwards");
        self::assertTrue($promise->getRealPromise()->isResolved());
    }

    public function testLateHandlersRunImmediately() : void{
        $promise = AwaitablePromise::r2p(9);
        $log = [];
        $promise->finally(function($v) use (&$log) : void{
            $log[] = $v;
        });
        $promise->then(function($v) use (&$log) : void{
            $log[] = $v + 1;
        });
        self::assertSame([9, 10], $log);
    }

    public function testGeneratorReturningNullResolves() : void{
        $called = false;
        AwaitablePromise::g2p((function() : Generator{
            yield from [];
        })())->then(function($v) use (&$called) : void{
            $called = true;
            self::assertNull($v);
        });
        self::assertTrue($called);
    }
}
