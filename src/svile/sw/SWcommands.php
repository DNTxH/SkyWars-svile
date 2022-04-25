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

use czechpmdevs\multiworld\generator\void\VoidGenerator;
use pocketmine\block\BlockFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\WorldCreationOptions;

class SWcommands
{
	/** @var SWmain */
	private $pg;


	public function __construct(SWmain $plugin)
	{
		$this->pg = $plugin;
	}


	/**
	 * @param CommandSender $sender
	 * @param Command $command
	 * @param $label
	 * @param array $args
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $command, $label, array $args)
	{
		if (!($sender instanceof Player) || !($sender->hasPermission(DefaultPermissions::ROOT_OPERATOR))) {
			switch (strtolower(array_shift($args))) {
				case 'join':
					if (!(count($args) < 0b11)) {
						$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw ' . TextFormat::GREEN . 'join [SWname]' . TextFormat::GRAY . ' [PlayerName]');
						break;
					}

					if (isset($args[0])) {
						//SW NAME
						$SWname = TextFormat::clean(array_shift($args));
						if (!array_key_exists($SWname, $this->pg->arenas)) {
							$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Arena with name: ' . TextFormat::WHITE . $SWname . TextFormat::RED . ' doesn\'t exist');
							break;
						}
					} else {
						if ($sender instanceof Player) {
							foreach ($this->pg->arenas as $a) {
								if ($a->join($sender, false))
									break 2;
							}
							$sender->sendMessage(TextFormat::RED . 'No games, retry later');
						}
						break;
					}

					$player = TextFormat::clean(array_shift($args));
					if (strlen($player) > 0 && $sender instanceof ConsoleCommandSender) {
						$p = $sender->getServer()->getPlayerByPrefix($player);
						if ($p instanceof Player) {
							if ($this->pg->inArena($p->getName())) {
								$p->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'You are already inside an arena');
								break;
							}
							$this->pg->arenas[$SWname]->join($p);
						} else {
							$sender->sendMessage(TextFormat::RED . 'Player not found!');
						}
					} elseif ($sender instanceof Player) {
						if ($this->pg->inArena($sender->getName())) {
							$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'You are already inside an arena');
							break;
						}
						$this->pg->arenas[$SWname]->join($sender);
					} else {
						$sender->sendMessage(TextFormat::RED . 'Player not found!');
					}
					break;


				case 'quit':
					if (!empty($args)) {
						$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw ' . TextFormat::GREEN . 'quit');
						break;
					}

					if ($sender instanceof Player) {
						foreach ($this->pg->arenas as $a) {
							if ($a->closePlayer($sender, true))
								break;
						}
					} else {
						$sender->sendMessage('This command is only avaible in game');
					}
					break;


				default:
					//No option found, usage
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw [join|quit]');
					break;
			}
			return true;
		}

		//Searchs for a valid option
		switch (strtolower(array_shift($args))) {
			case 'create':
				if (!(count($args) > 0b11 && count($args) < 0b101)) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw ' . TextFormat::GREEN . 'create [SWname] [slots] [countdown] [maxGameTime]');
					break;
				}

				$fworld = $sender->getWorld()->getFolderName();
				$world = $sender->getWorld()->getDisplayName();

				//Checks if the world is default
				if ($sender->getServer()->getWorldManager()->getDefaultWorld()->getDisplayName() === $world || $sender->getServer()->getWorldManager()->getDefaultWorld()->getFolderName() === $fworld) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'You can\'t create an arena in the default world');
					break;
				}

				//Checks if there is already an arena in the world
				foreach ($this->pg->arenas as $aname => $arena) {
					if ($arena->getWorld() == $world) {
						$sender->sendMessage(TextFormat::RED . '→' . TextFormat::RED . 'You can\'t create 2 arenas in the same world try:');
						$sender->sendMessage(TextFormat::RED . '→' . TextFormat::WHITE . '/sw list' . TextFormat::RED . ' for a list of arenas');
						$sender->sendMessage(TextFormat::RED . '→' . TextFormat::WHITE . '/sw delete' . TextFormat::RED . ' to delete an arena');
						break 2;
					}
				}

				//Checks if there is already a join sign in the world
				foreach ($this->pg->signs as $loc => $name) {
					if (explode(':', $loc)[3] == $world) {
						$sender->sendMessage(TextFormat::RED . '→' . TextFormat::RED . 'You can\'t create an arena in the same world of a join sign:');
						$sender->sendMessage(TextFormat::RED . '→' . TextFormat::WHITE . '/sw signdelete' . TextFormat::RED . ' to delete signs');
						break 2;
					}
				}

				//SW NAME
				$SWname = array_shift($args);
				if (!($SWname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $SWname) && strlen($SWname) < 0x10 && strlen($SWname) > 0b10)) {
					$sender->sendMessage(TextFormat::WHITE . '→' . TextFormat::AQUA . '[SWname]' . TextFormat::RED . ' must consists of a-z 0-9 (min3-max15)');
					break;
				}

				//Checks if the arena already exists
				if (array_key_exists($SWname, $this->pg->arenas)) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Arena with name: ' . TextFormat::WHITE . $SWname . TextFormat::RED . ' already exist');
					break;
				}

				//ARENA SLOT
				$slot = array_shift($args);
				if (!($slot && is_numeric($slot) && is_int(($slot + 0)) && $slot < 0x33 && $slot > 1)) {
					$sender->sendMessage(TextFormat::WHITE . '→' . TextFormat::AQUA . '[slots]' . TextFormat::RED . ' must be an integer >= 50 and >= 2');
					break;
				}
				$slot += 0;

				//ARENA COUNTDOWN
				$countdown = array_shift($args);
				if (!($countdown && is_numeric($countdown) && is_int(($countdown + 0)) && $countdown > 0b1001 && $countdown < 0x12d)) {
					$sender->sendMessage(TextFormat::WHITE . '→' . TextFormat::AQUA . '[countdown]' . TextFormat::RED . ' must be an integer <= 300 seconds (5 minutes) and >= 10');
					break;
				}
				$countdown += 0;

				//ARENA MAX EXECUTION TIME
				$maxtime = array_shift($args);
				if (!($maxtime && is_numeric($maxtime) && is_int(($maxtime + 0)) && $maxtime > 0x12b && $maxtime < 0x259)) {
					$sender->sendMessage(TextFormat::WHITE . '→' . TextFormat::AQUA . '[maxGameTime]' . TextFormat::RED . ' must be an integer <= 600 (10 minutes) and >= 300');
					unset($fworld, $world, $SWname, $slot, $countdown, $maxtime);
					break;
				}
				$maxtime += 0;

				//ARENA LEVEL NAME
				/*if ($fworld == $world) {
					//$sender->sendMessage(TextFormat::WHITE . '→' . TextFormat::RED . 'Using the world were you are now: ' . TextFormat::AQUA . $world . TextFormat::RED . ' ,expected lag');
				} else {
					//worldName
					$sender->sendMessage(TextFormat::WHITE . '→' . TextFormat::RED . 'There is a problem with the world name');
					break;
				}*/

				//Air world generator
//		        $provider = $sender->getWorld()->getProvider();
				if ($this->pg->config['world.generator.air']/* && $provider instanceof BaseWorldProvider*/) {
//			        $provider->getLevelData()->generatorName = new Str('generatorName', 'flat');
//			        $provider->getLevelData()->generatorOptions = new Str('generatorOptions', '0;0;0');
//			        $provider->saveLevelData();
					$opts = new WorldCreationOptions();
					$opts->setGeneratorClass(VoidGenerator::class);
					$opts->setGeneratorOptions('0;0;0');
					$opts->setDifficulty(0);
					$opts->setSeed(0);
					$opts->setSpawnPosition(new Vector3(0, 4, 0));
					$this->pg->getServer()->getWorldManager()->generateWorld($SWname, $opts);
				}

				$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::LIGHT_PURPLE . 'I\'m creating a backup of the world ' . TextFormat::AQUA . $world . TextFormat::LIGHT_PURPLE . ', do not move');


				//This is the "fake void"
				$last = 0x80;
				foreach ($sender->getWorld()->getLoadedChunks() as $chunk) {
					for ($x = 0; $x < 0x10; $x++) {
						for ($z = 0; $z < 0x10; $z++) {
							for ($y = 0; $y < 0x7f; $y++) {
								$block = $chunk->getTile($x, $y, $z);
								if($block === null)continue;
								if ($block->getBlock()->getId() !== 0 && $last > $y) {
									$last = $y;
									break;
								}
							}
						}
					}
				}
				$void = ($last - 1);

				$sender->teleport($sender->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
				foreach ($sender->getServer()->getWorldManager()->getWorldByName($world)->getPlayers() as $p)
					$p->teleport($this->pg->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
				$sender->getServer()->getWorldManager()->unloadWorld($sender->getServer()->getWorldManager()->getWorldByName($world));

				//From here @vars are: $SWname , $slot , $world
				// { TAR.GZ
				@mkdir($this->pg->getDataFolder() . 'arenas/' . $SWname, 0755);
				$tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $SWname . '/' . $world . '.tar');
				$tar->startBuffering();
				$tar->buildFromDirectory(realpath($sender->getServer()->getDataPath() . 'worlds/' . $world));
				if ($this->pg->config['world.compress.tar'])
					$tar->compress(\Phar::GZ);
				$tar->stopBuffering();
				if ($this->pg->config['world.compress.tar']) {
					$tar = null;
					@unlink($this->pg->getDataFolder() . 'arenas/' . $SWname . '/' . $world . '.tar');
				}
				unset($tar);
				$sender->getServer()->getWorldManager()->loadWorld($world);
				// END TAR.GZ }

				//SWarena object
				$this->pg->arenas[$SWname] = new SWarena($this->pg, $SWname, $slot, $world, $countdown, $maxtime, $void);
				$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Arena: ' . TextFormat::DARK_GREEN . $SWname . TextFormat::GREEN . ' created successfully!');
				$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Now set spawns with ' . TextFormat::WHITE . '/sw setspawn [slot]');
				$sender->teleport($sender->getServer()->getWorldManager()->getWorldByName($world)->getSpawnLocation());
				break;


			case 'setspawn':
				if (count($args) != 1) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw ' . TextFormat::GREEN . 'setspawn [slot]');
					break;
				}

				$SWname = '';
				foreach ($this->pg->arenas as $name => $arena) {
					if ($arena->getWorld() == $sender->getWorld()->getDisplayName()) {
						$SWname = $name;
						break;
					}
				}
				if (!($SWname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $SWname) && strlen($SWname) < 0x10 && strlen($SWname) > 0b10 && array_key_exists($SWname, $this->pg->arenas))) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Arena not found here, try ' . TextFormat::WHITE . '/sw create');
					unset($SWname);
					break;
				}

				$slot = array_shift($args);
				if (!($slot && is_numeric($slot) && is_int(($slot + 0)) && $slot < 0x33 && $slot > 0)) {
					$sender->sendMessage(TextFormat::WHITE . '→' . TextFormat::AQUA . '[slot]' . TextFormat::RED . ' must be an integer <= than 50 and >= 1');
					unset($SWname, $slot);
					break;
				}
				$slot += 0;

				if ($sender->getWorld()->getDisplayName() == $this->pg->arenas[$SWname]->getWorld()) {
					if ($this->pg->arenas[$SWname]->setSpawn($sender, $slot)) {
						$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'New spawn: ' . TextFormat::WHITE . $slot . TextFormat::GREEN . ' In arena: ' . TextFormat::WHITE . $SWname);
						if ($this->pg->arenas[$SWname]->checkSpawns())
							$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'I found all the spawns for Arena: ' . TextFormat::WHITE . $SWname . TextFormat::GREEN . ', now you can create a join sign!');
					}
				}
				break;


			case 'list':
				if (count($this->pg->arenas) > 0) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Loaded arenas:');
					foreach ($this->pg->arenas as $key => $val) {
						$sender->sendMessage(TextFormat::BLACK . '→ ' . TextFormat::YELLOW . $key . TextFormat::AQUA . ' [' . $val->getSlot(true) . '/' . $val->getSlot() . ']' . TextFormat::DARK_GRAY . ' => ' . TextFormat::GREEN . $val->getWorld());
					}
				} else {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'There aren\'t loaded arenas, create one with ' . TextFormat::WHITE . '/sw create');
				}
				break;
			case 'delete':
				if (count($args) != 1) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw ' . TextFormat::GREEN . 'delete [SWname]');
					break;
				}

				$SWname = array_shift($args);
				if (!($SWname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $SWname) && strlen($SWname) < 0x10 && strlen($SWname) > 0b10 && array_key_exists($SWname, $this->pg->arenas))) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Arena: ' . TextFormat::WHITE . $SWname . TextFormat::RED . ' doesn\'t exist');
					break;
				}

				if (!(is_dir($this->pg->getDataFolder() . 'arenas/' . $SWname) && is_file($this->pg->getDataFolder() . 'arenas/' . $SWname . '/settings.yml'))) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Arena files doesn\'t exists');
					break;
				}

				$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Please wait, this can take a bit');
				$this->pg->arenas[$SWname]->stop(true);
				foreach ($this->pg->signs as $loc => $name) {
					if ($SWname == $name) {
						$ex = explode(':', $loc);
						if ($sender->getServer()->getWorldManager()->loadWorld($ex[0b11])) {
							$sender->getServer()->getWorldManager()->loadWorld($ex[0b11]);
							$block = $sender->getServer()->getWorldManager()->getWorld($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
							if ($block->getId() == 0x3f || $block->getId() == 0x44)
								$sender->getServer()->getWorldManager()->getWorld($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), BlockFactory::getInstance()->get(0, 0));
						}
					}
				}
				$this->pg->setSign($SWname, 0, 0, 0, 'world', true, false);
				unset($this->pg->arenas[$SWname]);

				foreach (scandir($this->pg->getDataFolder() . 'arenas/' . $SWname) as $file) {
					if ($file != '.' && $file != '..' && is_file($this->pg->getDataFolder() . 'arenas/' . $SWname . '/' . $file)) {
						@unlink($this->pg->getDataFolder() . 'arenas/' . $SWname . '/' . $file);
					}
				}
				@rmdir($this->pg->getDataFolder() . 'arenas/' . $SWname);
				$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Arena: ' . TextFormat::DARK_GREEN . $SWname . TextFormat::GREEN . ' Deleted !');
				break;


			case 'signdelete':
				if (count($args) != 1) {
					$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw ' . TextFormat::GREEN . 'signdelete [SWname|all]');
					break;
				}

				$SWname = array_shift($args);
				if (!array_key_exists($SWname, $this->pg->arenas)) {
					if ($SWname == 'all') {
						//Deleting SW signs blocks
						foreach ($this->pg->signs as $loc => $name) {
							$ex = explode(':', $loc);
							if ($sender->getServer()->getWorldManager()->loadWorld($ex[0b11])) {
								$block = $sender->getServer()->getWorldManager()->getWorld($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
								if ($block->getId() == 0x3f || $block->getId() == 0x44)
									$sender->getServer()->getWorldManager()->getWorld($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), BlockFactory::getInstance()->get(0, 0));
							}
						}
						//Deleting signs from db & array
						$this->pg->setSign($SWname, 0, 0, 0, 'world', true);
						$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Deleted all SW signs!');
					} else {
						$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Arena: ' . TextFormat::WHITE . $SWname . TextFormat::RED . ' doesn\'t exist');
						unset($SWname);
					}
					break;
				}
				$this->pg->arenas[$SWname]->stop(true);
				foreach ($this->pg->signs as $loc => $name) {
					if ($SWname == $name) {
						$ex = explode(':', $loc);
						if ($sender->getServer()->getWorldManager()->getWorldByName($ex[0b11])) {
							$block = $sender->getServer()->getWorldManager()->getWorldByName($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
							if ($block->getId() == 0x3f || $block->getId() == 0x44)
								$sender->getServer()->getWorldManager()->getWorldByName($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), BlockFactory::getInstance()->get(0, 0));
						}
					}
				}
				$this->pg->setSign($SWname, 0, 0, 0, 'world', true, false);
				$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Deleted signs for arena: ' . TextFormat::DARK_GREEN . $SWname);
				break;


			default:
				//No option found, usage
				$sender->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Usage: /sw [create|setspawn|list|delete|signdelete]');
				break;
		}
		return true;
	}
}