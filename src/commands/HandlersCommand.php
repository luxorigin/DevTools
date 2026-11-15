<?php

declare(strict_types=1);

namespace DevTools\commands;

use DevTools\DevTools;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

final class HandlersCommand extends Command
{
	public function __construct(protected DevTools $plugin)
	{
		parent::__construct(strtolower("handlers"), "List all event handlers currently associated with an event");
		$this->setPermission("devtools.command.handlers");
		$this->setUsage("/{$this->getName()} <event>");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if (count($args) > 1) {
			return;
		}
		$all = HandlerListManager::global()->getAll();
		ksort($all, SORT_STRING);
		$found = false;

		foreach ($all as $className => $handlerList) {
			if (count($args) === 0 || str_contains($className, $args[0])) {
				$found = true;

				foreach (EventPriority::ALL as $priority) {
					$priorityName = DevTools::EVENT_PRIORITY_NAMES[$priority];

					for ($currentList = $handlerList; $currentList !== null; $currentList = $currentList->getParent()) {
						$handlers = $handlerList->getListenersByPriority($priority);
						if (count($handlers) === 0) {
							continue;
						}

						if (!$found) {
							$found = true;
							$sender->sendMessage("--- Handlers called by " . TextFormat::GREEN . $className . TextFormat::WHITE . " ---");
						}

						foreach ($handlers as $handler) {
							$sender->sendMessage(
								"- " .
								TextFormat::DARK_GREEN . Utils::getNiceClosureName($handler->getHandler()) . TextFormat::RESET .
								" in plugin " .
								TextFormat::DARK_GREEN . $handler->getPlugin()->getName() . TextFormat::RESET .
								" at priority " .
								TextFormat::DARK_GREEN . $priorityName . TextFormat::RESET .
								" (handles cancelled events: " .
								TextFormat::DARK_GREEN . ($handler->isHandlingCancelled() ? "yes" : "no") . TextFormat::RESET .
								")"
							);
						}
					}
				}
			}
		}

		if (!$found) {
			$sender->sendMessage(TextFormat::RED . "No event handlers found for any classes containing \"" . $args[0] . "\"");
		}
	}
}