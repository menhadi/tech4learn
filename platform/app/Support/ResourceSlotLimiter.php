<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class ResourceSlotLimiter
{
    /**
     * Run an expensive operation while holding one slot from a shared pool.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws LockTimeoutException
     */
    public function run(string $pool, int $slots, callable $callback, int $waitSeconds = 900, int $leaseSeconds = 1200): mixed
    {
        $slots = max(1, $slots);
        $deadline = microtime(true) + max(1, $waitSeconds);

        do {
            for ($slot = 1; $slot <= $slots; $slot++) {
                $lock = Cache::lock("resource-slot:{$pool}:{$slot}", $leaseSeconds);

                if (! $lock->get()) {
                    continue;
                }

                try {
                    return $callback();
                } finally {
                    $lock->release();
                }
            }

            usleep(500000);
        } while (microtime(true) < $deadline);

        throw new LockTimeoutException("Timed out waiting for an available {$pool} processing slot.");
    }
}