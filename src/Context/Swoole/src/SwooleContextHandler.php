<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Context\Swoole;

use OpenTelemetry\Context\ExecutionContextAwareInterface;
use Swoole\Coroutine;

/**
 * @internal
 *
 * @phan-file-suppress PhanUndeclaredClassMethod
 * @psalm-suppress UndefinedClass
 */
final class SwooleContextHandler
{
    private ExecutionContextAwareInterface $storage;

    public function __construct(ExecutionContextAwareInterface $storage)
    {
        $this->storage = $storage;
    }

    public function switchToActiveCoroutine(): void
    {
        $cid = Coroutine::getCid();
        if ($cid !== -1 && !$this->isForked($cid)) {
            for ($pcid = $cid; ($pcid = Coroutine::getPcid($pcid)) !== -1 && Coroutine::exists($pcid) && !$this->isForked($pcid);) {
            }

            $this->storage->switch($pcid);
            $this->forkCoroutine($cid);
        }

        $this->storage->switch($cid);
    }

    public function splitOffChildCoroutines(): void
    {
        $pcid = Coroutine::getCid();
        foreach (method_exists(Coroutine::class, 'list') ? Coroutine::list() : Coroutine::listCoroutines() as $cid) {
            if ($pcid === Coroutine::getPcid($cid) && !$this->isForked($cid)) {
                $this->forkCoroutine($cid);
            }
        }
    }

    private function isForked(int $_cid): bool
    {
        return isset(Coroutine::getContext($_cid)[__CLASS__]);
    }

    private function forkCoroutine(int $cid): void
    {
        $this->storage->fork($cid);
        Coroutine::getContext($cid)[__CLASS__] = new SwooleContextDestructor($this->storage, $cid);
    }
}
