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

use pocketmine\block\tile\Sign;
use pocketmine\block\utils\SignText;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class SWmain extends PluginBase
{
	/** Plugin Version */
	const SW_VERSION = '1.0';
	/** @var SWarena[] */
	public $arenas = [];
	/** @var array */
	public $signs = [];
	/** @var array */
	public $config;
	/** @var array */
	public $lang;
	/** @var Config */
	public $signcfg;
	/** @var SWcommands */
	private $commands;

	public function onLoad(): void
	{
		if ($this->getDescription()->getVersion() != self::SW_VERSION) {
			$this->getLogger()->critical("There is a problem with the plugin version");
		}
		if (!is_dir($this->getDataFolder())) {
			@mkdir($this->getDataFolder(), 0755, true);
		}
		if (!is_dir($this->getDataFolder() . "arenas")) {
			@mkdir($this->getDataFolder() . "arenas", 0755, true);
		}
		if (is_file($this->getDataFolder() . "config.yml")) {
			$v = ((new Config($this->getDataFolder() . 'config.yml', CONFIG::YAML))->get('CONFIG_VERSION', '1.0'));
			if ($v != '1.0' && $v != self::SW_VERSION) {
				$this->getLogger()->notice("You are using old configs, deleting them. make sure to delete old arenas if aren't working");
				@unlink($this->getDataFolder() . 'config.yml');
				@unlink($this->getDataFolder() . 'lang.yml');
				$this->saveResource('config.yml', true);
			} elseif ($v == '1.0') {
				$this->saveResource('config.yml', true);
			}
			unset($v);
		} else {
			$this->saveResource('config.yml', true);
		}
	}


	public function onEnable(): void
	{
		$this->config = new Config($this->getDataFolder() . 'config.yml', CONFIG::YAML);
		$this->config = $this->config->getAll();

		$this->lang = new Config($this->getDataFolder() . 'lang.yml', CONFIG::YAML, [
			'banned.command.msg' => '@b→@cYou can\'t use this command here',
			'sign.game.full' => '@b→@cThis game is full, please wait',
			'sign.game.running' => '@b→@cThe game is running, please wait',
			'game.join' => '@b→@f{PLAYER} @ejoined the game @b{COUNT}',
			'popup.countdown' => '@bThe game starts in @f{N}',
			'chat.countdown' => '@b→@7The game starts in @b{N}',
			'game.start' => '@b→@dThe game start now, good luck !',
			'no.pvp.countdown' => '@bYou can\'t PvP for @f{COUNT} @bseconds',
			'game.chest.refill' => '@b→@aChests has been refilled !',
			'game.left' => '@f→@7{PLAYER} left the game @b{COUNT}',
			'death.player' => '@c→@f{PLAYER} @cwas killed by @f{KILLER} @b{COUNT}',
			'death.arrow' => '@c→@f{PLAYER} @cwas killed by @f{KILLER} @b{COUNT}',
			'death.void' => '@c→@f{PLAYER} @cwas killed by @fVOID @b{COUNT}',
			'death.lava' => '@c→@f{PLAYER} @cwas killed by @fLAVA @b{COUNT}',//TODO: add more?
			'death.spectator' => '@f→@bYou are now a spectator!_EOL_@f→@bType @f/sw quit @bto exit from the game',
			'server.broadcast.winner' => '@0→@f{PLAYER} @bwon the game on SW: @f{SWNAME}',
			'winner.reward.msg' => '@f→@bYou won @f{VALUE}$_EOL_@f→@7Your money: @f{MONEY}$'
		]);
		touch($this->getDataFolder() . 'lang.yml');
		$this->lang = $this->lang->getAll();
		file_put_contents($this->getDataFolder() . 'lang.yml', '#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL . str_replace('#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL, '', file_get_contents($this->getDataFolder() . 'lang.yml')));
		$newlang = [];
		foreach ($this->lang as $key => $val) {
			$newlang[$key] = str_replace('  ', ' ', str_replace('_EOL_', "\n", str_replace('@', '§', trim($val))));
		}
		$this->lang = $newlang;
		unset($newlang);

		$this->signcfg = new Config($this->getDataFolder() . "signs.yml", Config::YAML);

		$this->getScheduler()->scheduleRepeatingTask(new SWtimer($this), 19);
		$this->getServer()->getPluginManager()->registerEvents(new SWlistener($this), $this);

		$this->loadSigns();
		$this->loadArenas();

		$this->commands = new SWcommands($this);

		$this->getLogger()->info(str_replace('\n', PHP_EOL, "\n" . @base64_decode("CsKnbMKnYyoqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqCsKnbMKnZUF1dGhvcnM6IMKnZnN2aWxlLCBWYXhQZXgKwqdswqdlS2lrOiDCp2Zfc3ZpbGVfCsKnbMKnZVRlbGVncmFtX0dyb3VwOiDCp2ZodHRwczovL3RlbGVncmFtLm1lL3N2aWxlCsKnbMKnZUUtbWFpbDogwqdmdGhlc3ZpbGxlQGdtYWlsLmNvbQrCp2zCp2VHaXRodWI6IMKnZmh0dHBzOi8vZ2l0aHViLmNvbS9WYXhQZXgvU2t5V2Fyc1N2aWxlCsKnbMKnYlNreVdhcnMgcGx1Z2luIGJ5IHN2aWxlOiDCp2FFTkFCTEVECsKnbMKnYyoqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqCg==")));
	}

	/**
	 * @return bool
	 */
	public function loadSigns()
	{
		$this->signs = [];
		$array = $this->signcfg->getAll();
		foreach ($array as $item => $value){
			$this->signs[$value['x'] . ':' . $value['y'] . ':' . $value['z'] . ':' . $value['world']] = $value['arena'];
		}
		if (empty($this->signs) && !empty($array))
			return false;
		else
			return true;
	}

	/**
	 * @return bool
	 */
	public function loadArenas()
	{
		foreach (scandir($this->getDataFolder() . 'arenas/') as $arenadir) {
			if ($arenadir != '..' && $arenadir != '.' && is_dir($this->getDataFolder() . 'arenas/' . $arenadir)) {
				if (is_file($this->getDataFolder() . 'arenas/' . $arenadir . '/settings.yml')) {
					$config = new Config($this->getDataFolder() . 'arenas/' . $arenadir . '/settings.yml', CONFIG::YAML, [
						'name' => 'default',
						'slot' => 0,
						'world' => 'world_1',
						'countdown' => 30,
						'maxGameTime' => 600,
						'void_Y' => 0,
						'spawns' => [],
					]);
					$this->arenas[$config->get('name')] = new SWarena($this, $config->get('name'), ($config->get('slot') + 0), $config->get('world'), ($config->get('countdown') + 0), ($config->get('maxGameTime') + 0), ($config->get('void_Y') + 0));
					unset($config);
				} else {
					return false;
				}
			}
		}
		return true;
	}

	public function onDisable(): void
	{
		foreach ($this->arenas as $name => $arena)
			$arena->stop(true);
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool
	{
		$this->commands->onCommand($sender, $command, $label, $args);
		return true;
	}

	/**
	 * @param string $SWname
	 * @param float|int $x
	 * @param float|int $y
	 * @param float|int $z
	 * @param string $world
	 * @param bool $delete
	 * @param bool $all
	 * @return bool
	 * @throws \JsonException
	 */
	public function setSign(string $SWname, float|int $x, float|int $y, float|int $z, string $world, bool $delete = false, bool $all = true)
	{
		if ($delete) {
			if ($all) {
				$this->signcfg->setAll([]);
				$this->signcfg->save();
			} else {
				$this->signcfg->remove($SWname);
				$this->signcfg->save();
			}
			if ($this->loadSigns())
				return true;
			else
				return false;
		} else {
			$this->signcfg->set($SWname, []);
			$this->signcfg->setNested($SWname . ".arena", $SWname);
			$this->signcfg->setNested($SWname . ".x", $x);
			$this->signcfg->setNested($SWname . ".y", $y);
			$this->signcfg->setNested($SWname . ".z", $z);
			$this->signcfg->setNested($SWname . ".world", $world);
			$this->signcfg->save();
			if ($this->loadSigns())
				return true;
			else
				return false;
		}
	}


	/**
	 * @param bool $all
	 * @param string $SWname
	 * @param int $players
	 * @param int $slot
	 * @param string $state
	 */
	public function refreshSigns(bool $all = true, string $SWname = '', int $players = 0, int $slot = 0, string $state = '§fTap to join')
	{
		if (!$all) {
			$ex = explode(':', array_search($SWname, $this->signs));
			if (count($ex) == 4) {
				$this->getServer()->getWorldManager()->loadWorld($ex[3]);
				if ($this->getServer()->getWorldManager()->getWorldByName($ex[3]) != null) {
					$tile = $this->getServer()->getWorldManager()->getWorldByName($ex[3])->getTile(new Vector3($ex[0], $ex[1], $ex[2]));
					assert($tile instanceof Sign);
					$text = $tile->getText()->getLines();
					$tile->setText(new SignText([$text[0], $text[1], TextFormat::GREEN . $players . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $slot, $state]));
					$tile->getPosition()->getWorld()->setBlock($tile->getPosition(), $tile->getBlock());
				}
			}
		} else {
			foreach ($this->signs as $key => $val) {
				$ex = explode(':', $key);
				$this->getServer()->getWorldManager()->loadWorld($ex[3]);
				if ($this->getServer()->getWorldManager()->getWorldByName($ex[3]) instanceof World) {
					$tile = $this->getServer()->getWorldManager()->getWorldByName($ex[3])->getTile(new Vector3($ex[0], $ex[1], $ex[2]));
					assert($tile instanceof Sign);
					$text = $tile->getText()->getLines();
					$tile->setText(new SignText([$text[0], $text[1], TextFormat::GREEN . $this->arenas[$val]->getSlot(true) . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $this->arenas[$val]->getSlot(), $text[3]]));
					$tile->getPosition()->getWorld()->setBlock($tile->getPosition(), $tile->getBlock());
				}
			}
		}
	}


	/**
	 * @param string $playerName
	 * @return bool
	 */
	public function inArena($playerName = '')
	{
		foreach ($this->arenas as $a) {
			if ($a->inArena($playerName)) {
				return true;
			}
		}
		return false;
	}


	/**
	 * @return array
	 */
	public function getChestContents()
	{
		$items = array(
			//ARMOR
			'armor' => array(
				array(
					ItemIds::LEATHER_CAP,
					ItemIds::LEATHER_TUNIC,
					ItemIds::LEATHER_PANTS,
					ItemIds::LEATHER_BOOTS
				),
				array(
					ItemIds::GOLD_HELMET,
					ItemIds::GOLD_CHESTPLATE,
					ItemIds::GOLD_LEGGINGS,
					ItemIds::GOLD_BOOTS
				),
				array(
					ItemIds::CHAIN_HELMET,
					ItemIds::CHAIN_CHESTPLATE,
					ItemIds::CHAIN_LEGGINGS,
					ItemIds::CHAIN_BOOTS
				),
				array(
					ItemIds::IRON_HELMET,
					ItemIds::IRON_CHESTPLATE,
					ItemIds::IRON_LEGGINGS,
					ItemIds::IRON_BOOTS
				),
				array(
					ItemIds::DIAMOND_HELMET,
					ItemIds::DIAMOND_CHESTPLATE,
					ItemIds::DIAMOND_LEGGINGS,
					ItemIds::DIAMOND_BOOTS
				)
			),

			//WEAPONS
			'weapon' => array(
				array(
					ItemIds::WOODEN_SWORD,
					ItemIds::WOODEN_AXE,
				),
				array(
					ItemIds::GOLD_SWORD,
					ItemIds::GOLD_AXE
				),
				array(
					ItemIds::STONE_SWORD,
					ItemIds::STONE_AXE
				),
				array(
					ItemIds::IRON_SWORD,
					ItemIds::IRON_AXE
				),
				array(
					ItemIds::DIAMOND_SWORD,
					ItemIds::DIAMOND_AXE
				)
			),

			//FOOD
			'food' => array(
				array(
					ItemIds::RAW_PORKCHOP,
					ItemIds::RAW_CHICKEN,
					ItemIds::MELON_SLICE,
					ItemIds::COOKIE
				),
				array(
					ItemIds::RAW_BEEF,
					ItemIds::CARROT
				),
				array(
					ItemIds::APPLE,
					ItemIds::GOLDEN_APPLE
				),
				array(
					ItemIds::BEETROOT_SOUP,
					ItemIds::BREAD,
					ItemIds::BAKED_POTATO
				),
				array(
					ItemIds::MUSHROOM_STEW,
					ItemIds::COOKED_CHICKEN
				),
				array(
					ItemIds::COOKED_PORKCHOP,
					ItemIds::STEAK,
					ItemIds::PUMPKIN_PIE
				),
			),

			//THROWABLE
			'throwable' => array(
				array(
					ItemIds::BOW,
					ItemIds::ARROW
				),
				array(
					ItemIds::SNOWBALL
				),
				array(
					ItemIds::EGG
				)
			),

			//BLOCKS
			'block' => array(
				ItemIds::STONE,
				ItemIds::WOODEN_PLANKS,
				ItemIds::COBBLESTONE,
				ItemIds::DIRT
			),

			//OTHER
			'other' => array(
				array(
					ItemIds::WOODEN_PICKAXE,
					ItemIds::GOLD_PICKAXE,
					ItemIds::STONE_PICKAXE,
					ItemIds::IRON_PICKAXE,
					ItemIds::DIAMOND_PICKAXE
				),
				array(
					ItemIds::STICK,
					ItemIds::STRING
				)
			)
		);

		$templates = [];
		for ($i = 0; $i < 10; $i++) {

			$armorq = mt_rand(0, 1);
			$armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
			$armor1 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
			if ($armorq) {
				$armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
				$armor2 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
			} else {
				$armor2 = array(0, 1);
			}
			unset($armorq, $armortype);

			$weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
			$weapon = array($weapontype[mt_rand(0, (count($weapontype) - 1))], 1);
			unset($weapontype);

			$ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
			$food = array($ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5));
			unset($ftype);

			$add = mt_rand(0, 1);
			if ($add) {
				$tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
				if (count($tr) == 2) {
					$throwable1 = array($tr[1], mt_rand(10, 20));
					$throwable2 = array($tr[0], 1);
				} else {
					$throwable1 = array(0, 1);
					$throwable2 = array($tr[0], mt_rand(5, 10));
				}
				$other = array(0, 1);
			} else {
				$throwable1 = array(0, 1);
				$throwable2 = array(0, 1);
				$ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
				$other = array($ot[mt_rand(0, (count($ot) - 1))], 1);
			}
			unset($add, $tr, $ot);

			$block = array($items['block'][mt_rand(0, (count($items['block']) - 1))], 64);

			$contents = array(
				$armor1,
				$armor2,
				$weapon,
				$food,
				$throwable1,
				$throwable2,
				$block,
				$other
			);
			shuffle($contents);
			$fcontents = array(
				mt_rand(1, 2) => array_shift($contents),
				mt_rand(3, 5) => array_shift($contents),
				mt_rand(6, 10) => array_shift($contents),
				mt_rand(11, 15) => array_shift($contents),
				mt_rand(16, 17) => array_shift($contents),
				mt_rand(18, 20) => array_shift($contents),
				mt_rand(21, 25) => array_shift($contents),
				mt_rand(26, 27) => array_shift($contents),
			);
			$templates[] = $fcontents;

		}

		shuffle($templates);
		return $templates;
	}
}