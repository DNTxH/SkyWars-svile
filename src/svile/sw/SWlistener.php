<?php

/*
 *                _   _
 *  ___  __   __ (_) | |   ___
 * / __| \ \ / / | | | |  / _ \
 * \__ \  \ / /  | | | | |  __/
 * |___/   \_/   |_| |_|  \___|
 *
 * SkyWars plugin for PocketMine-MP & forks
 *
 * @Author: svile
 * @Kik: _svile_
 * @Telegram_Group: https://telegram.me/svile
 * @E-mail: thesville@gmail.com
 * @Github: https://github.com/svilex/SkyWars-PocketMine
 *
 * Copyright (C) 2016 svile
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * DONORS LIST :
 * - Ahmet
 * - Jinsong Liu
 * - no one
 *
 */

namespace svile\sw;

use pocketmine\block\BaseSign;
use pocketmine\block\utils\SignText;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class SWlistener implements Listener
{
	/** @var SWmain */
	private $pg;


	public function __construct(SWmain $plugin)
	{
		$this->pg = $plugin;
	}


	public function onSignChange(SignChangeEvent $ev)
	{
		if ($ev->getNewText()->getLine(0) != 'sw' || !$ev->getPlayer()->hasPermission(DefaultPermissions::ROOT_OPERATOR))
			return;

		//Checks if the arena exists
		$SWname = TextFormat::clean(trim($ev->getNewText()->getLine(1)));
		if (!array_key_exists($SWname, $this->pg->arenas)) {
			$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'This arena doesn\'t exist, try ' . TextFormat::WHITE . '/sw create');
			return;
		}

		//Checks if a sign already exists for the arena
		if (in_array($SWname, $this->pg->signs)) {
			$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'A sign for this arena already exist, try ' . TextFormat::WHITE . '/sw signdelete');
			return;
		}

		//Checks if the sign is placed inside arenas
		$world = $ev->getPlayer()->getWorld()->getDisplayName();
		foreach ($this->pg->arenas as $name => $arena) {
			if ($world == $arena->getWorld()) {
				$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'You can\'t place the join sign inside arenas');
				return;
			}
		}

		//Checks arena spawns
		if (!$this->pg->arenas[$SWname]->checkSpawns()) {
			$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Not all the spawns are set in this arena, try ' . TextFormat::WHITE . ' /sw setspawn');
			return;
		}

		//Saves the sign
		if (!$this->pg->setSign($SWname, $ev->getBlock()->getPosition()->getX(), $ev->getBlock()->getPosition()->getY(), $ev->getBlock()->getPosition()->getZ(), $world))
			$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'An error occurred, please contact the developer');
		else
			$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'SW join sign created!');

		//Sets sign format
		$ev->setNewText(new SignText([
			0 => $this->pg->config['1st_line'],
			1 => str_replace('{SWNAME}', $SWname, $this->pg->config['2nd_line']),
			2 => TextFormat::GREEN . '0' . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $this->pg->arenas[$SWname]->getSlot(),
			3 => TextFormat::WHITE . 'Tap to join'
		]));

		$this->pg->refreshSigns(true);
	}

	public function onInteract(PlayerInteractEvent $ev)
	{
		if ($ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK)
			return;

		//In-arena Tap
		foreach ($this->pg->arenas as $a) {
			if ($t = $a->inArena($ev->getPlayer()->getName())) {
				if ($t == 2)
					$ev->cancel();
				if ($a->GAME_STATE == 0)
					$ev->cancel();
				return;
			}
		}

		//Join sign Tap check
		$key = $ev->getBlock()->getPosition()->x . ':' . $ev->getBlock()->getPosition()->y . ':' . $ev->getBlock()->getPosition()->z . ':' . $ev->getBlock()->getPosition()->getWorld()->getDisplayName();
		if (array_key_exists($key, $this->pg->signs))
			$this->pg->arenas[$this->pg->signs[$key]]->join($ev->getPlayer());
		unset($key);
	}


	public function onLevelChange(EntityTeleportEvent $ev)
	{
		$entity = $ev->getEntity();
		if ($entity instanceof Player) {
			foreach ($this->pg->arenas as $a) {
				if ($a->inArena($entity->getName())) {
					$ev->cancel();
					break;
				}
			}
		}
	}


	public function onTeleport(EntityTeleportEvent $ev)
	{
		if ($ev->getEntity() instanceof Player) {
			foreach ($this->pg->arenas as $a) {
				if ($a->inArena($ev->getEntity()->getName())) {
					//Allow near teleport
					if ($ev->getFrom()->distanceSquared($ev->getTo()) < 20)
						break;
					$ev->cancel();
					break;
				}
			}
		}
	}


	public function onDropItem(PlayerDropItemEvent $ev)
	{
		foreach ($this->pg->arenas as $a) {
			if (($f = $a->inArena($ev->getPlayer()->getName()))) {
				if ($f == 2) {
					$ev->cancel();
					break;
				}
				if (!$this->pg->config['player.drop.item']) {
					$ev->cancel();
					break;
				}
				break;
			}
		}
	}


	public function onPickUp(EntityItemPickupEvent $ev)
	{
		if (($p = $ev->getInventory()->getHolder()) instanceof Player) {
			foreach ($this->pg->arenas as $a) {
				if ($f = $a->inArena($p->getName())) {
					if ($f == 2)
						$ev->cancel();
					break;
				}
			}
		}
	}


	public function onItemHeld(PlayerItemHeldEvent $ev)
	{
		foreach ($this->pg->arenas as $a) {
			if ($f = $a->inArena($ev->getPlayer()->getName())) {
				if ($f == 2) {
					if (($ev->getItem()->getId() . ':' . $ev->getItem()->getMeta()) == $this->pg->config['spectator.quit.item'])
						$a->closePlayer($ev->getPlayer());
					$ev->cancel();
					$ev->getPlayer()->getInventory()->setHeldItemIndex(1);
				}
				break;
			}
		}
	}


	public function onMove(PlayerMoveEvent $ev)
	{
		foreach ($this->pg->arenas as $a) {
			if ($a->inArena($ev->getPlayer()->getName())) {
				if ($a->GAME_STATE == 0) {
					$spawn = $a->getWorld(true, $ev->getPlayer()->getName());
//					var_dump($spawn);
					$world = $ev->getPlayer()->getWorld(); //todo: not sure if spawn has world
					if ($ev->getPlayer()->getPosition()->distanceSquared(new Position($spawn['x'], $spawn['y'], $spawn['z'], $world)) > 4)
						$ev->setTo(new Location($spawn['x'], $spawn['y'], $spawn['z'], $world, $spawn['yaw'], $spawn['pitch']));
					break;
				}
				if ($a->void >= $ev->getPlayer()->getPosition()->getFloorY() && $ev->getPlayer()->isAlive()) {
					$event = new EntityDamageEvent($ev->getPlayer(), EntityDamageEvent::CAUSE_VOID, 10);
					$ev->getPlayer()->attack($event);//$event->getFinalDamage()
				}
				return;
			}
		}

		//Checks if knockBack is enabled
		if ($this->pg->config['sign.knockBack']) {
			$radius = $this->pg->config["knockBack.radius.from.sign"];
			$intensity = $this->pg->config["knockBack.intensity"]; // / 5
			//Todo: svile is dumb make a new code soon
		}
	}


	public function onQuit(PlayerQuitEvent $ev)
	{
		foreach ($this->pg->arenas as $a) {
			if ($a->closePlayer($ev->getPlayer(), true))
				break;
		}
	}


	public function onDeath(PlayerDeathEvent $event)
	{
		if ($event->getEntity() instanceof Player) {
			$p = $event->getEntity();
			foreach ($this->pg->arenas as $a) {
				if ($a->closePlayer($p)) {
					$event->setDeathMessage('');
					$cause = $event->getEntity()->getLastDamageCause()->getCause();
					$ev = $event->getEntity()->getLastDamageCause();
					$count = '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']';
					$message = "";

					switch ($cause) {
						case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
							if ($ev instanceof EntityDamageByEntityEvent) {
								$d = $ev->getDamager();
								if ($d instanceof Player)
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
								elseif ($d instanceof Living)
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getNameTag() !== '' ? $d->getNameTag() : $d->getName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
								else
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
							}
							break;
						case EntityDamageEvent::CAUSE_PROJECTILE:
							if ($ev instanceof EntityDamageByEntityEvent) {
								$d = $ev->getDamager();
								if ($d instanceof Player)
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
								elseif ($d instanceof Living)
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getNameTag() !== '' ? $d->getNameTag() : $d->getName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
								else
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
							}
							break;
						case EntityDamageEvent::CAUSE_VOID:
							$message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.void']));
							break;
						case EntityDamageEvent::CAUSE_LAVA:
							$message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.lava']));
							break;
						default:
							$message = str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['game.left']));
							break;
					}

					foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($a->getWorld())->getPlayers() as $pl)
						$pl->sendMessage($message);

					if (!$this->pg->config['drops.on.death'])
						$event->setDrops([]);
					break;
				}
			}
		}
	}


	public function onDamage(EntityDamageEvent $ev)
	{
		if ($ev->getEntity() instanceof Player) {
			$p = $ev->getEntity();
			foreach ($this->pg->arenas as $a) {
				if ($f = $a->inArena($p->getName())) {
					if ($f != 1) {
						$ev->cancel();
						break;
					}
					if ($ev instanceof EntityDamageByEntityEvent && ($d = $ev->getDamager()) instanceof Player) {
						if (($f = $a->inArena($d->getName())) == 2 || $f == 0) {
							$ev->cancel();
							break;
						}
					}
					$cause = (int)$ev->getCause();
					if (in_array($cause, $this->pg->config['damage.cancelled.causes'])) {
						$ev->cancel();
						break;
					}
					if ($a->GAME_STATE == 0 || $a->GAME_STATE == 2) {
						$ev->cancel();
						break;
					}

					//SPECTATORS
					$spectate = (bool)$this->pg->config['death.spectator'];
					if ($spectate && !$ev->isCancelled()) {
						if (($p->getHealth() - $ev->getFinalDamage()) <= 0) {
							$ev->cancel();
							//FAKE KILL PLAYER MSG
							$count = '[' . ($a->getSlot(true) - 1) . '/' . $a->getSlot() . ']';
							$message = "";

							switch ($cause) {
								case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
									if ($ev instanceof EntityDamageByEntityEvent) {
										$d = $ev->getDamager();
										if ($d instanceof Player)
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
										elseif ($d instanceof Living)
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getNameTag() !== '' ? $d->getNameTag() : $d->getName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
										else
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
									}
									break;
								case EntityDamageEvent::CAUSE_PROJECTILE:
									if ($ev instanceof EntityDamageByEntityEvent) {
										$d = $ev->getDamager();
										if ($d instanceof Player)
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
										elseif ($d instanceof Living)
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getNameTag() !== '' ? $d->getNameTag() : $d->getName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
										else
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
									}
									break;
								case EntityDamageEvent::CAUSE_VOID:
									$message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.void']));
									break;
								case EntityDamageEvent::CAUSE_LAVA:
									$message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.lava']));
									break;
								default:
									$message = str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['game.left']));
									break;
							}

							foreach ($p->getLevel()->getPlayers() as $pl)
								$pl->sendMessage($message);

							//DROPS
							if ($this->pg->config['drops.on.death']) {
								foreach ($p->getDrops() as $item) {
									$p->getLevel()->dropItem($p, $item);
								}
							}

							//CLOSE
							$a->closePlayer($p, false, true);
						}
					}
					break;
				}
			}
		}
	}


	public function onRespawn(PlayerRespawnEvent $ev)
	{
		if ($this->pg->config['always.spawn.in.defaultLevel'])
			$ev->setRespawnPosition($this->pg->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
		//Removes player things
		if ($this->pg->config['clear.inventory.on.respawn&join'])
			$ev->getPlayer()->getInventory()->clearAll();
		if ($this->pg->config['clear.effects.on.respawn&join'])
			$ev->getPlayer()->getEffects()->clear();
	}


	public function onBreak(BlockBreakEvent $ev)
	{
		foreach ($this->pg->arenas as $a) {
			if ($t = $a->inArena($ev->getPlayer()->getName())) {
				if ($t == 2)
					$ev->cancel();
				if ($a->GAME_STATE == 0)
					$ev->cancel();
				break;
			}
		}
		if (!$ev->getPlayer()->hasPermission(DefaultPermissions::ROOT_OPERATOR))
			return;
		$key = ($ev->getBlock()->getPosition()->getX() . ':' . $ev->getBlock()->getPosition()->getY() . ':' . $ev->getBlock()->getPosition()->getZ() . ':' . $ev->getPlayer()->getWorld()->getFolderName());
		if (array_key_exists($key, $this->pg->signs)) {
			$this->pg->arenas[$this->pg->signs[$key]]->stop(true);
			$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Arena reloaded!');
			if ($this->pg->setSign($this->pg->signs[$key], 0, 0, 0, 'world', true, false)) {
				$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'SW join sign deleted!');
			} else {
				$ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'An error occurred, please contact the developer');
			}
		}
		unset($key);
	}


	public function onPlace(BlockPlaceEvent $ev)
	{
		foreach ($this->pg->arenas as $a) {
			if ($t = $a->inArena($ev->getPlayer()->getName())) {
				if ($t == 2)
					$ev->cancel();
				if ($a->GAME_STATE == 0)
					$ev->cancel();
				break;
			}
		}
	}


	public function onCommand(PlayerCommandPreprocessEvent $ev)
	{
		$command = strtolower($ev->getMessage());
		if ($command[0] == '/') {
			$command = explode(' ', $command)[0];
			if ($this->pg->inArena($ev->getPlayer()->getName())) {
				if (in_array($command, $this->pg->config['banned.commands.while.in.game'])) {
					$ev->getPlayer()->sendMessage($this->pg->lang['banned.command.msg']);
					$ev->cancel();
				}
			}
		}
		unset($command);
	}
}