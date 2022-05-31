<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade;

use Closure;
use muqsit\invmenu\InvMenu;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use pocketmine\item\ItemFactory;
use pocketmine\scheduler\TaskHandler;
use alvin0319\PlayerTrade\event\TradeEndEvent;
use alvin0319\PlayerTrade\event\TradeStartEvent;
use muqsit\invmenu\transaction\InvMenuTransaction;
use alvin0319\PlayerTrade\task\TradeQueueCheckTask;
use muqsit\invmenu\transaction\InvMenuTransactionResult;

final class TradeQueue
{

	public const SENDER_SLOTS = [
		0, 1, 2, 3, 9, 10, 11, 12, 18, 19, 20, 21, 27, 28, 29, 30, 36, 37, 38, 39, 46, 47, 48
	];

	public const RECEIVER_SLOTS = [
		5, 6, 7, 8, 14, 15, 16, 17, 23, 24, 25, 26, 32, 33, 34, 35, 41, 42, 43, 44, 50, 51, 52
	];

	public const BORDER_SLOTS = [
		4, 13, 22, 31, 40, 49
	];

	public const SENDER_DONE_SLOT = 45;

	public const RECEIVER_DONE_SLOT = 53;

	/** @var Player */
	protected Player $sender, $receiver;
	/** @var InvMenu */
	protected InvMenu $senderMenu, $receiverMenu;
	/** @var bool */
	protected bool $isSenderDone = false, $isReceiverDone = false;
	/** @var bool */
	protected bool $isSenderConfirmed = false, $isReceiverConfirmed = false;
	/** @var bool */
	protected bool $done = false;
	/** @var TaskHandler|null */
	protected ?TaskHandler $handler = null;

	public function __construct(Player $sender, Player $receiver)
	{
		$this->sender = $sender;
		$this->receiver = $receiver;
		$this->senderMenu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$this->senderMenu->setName("You      |     {$receiver->getName()}");
		$this->senderMenu->setListener(Closure::fromCallable([$this, "handleInventoryTransaction"]));
		$this->receiverMenu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$this->receiverMenu->setName("{$sender->getName()}   |     You");
		$this->receiverMenu->setListener(Closure::fromCallable([$this, "handleInventoryTransaction"]));
		$this->senderMenu->setInventoryCloseListener(Closure::fromCallable([$this, "onInventoryClose"]));
		$this->receiverMenu->setInventoryCloseListener(Closure::fromCallable([$this, "onInventoryClose"]));

		$borderItem = ItemFactory::getInstance()->get(1081, 0, 1);
		$borderItem->setCustomName("§l ");

		$redItem = ItemFactory::getInstance()->get(ItemIds::TERRACOTTA, 14);
		$redItem->setCustomName("§r§c§lWAIT!");
		foreach (self::BORDER_SLOTS as $slot) {
			$this->senderMenu->getInventory()->setItem($slot, $borderItem);
			$this->receiverMenu->getInventory()->setItem($slot, $borderItem);
		}

		$this->senderMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $redItem);
		$this->senderMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $redItem);
		$this->receiverMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $redItem);
		$this->receiverMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $redItem);

		$this->startTrade();
	}

	public function startTrade(): void
	{
		if (!$this->sender->isOnline() || !$this->receiver->isOnline()) {
			return;
		}

		$ev = new TradeStartEvent($this->sender, $this->receiver);
		$ev->call();
		if ($ev->isCancelled()) {
			$this->removeFrom();
			return;
		}

		$this->senderMenu->send($this->sender);
		$this->receiverMenu->send($this->receiver);

		$this->handler = PlayerTrade::getInstance()->getScheduler()->scheduleRepeatingTask(new TradeQueueCheckTask($this), 20);
	}

	public function getSender(): Player
	{
		return $this->sender;
	}

	public function getReceiver(): Player
	{
		return $this->receiver;
	}

	public function done(): void
	{
		$this->done = true;
		$plugin = PlayerTrade::getInstance();
		$senderRemains = [];
		$receiverRemains = [];
		foreach (self::RECEIVER_SLOTS as $slot) {
			$item = $this->receiverMenu->getInventory()->getItem($slot);
			if (!$item->isNull()) {
				$senderRemains = array_merge($senderRemains, $this->sender->getInventory()->addItem($item));
			}
		}
		foreach (self::SENDER_SLOTS as $slot) {
			$item = $this->senderMenu->getInventory()->getItem($slot);
			if (!$item->isNull()) {
				$receiverRemains = array_merge($receiverRemains, $this->receiver->getInventory()->addItem($item));
			}
		}
		if (count($senderRemains) > 0) {
			$this->sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("trade.inventoryFull"));
			foreach ($senderRemains as $remain)
				$this->sender->dropItem($remain);
		}
		if (count($receiverRemains) > 0) {
			$this->receiver->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("trade.inventoryFull"));
			foreach ($receiverRemains as $remain)
				$this->receiver->dropItem($remain);
		}
		$this->senderMenu->onClose($this->sender);
		$this->receiverMenu->onClose($this->receiver);
		$this->removeFrom();
		$this->sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("trade.success"));
		$this->receiver->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("trade.success"));
		(new TradeEndEvent($this->sender, $this->receiver, TradeEndEvent::REASON_SUCCESS))->call();
	}

	public function cancel(bool $offline = true, bool $causedBySender = false): void
	{
		$this->done = true;
		$plugin = PlayerTrade::getInstance();
		foreach (self::SENDER_SLOTS as $slot) {
			$item = $this->senderMenu->getInventory()->getItem($slot);
			if (!$item->isNull()) {
				$this->sender->getInventory()->addItem($item);
			}
		}
		foreach (self::RECEIVER_SLOTS as $slot) {
			$item = $this->receiverMenu->getInventory()->getItem($slot);
			if (!$item->isNull()) {
				$this->receiver->getInventory()->addItem($item);
			}
		}
		if ($offline) {
			if ($causedBySender) {
				$this->receiverMenu->onClose($this->receiver);
				$this->receiver->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("trade.cancel.senderLeft"));
			} else {
				$this->senderMenu->onClose($this->sender);
				$this->sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("trade.cancel.receiverLeft"));
			}
		} else {
			$reason = $causedBySender ? "sender" : "receiver";
			$message = $plugin->getLanguage()->translateString("trade.cancel", [
				$reason
			]);

			if ($this->sender->isConnected()) {
				$this->senderMenu->onClose($this->sender);
				$this->sender->sendMessage(PlayerTrade::$prefix . $message);
			}
			if ($this->receiver->isConnected()) {
				$this->receiverMenu->onClose($this->receiver);
				$this->receiver->sendMessage(PlayerTrade::$prefix . $message);
			}
		}
		(new TradeEndEvent($this->sender, $this->receiver, $causedBySender ? ($offline ? TradeEndEvent::REASON_SENDER_QUIT : TradeEndEvent::REASON_RECEIVER_QUIT) : TradeEndEvent::REASON_RECEIVER_CANCEL));
		$this->removeFrom();
	}

	public function removeFrom(): void
	{
		PlayerTrade::getInstance()->removeFromQueue($this);
		if ($this->handler !== null) {
			$this->handler->cancel();
		}
	}

	public function handleInventoryTransaction(InvMenuTransaction $action): InvMenuTransactionResult
	{
		$discard = $action->discard()->then(fn (Player $player) => $this->syncWith());
		$continue = $action->continue()->then(fn (Player $player) => $this->syncWith());
		$player = $action->getPlayer();
		$slot = $action->getAction()->getSlot();
		if ($this->done) return $discard;
		if ($this->isSender($player)) {
			if ($slot === self::SENDER_DONE_SLOT) {
				if ($this->isSenderDone) {
					if (!$this->isSenderConfirmed && $this->isReceiverDone) {
						$this->isSenderConfirmed = true;
					}
					return $discard;
				}
				$this->isSenderDone = true;
				return $discard;
			}
			if ($this->isSenderDone || $this->isSenderConfirmed) {
				return $discard;
			}
			
			if (!in_array($slot, array_merge(self::SENDER_SLOTS, [self::SENDER_DONE_SLOT]))) {
				return $discard;
			}
			
		} else {
			if ($slot === self::RECEIVER_DONE_SLOT) {
				if ($this->isReceiverDone) {
					if (!$this->isReceiverConfirmed && $this->isSenderDone) {
						$this->isReceiverConfirmed = true;
					}
					return $discard;
				}
				$this->isReceiverDone = true;
				return $discard;
			}
			if ($this->isReceiverDone || $this->isReceiverConfirmed) {
				return $discard;
			}
			
			if (!in_array($slot, array_merge(self::RECEIVER_SLOTS, [self::RECEIVER_DONE_SLOT]))) {
				return $discard;
			}
			
		}
		return $continue;
	}

	public function onInventoryClose(Player $player): void
	{
		if (!$this->done) {
			$this->cancel(false, $this->isSender($player));
		}
	}

	public function isReceiver(Player $player): bool
	{
		return $this->receiver->getName() === $player->getName();
	}

	public function isSender(Player $player): bool
	{
		return $this->sender->getName() === $player->getName();
	}

	public function isDone(): bool
	{
		return $this->done;
	}

	public function syncWith(): void
	{
		$yellowItem = ItemFactory::getInstance()->get(ItemIds::TERRACOTTA, 4);
		$yellowItem->setCustomName("§r§l§aREADY!");

		$greenItem = ItemFactory::getInstance()->get(ItemIds::TERRACOTTA, 13);
		$greenItem->setCustomName("§r§l§aCONFIRMED!");
		foreach (self::SENDER_SLOTS as $slot) {
			$senderItem = $this->senderMenu->getInventory()->getItem($slot);
			$receiverItem = $this->receiverMenu->getInventory()->getItem($slot);
			if (!$senderItem->equalsExact($receiverItem)) {
				$this->receiverMenu->getInventory()->setItem($slot, $senderItem);
			}
		}
		foreach (self::RECEIVER_SLOTS as $slot) {
			$senderItem = $this->senderMenu->getInventory()->getItem($slot);
			$receiverItem = $this->receiverMenu->getInventory()->getItem($slot);
			if (!$receiverItem->equalsExact($senderItem)) {
				$this->senderMenu->getInventory()->setItem($slot, $receiverItem);
			}
		}

		if ($this->isSenderDone) {
			$this->senderMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $yellowItem);
			$this->receiverMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $yellowItem);
		}
		if ($this->isReceiverDone) {
			$this->senderMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $yellowItem);
			$this->receiverMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $yellowItem);
		}

		if ($this->isSenderConfirmed) {
			$this->senderMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $greenItem);
			$this->receiverMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $greenItem);
		}
		if ($this->isReceiverConfirmed) {
			$this->senderMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $greenItem);
			$this->receiverMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $greenItem);
		}

		if (!$this->done && $this->isSenderConfirmed && $this->isReceiverConfirmed) {
			$this->done();
		}
	}
}
