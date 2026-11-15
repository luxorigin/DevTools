<?php

declare(strict_types=1);

namespace DevTools\commands;

use DevTools\DevTools;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class ListPermissionCommand extends Command
{
	public function __construct(protected DevTools $plugin)
	{
		parent::__construct(strtolower("listperm"), "Lists all the permission nodes set on the executor, or a player");
		$this->setPermission("devtools.command.listperms.self;devtools.command.listperms.other");
		$this->setUsage("/{$this->getName()} [playerName]");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		$target = $sender;

		if (isset($args[0])) {
			if (($player = $this->plugin->getServer()->getPlayerExact($args[0])) instanceof Player) {
				$target = $player;
			} else {
				return;
			}
		}

		if ($target !== $sender and !$sender->hasPermission("devtools.command.listperms.other")) {
			$sender->sendMessage(TextFormat::RED . "You do not have permissions to check other players.");
			return;
		}
		$sender->sendMessage(TextFormat::GOLD . "--- Permissions assigned to " . TextFormat::YELLOW . $target->getName() . TextFormat::GOLD . " ---");
		foreach ($target->getEffectivePermissions() as $permissionAttachmentInfo) {
			$sender->sendMessage("- " . $this->plugin->describePermission($permissionAttachmentInfo));
		}
	}
}