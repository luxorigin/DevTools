<?php

declare(strict_types=1);

namespace DevTools\commands;

use DevTools\DevTools;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\permission\Permissible;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class CheckPermissionCommand extends Command
{
	public function __construct(protected DevTools $plugin)
	{
		parent::__construct(strtolower("checkperm"), "Checks a permission value for the current sender, or a player");
		$this->setPermission("devtools.command.checkperm;devtools.command.checkperm.other");
		$this->setUsage("/{$this->getName()} <node> [playerName]");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		$target = $sender;

		if (!isset($args[0])) return;

		$node = strtolower($args[0]);

		if (isset($args[1])) {
			if (($player = $this->plugin->getServer()->getPlayerExact($args[1])) instanceof Player) {
				$target = $player;
			} else {
				return;
			}
		}

		if ($target !== $sender && !$sender->hasPermission("devtools.command.checkperm.other")) {
			$sender->sendMessage(TextFormat::RED . "You do not have permissions to check other players.");
			return;
		}

		$sender->sendMessage(TextFormat::GREEN . "---- " . TextFormat::WHITE . "Permission node " . $node . TextFormat::GREEN . " ----");
		$perm = PermissionManager::getInstance()->getPermission($node);
		if ($perm instanceof Permission) {
			$description = $perm->getDescription();
			$rawDescription = $description instanceof Translatable ? $sender->getLanguage()->translate($description) : $description;
			$message = TextFormat::GOLD . "Description: " . TextFormat::WHITE . $rawDescription . "\n";
			$children = [];
			foreach ($perm->getChildren() as $name => $isGranted) {
				$children[] = ($isGranted ? TextFormat::GREEN : TextFormat::RED) . $name . TextFormat::WHITE;
			}
			$message .= TextFormat::GOLD . "Children: " . TextFormat::WHITE . implode(", ", $children) . "\n";
		} else {
			$message = TextFormat::RED . "Permission does not exist\n";
		}
		$sender->sendMessage($message);
		$coloredName = TextFormat::YELLOW . $target->getName() . TextFormat::RESET;
		$sender->sendMessage(TextFormat::GOLD . "Permission info for $coloredName:");
		foreach ($this->describePermissionSet($target, $node) as $line) {
			$sender->sendMessage("- " . $line);
		}
	}

	private function describePermissionSet(Permissible $sender, string $permission): array
	{
		$info = $sender->getEffectivePermissions()[$permission] ?? null;
		$result = [];

		if ($info === null) {
			return [TextFormat::RED . $permission . TextFormat::WHITE . " is not set (default " . TextFormat::RED . "false" . TextFormat::WHITE . ")"];
		}

		while ($info !== null) {
			$result[] = $this->plugin->describePermission($info);
			$info = $info->getGroupPermissionInfo();
		}
		return $result;
	}
}