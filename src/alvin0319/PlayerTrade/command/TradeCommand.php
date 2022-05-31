<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade\command;

use function count;
use function array_shift;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use alvin0319\PlayerTrade\PlayerTrade;
use pocketmine\command\utils\InvalidCommandSyntaxException;

final class TradeCommand extends Command
{

	public function __construct()
	{
		parent::__construct("trade", "Trade with other player!", "/trade <accept|request|deny> <player>");
		$this->setPermission("playertrade.command");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): bool
	{
		if (!$this->testPermission($sender)) {
			return false;
		}
		$plugin = PlayerTrade::getInstance();
		if (!$sender instanceof Player) {
			$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.ingameOnly"));
			return false;
		}
		if (count($args) < 2) {
			throw new InvalidCommandSyntaxException();
		}
		switch (array_shift($args)) {
			case "request":
				if (PlayerTrade::getInstance()->hasRequest($sender)) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.alreadyHaveRequest"));
					return false;
				}
				/** @phpstan-ignore-next-line */
				$player = $sender->getServer()->getPlayerByPrefix(array_shift($args));
				if ($player === null) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.offlinePlayer"));
					return false;
				}
				if ($player->getName() === $sender->getName()) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.noSelf", [
						"request trade"
					]));
					return false;
				}
				PlayerTrade::getInstance()->addRequest($sender, $player);
				$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.requestSuccess", [
					$player->getName()
				]));
				$player->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.receiveRequest1", [
					$sender->getName()
				]));
				$player->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.receiveRequest2", [
					$sender->getName()
				]));
				break;
			case "accept":
				/** @phpstan-ignore-next-line */
				$player = $sender->getServer()->getPlayerByPrefix(array_shift($args));
				if ($player === null) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.offlinePlayer"));
					return false;
				}
				if ($sender->getName() === $player->getName()) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.noSelf", [
						"accept request"
					]));
					return false;
				}
				if (!PlayerTrade::getInstance()->hasRequestFrom($sender, $player)) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.noAnyRequest", [
						$player->getName()
					]));
					return false;
				}
				PlayerTrade::getInstance()->acceptRequest($sender);
				break;
			case "deny":
				/** @phpstan-ignore-next-line */
				$player = $sender->getServer()->getPlayerByPrefix(array_shift($args));
				if ($player === null) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.offlinePlayer"));
					return false;
				}
				if ($sender->getName() === $player->getName()) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.noSelf", [
						"deny request"
					]));
					return false;
				}
				if (!PlayerTrade::getInstance()->hasRequestFrom($sender, $player)) {
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.noAnyRequest", [
						$player->getName()
					]));
					return false;
				}
				PlayerTrade::getInstance()->denyRequest($sender);
				$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.requestDeny", [
					$player->getName()
				]));
				$player->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.requestDeny.sender"));
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}
		return true;
	}
}
