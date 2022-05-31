<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade\event;

use pocketmine\event\Cancellable;

final class TradeStartEvent extends TradeEvent implements Cancellable
{

    /** @var bool */
    private bool $cancelled = false;

    public function cancel(bool $value = true): void
    {
        $this->cancelled = $value;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
