<?php

namespace DevTools;

use DevTools\commands\CheckPermissionCommand;
use DevTools\commands\ExtractPluginCommand;
use DevTools\commands\GeneratePluginCommand;
use DevTools\commands\HandlersByPluginCommand;
use DevTools\commands\HandlersCommand;
use DevTools\commands\ListPermissionCommand;
use DevTools\commands\MakePluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\permission\PermissionAttachmentInfo;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use DevTools\plugin\FolderPluginLoader;
use pocketmine\utils\TextFormat;

final class DevTools extends PluginBase
{
	use SingletonTrait;

	public const EVENT_PRIORITY_NAMES = [
		EventPriority::LOWEST => "LOWEST",
		EventPriority::LOW => "LOW",
		EventPriority::NORMAL => "NORMAL",
		EventPriority::HIGH => "HIGH",
		EventPriority::HIGHEST => "HIGHEST",
		EventPriority::MONITOR => "MONITOR",
	];

	protected function onLoad(): void
	{
		require_once __DIR__ . "/ConsoleScript.php";

		self::setInstance($this);

		$this->getServer()->getPluginManager()->registerInterface(new FolderPluginLoader($this->getServer()->getLoader()));
	}

	protected function onEnable(): void
	{
		$this->getServer()->getCommandMap()->registerAll("devtools", [
			new CheckPermissionCommand($this), new ExtractPluginCommand($this), new GeneratePluginCommand($this),
			new HandlersByPluginCommand($this), new HandlersCommand($this), new ListPermissionCommand($this),
			new MakePluginCommand($this)
		]);

		$this->getLogger()->info("Registered folder plugin loader");
	}

	public function doMakePlugin(CommandSender $sender, array $args): bool
	{
		$pluginName = trim(implode(" ", $args));
		$plugin = $this->getServer()->getPluginManager()->getPlugin($pluginName);

		if (ini_get("phar.readonly") !== "0") {
			$sender->sendMessage(TextFormat::RED . "This command requires \"phar.readonly\" to be set to 0. Set it in " . php_ini_loaded_file() . "and restart the server.");
			return true;
		}

		if ($pluginName === "" or !$plugin instanceof Plugin) {
			$sender->sendMessage(TextFormat::RED . "Invalid plugin name, check the name case.");
			return false;
		}
		$description = $plugin->getDescription();

		if (!($plugin->getPluginLoader()) instanceof FolderPluginLoader) {
			$sender->sendMessage(TextFormat::RED . "Plugin " . $description->getName() . " is not in folder structure.");
			return false;
		}
		$pharPath = $this->getDataFolder() . $description->getName() . "_v" . $description->getVersion() . ".phar";
		$reflection = new \ReflectionClass(PluginBase::class);
		$file = $reflection->getProperty("file");
		$file->setAccessible(true);
		$pfile = rtrim($file->getValue($plugin), '/');
		$filePath = realpath($pfile);

		if ($filePath === false) {
			$sender->sendMessage(TextFormat::RED . "Plugin " . $description->getName() . " not found at $pfile (maybe deleted?)");
			return false;
		}

		$filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$metadata = generatePluginMetadataFromYml($filePath . "plugin.yml");
		assert($metadata !== null);

		if ($description->getName() === "DevTools") {
			$stub = sprintf(DEVTOOLS_REQUIRE_FILE_STUB, "src/ConsoleScript.php");
		} else {
			$stubMetadata = [];
			foreach ($metadata as $key => $value) {
				$stubMetadata[] = addslashes(ucfirst($key) . ": " . (is_array($value) ? implode(", ", $value) : $value));
			}
			$stub = sprintf(DEVTOOLS_PLUGIN_STUB, $description->getName(), $description->getVersion(), $this->getDescription()->getVersion(), date("r"), implode("\n", $stubMetadata));
		}

		$this->buildPhar($sender, $pharPath, $filePath, [], $metadata, $stub, \Phar::SHA1);
		$sender->sendMessage("Phar plugin " . $description->getName() . " v" . $description->getVersion() . " has been created on " . $pharPath);
		return true;
	}

	public function buildPhar(CommandSender $sender, string $pharPath, string $basePath, array $includedPaths, array $metadata, string $stub, int $signatureAlgo = \Phar::SHA1): void
	{
		foreach (buildPhar($pharPath, $basePath, $includedPaths, $metadata, $stub, $signatureAlgo, \Phar::GZ) as $line) {
			$sender->sendMessage("[DevTools] $line");
		}
	}

	public function describePermission(PermissionAttachmentInfo $info): string
	{
		$color = static function (PermissionAttachmentInfo $info, bool $dark): string {
			if ($info->getValue()) {
				$color = $dark ? TextFormat::DARK_GRAY : TextFormat::GREEN;
			} else {
				$color = $dark ? TextFormat::DARK_RED : TextFormat::RED;
			}
			return sprintf("%s%s%s", $color, $info->getPermission(), TextFormat::WHITE);
		};

		$value = static fn(bool $value): string => ($value ? TextFormat::GOLD . "true" : TextFormat::RED . "false") . TextFormat::WHITE;
		$group = $info->getGroupPermissionInfo();

		if ($group !== null) {
			return $color($info, false) . " is set to " . $value($info->getValue()) . " by " . $color($group, true);
		} else {
			$origin = $info->getAttachment();

			if ($origin !== null) {
				$originName = "plugin " . TextFormat::GREEN . $origin->getPlugin()->getName();
			} else {
				$originName = "base permission";
			}
			return $color($info, false) . "is set to" . $value($info->getValue()) . " explicitly by $originName" . TextFormat::WHITE;;
		}
	}
}