<?php

declare(strict_types=1);

namespace DevTools\commands;

use DevTools\DevTools;
use DevTools\plugin\FolderPluginLoader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\utils\TextFormat;
use function trim;
use function count;

final class MakePluginCommand extends Command
{
	public function __construct(protected DevTools $plugin)
	{
		parent::__construct(strtolower("makeplugin"), "Creates a Phar plugin from one in source code form");
		$this->setPermission("devtools.command.makeplugin");
		$this->setUsage("/makeplugin <pluginName>");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if (isset($args[0]) and trim($args[0]) === "*") {
			$plugins = $this->plugin->getServer()->getPluginManager()->getPlugins();
			$succeeded = [];
			$failed = [];
			$skipped = 0;

			foreach ($plugins as $plugin) {
				if (!$plugin->getPluginLoader() instanceof FolderPluginLoader) {
					$skipped++;
					continue;
				}

				if ($this->plugin->doMakePlugin($sender, [$plugin->getName()])) {
					$succeeded[] = $plugin;
				} else {
					$failed[] = $plugin;
				}
			}
			if (count($failed) > 0) {
				$sender->sendMessage(TextFormat::RED . count($failed) . " plugin" . (count($failed) === 1 ? "" : "s") . " failed to build: " . implode(", ", $failed));
			}

			if (count($succeeded) > 0) {
				$sender->sendMessage(TextFormat::GREEN . count($succeeded) . "/" . (count($plugins) - $skipped) . " plugin" . ((count($plugins) - $skipped) === 1 ? "" : "s") . " successfully built: " . implode(", ", $succeeded));
			}
		} else {
			$this->plugin->doMakePlugin($sender, $args);
		}
	}
}