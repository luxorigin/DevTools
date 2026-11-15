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

final class HandlersByPluginCommand extends Command
{
	public function __construct(protected DevTools $plugin)
	{
		parent::__construct(strtolower("handlersbyplugin"), "Lists all event handlers registered by a given plugin");
		$this->setPermission("devtools.command.handlersbyplugin");
		$this->setUsage("/{$this->getName()} <pluginName>");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if (count($args) !== 1) return;

		$plugin = $this->plugin->getServer()->getPluginManager()->getPlugin($args[0]);

		if ($plugin === null) {
			$sender->sendMessage(TextFormat::RED . "No plugin found with name : {$args[0]}");
			return;
		}

		$sender->sendMessage("--- Event handlers registered by plugin " . TextFormat::GREEN . $plugin->getName() . TextFormat::WHITE . " ---");

		foreach (HandlerListManager::global()->getAll() as $className => $handlerList) {
			foreach (EventPriority::ALL as $priority) {
				$priorityName = DevTools::EVENT_PRIORITY_NAMES[$priority];

				for ($currentList = $handlerList; $currentList !== null; $currentList = $currentList->getParent()) {
					$handlers = $handlerList->getListenersByPriority($priority);

					if (count($handlers) === 0) {
						continue;
					}

					foreach ($handlers as $handler) {
						if ($handler->getPlugin() !== $plugin) continue;

						$sender->sendMessage(
							"- " .
							TextFormat::DARK_GREEN . Utils::getNiceClosureName($handler->getHandler()) . TextFormat::RESET .
							" handles event " .
							TextFormat::DARK_GREEN . $className . TextFormat::RESET .
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
}