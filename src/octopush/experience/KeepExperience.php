<?php

namespace octopush\experience;

use AttachableLogger;
use JsonException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use PrefixedLogger;

class KeepExperience extends PluginBase implements Listener
{
	/** @var bool */
	public bool $keepExperience = true;

	/** @var PrefixedLogger */
	private PrefixedLogger $logger;

	/** @var array */
	private array $experience = [];

	public function onEnable(): void
	{
		$config = $this->getConfig();
		$this->keepExperience = (bool)$config->get('keep-experience', true);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->logger = new PrefixedLogger($this->getLogger(), "KeepExperience");
	}

	public function onDisable(): void
	{
		$config = $this->getConfig();
		$config->set('keep-experience', $this->keepExperience);
		try {
			$config->save();
		} catch (JsonException $exception) {
			$this->logger->error("Could not save config file, unexpected error: " . $exception->getMessage());
		}
	}

	/**
	 * @param CommandSender $sender
	 * @param Command $command
	 * @param string $label
	 * @param array $args
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		$cmd = $command->getName();
		if (!$sender instanceof Player) {
			return false;
		}
		if ($cmd == 'keepxp') {
			$this->keepExperience = !$this->keepExperience;
			if ($this->keepExperience) {
				$sender->sendMessage(C::GREEN . "Keep experience enabled!");
			} else {
				$this->experience = [];
				$sender->sendMessage(C::GREEN . "Keep experience disabled!");
			}
		}
		return true;
	}

	/**
	 * @param PlayerRespawnEvent $event
	 * @return void
	 * @priority MONITOR
	 */
	public function onPlayerRespawn(PlayerRespawnEvent $event): void
	{
		if (!$this->keepExperience) {
			return;
		}
		$player = $event->getPlayer();
		if (isset($this->experience[$player->getName()])) {
			$level = $this->experience[$player->getName()]['level'];
			$progress = $this->experience[$player->getName()]['progress'];
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $level, $progress){
				$player->getXpManager()->setXpAndProgress($level ?? 0, $progress ?? 0);
			}), 10);
		}
	}

	/**
	 * @param PlayerDeathEvent $event
	 * @return void
	 */
	public function onPlayerDeath(PlayerDeathEvent $event): void {
		$player = $event->getPlayer();
		if (isset($this->experience[$player->getName()])) {
			$event->setXpDropAmount(0);
		}
	}

	/**
	 * @param EntityDamageEvent $event
	 * @return void
	 */
	public function onEntityDamage(EntityDamageEvent $event): void {
		$player = $event->getEntity();
		if ($player instanceof Player) {
			if ($event->getFinalDamage() > $player->getHealth()){
				$this->experience[$player->getName()]['level'] = $player->getXpManager()->getXpLevel();
				$this->experience[$player->getName()]['progress'] = $player->getXpManager()->getXpProgress();
			}
		}
	}

	/**
	 * @return PrefixedLogger
	 */
	public function getPluginLogger(): PrefixedLogger
	{
		return $this->logger;
	}
}