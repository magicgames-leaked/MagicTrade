<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade\task;

use pocketmine\scheduler\Task;
use alvin0319\PlayerTrade\PlayerTrade;

final class TradeCheckTask extends Task
{

	public function onRun(): void
	{
		PlayerTrade::getInstance()->checkRequests();
	}
}
