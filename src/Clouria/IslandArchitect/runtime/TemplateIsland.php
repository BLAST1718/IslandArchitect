<?php

/*
		
		  _____     _                 _          
		  \_   \___| | __ _ _ __   __| |         
		   / /\/ __| |/ _` | '_ \ / _` |         
		/\/ /_ \__ \ | (_| | | | | (_| |         
		\____/ |___/_|\__,_|_| |_|\__,_|         
		                                         
		   _            _     _ _            _   
		  /_\  _ __ ___| |__ (_) |_ ___  ___| |_ 
		 //_\\| '__/ __| '_ \| | __/ _ \/ __| __|
		/  _  \ | | (__| | | | | ||  __/ (__| |_ 
		\_/ \_/_|  \___|_| |_|_|\__\___|\___|\__|
		                                         
		@ClouriaNetwork | Apache License 2.0
														*/

declare(strict_types=1);
namespace Clouria\IslandArchitect\runtime;

use pocketmine\{
	math\Vector3,
	math\AxisAlignedBB as BB,
	level\Level,
	level\format\Chunk,
	block\Block,
	item\Item,
	utils\Random
};

use function array_push;
use function implode;
use function explode;
use function asort;
use function in_array;
use function json_encode;
use function json_decode;
use function array_rand;
use function array_search;
use function array_values;

use const SORT_NUMERIC;

class TemplateIsland {

	public function __construct(string $name) {
		$this->name = $name;
	}

	/**
	 * @var string
	 */
	protected $name;

	public function getName() : string {
		return $this->name;
	}

	/**
	 * @var Vector3|null
	 */
	protected $startcoord = null;

	public function getStartCoord() : ?Vector3 {
		return $this->startcoord;
	}

	public function setStartCoord(Vector3 $pos) : void {
		$this->startcoord = $pos->asVector3();
		$this->changed = true;
	}

	/**
	 * @var Vector3|null
	 */
	protected $endcoord = null;

	public function getEndCoord() : ?Vector3 {
		return $this->endcoord;
	}

	public function setEndCoord(Vector3 $pos) : void {
		$this->endcoord = $pos->asVector3();
		$this->changed = true;
	}

	/**
	 * @var string|null
	 */
	protected $level = null;
	
	public function getLevel() : ?string {
		return $this->level;
	}

	public function setLevel(Level $level) : void {
		$this->level = $level->getFolderName();
		$this->changed = true;
	}

	/**
	 * @var RandomGeneration[]
	 */
	protected $randoms = [];

	/**
	 * @return RandomGeneration[]
	 */
	public function getRandoms() : array {
		return $this->randoms;
	}

	public function getRandomById(int $id) : ?RandomGeneration {
		return $this->randoms[$id] ?? null;
	}

	public function removeRandomById(int $id) : bool {
		if (!isset($this->randoms[$id])) return false;
		unset($this->randoms[$id]);
		$this->changed = true;
		return true;
	}

	/**
	 * @param RandomGeneration $random
	 * @return int The random generation regex ID
	 */
	public function addRandom(RandomGeneration $random) : int {
		$this->changed = true;
		return array_push($this->randoms, $random) - 1;
	}

	/**
	 * @var array<string, int>
	 */
	private $random_blocks = [];

	/**
	 * @see TemplateIsland::getRandomByVector3()
	 */
	public function setBlockRandom(Vector3 $block, int $id) : bool {
		if (!isset($this->getRandoms()[$id])) return false;
		$this->random_blocks[$block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ()] = $id;
		$this->changed = true;
		return true;
	}

	/**
	 * @see TemplateIsland::setBlockRandom()
	 */
	public function getRandomByVector3(Vector3 $block) : ?int {
		return $this->random_blocks[$block->getFloorX() . ':' . $block->getFloorY() . ':' . $block->getFloorZ()] ?? null;
	}

	/**
	 * @var array<int, int[]>
	 */
	protected $symbolic = [];

	protected const SYMBOLICS = [
		[Item::PURPLE_GLAZED_TERRACOTTA],
		[Item::WHITE_GLAZED_TERRACOTTA],
		[Item::ORANGE_GLAZED_TERRACOTTA],
		[Item::MAGENTA_GLAZED_TERRACOTTA],
		[Item::LIGHT_BLUE_GLAZED_TERRACOTTA],
		[Item::YELLOW_GLAZED_TERRACOTTA],
		[Item::LIME_GLAZED_TERRACOTTA],
		[Item::PINK_GLAZED_TERRACOTTA],
		[Item::GRAY_GLAZED_TERRACOTTA],
		[Item::SILVER_GLAZED_TERRACOTTA],
		[Item::CYAN_GLAZED_TERRACOTTA]
	];

	private $unused_symbolics = self::SYMBOLICS;

	/**
	 * @todo Allow user to customize symbolic in panel(inventory)
	 */
	public function getRandomSymbolic(int $regex) : Item {
		if (!isset($this->symbolic[$regex])) {
			if (empty($this->unused_symbolics)) $this->unused_symbolics = self::SYMBOLICS;
			$chosenone = array_rand($this->unused_symbolics);
			$this->symbolic[$regex] = $this->unused_symbolics[$chosenone];
			unset($this->unused_symbolics[$chosenone]);
		}
		return Item::get($this->symbolic[$regex][0], $this->symbolic[$regex][1] ?? 0);
	}


	public function setRandomSymbolic(int $regex, int $id, int $meta = 0) : void {
		if (isset($this->symbolic[$regex])) $this->unused_symbolics[] = $this->symbolic[$regex];
		$this->symbolic[$regex] = [$id, $meta];
		$this->changed = true;
	}

	/**
	 * @var array<string, string>
	 */
	protected $structure;

	public function getChunkBlocks(int $cx, int $cz, Random $random) : array {
		for ($z=$cz << 4; $z < ($cz + 1) << 4; $z++) for ($x=$cx << 4; $x < ($cx + 1) << 4; $x++) for ($y=0; $y <= Level::Y_MAX; $y++) {
			$block = $this->structure[$x . ':' . $y . ':' . $z] ?? null;
			if (!isset($block)) continue;
			$block = explode(':', $block);
			if ((int)$block[0] === 0) $blocks[$x][$z][$y] = [(int)($block[1] ?? Item::AIR) & 0xff, (int)($block[2] ?? 0) & 0xff];
			if ((int)$block[0] === 1) $blocks[$x][$z][$y] = $this->randomElementArray((int)$block[1]);
		}
	}

	public const VERSION = 1.0;

	public function save() : string {
		$data['level'] = $this->getLevel();
		$data['startcoord'] = $this->getStartCoord()->floor();
		$data['endcoord'] = $this->getEndCoord()->floor();
		$data['random_blocks'] = $this->random_blocks;
		foreach ($this->symbolic as $regexid => $symbolic) {
			$symbolic = $symbolic[0] . (isset($symbolic[1]) ? ':' . $symbolic[1] : '');
			$data['symbolic'][$regexid] = $symbolic;
		}
		foreach ($this->randoms as $regexid => $random) {
			$elements = $random->getAllElements();
			if (empty($elements)) $data['randoms'][$regexid] = ['blockid:meta' => 'chance'];
			else $data['randoms'][$regexid] = $elements;
		}

		return $this->encode($data);
	}

	/**
	 * @param mixed[] $chunks 
	 * @return string JSON encoded template island data
	 */
	public function export(array $chunks) : string {
		$sc = $this->getStartCoord();
		$ec = $this->getEndCoord();
		$xl = [$sc->getFloorX(), $ec->getFloorX()];
		$yl = [$sc->getFloorY(), $ec->getFloorY()];
		$zl = [$sc->getFloorZ(), $ec->getFloorZ()];
		asort($xl, SORT_NUMERIC);
		asort($yl, SORT_NUMERIC);
		asort($zl, SORT_NUMERIC);
		$xl = array_values($xl);
		$yl = array_values($yl);
		$zl = array_values($zl);

		$usedrandoms = [];
		foreach ($chunks[0] as $hash => $chunk) {
			$chunk = $chunks[1][$hash]::fastDeserialize($chunk);
			$chunksmap[$hash] = $chunk;
		}
		for ($x = $xl[0]; $x <= $xl[1]; $x++) for ($z = $zl[0]; $z <= $zl[1]; $z++) {
			$chunk = $chunksmap[Level::chunkHash($x >> 4, $z >> 4)] ?? null;
			if ($chunk === null) continue;
			$bx = $x - $xl[0];
			$bz = $z - $zl[0];
			for ($y = $yl[0]; $y <= $yl[1]; $y++) {
				if (($id = $chunk->getBlockId($x & 0x0f, $y & 0x0f, $z & 0x0f)) === Block::AIR) continue;
				$by = $y - $yl[0];
				$coord = $x . ':' . $y . ':' . $z;
				$bcoord = $bx . ':' . $by . ':' . $bz;
				if (isset($this->random_blocks[$coord])) {
					$id = $this->random_blocks[$coord];
					if (($r = $this->getRandomById($this->random_blocks[$coord])) === null) continue;
					if (!$r->isValid()) continue;
					if (($i = array_search($id, $usedrandoms, true)) === false) $id = array_push($usedrandoms, $id) - 1;
					else $id = $usedrandoms[$i];
					$data['structure'][$bcoord] = '1:' . $id;
				} else {
					$data['structure'][$bcoord] = '0:' . $id;
					$meta = $chunk->getBlockData($x & 0x0f, $y, $z & 0x0f);
					if ($meta !== Item::AIR) $data['structure'][$bcoord] .= $meta;
				}
			}
		}

		if (!empty($usedrandoms ?? [])) foreach ($this->randoms as $id => $random) if (in_array($id, $usedrandoms)) $data['randoms'][] = $random->getAllElements();

		return $this->encode($data ?? []);
	}

	protected function encode(array $data) : string {
		$data['version'] = self::VERSION;
		$data['name'] = $this->getName();

		return json_encode($data);
	}

	public static function load(string $data) : ?TemplateIsland {
		$data = json_decode($data, true);
		if ($data === false) return null;
		if (
			(int)($version = $data['version'] ?? -1) === -1 or
			((int)$version > self::VERSION) or
			!isset($data['name'])
		) return null;

		$self = new self($data['name']);
		if (isset($data['level'])) $self->level = $data['level'];
		if (isset($data['startcoord'])) {
			$coord = $data['startcoord'];
			$self->startcoord = new Vector3((int)$coord['x'], (int)$coord['y'], (int)$coord['z']);
		}
		if (isset($data['endcoord'])) {
			$coord = $data['endcoord'];
			$self->endcoord = new Vector3((int)$coord['x'], (int)$coord['y'], (int)$coord['z']);
		}
		if (isset($data['random_blocks'])) $self->random_blocks = $data['random_blocks'];
		if (isset($data['symbolic'])) {
			$unused_symbolics = self::SYMBOLICS;
			foreach ($data['symbolic'] as $regexid => $symbolic) {
				$symbolic = explode(':', $symbolic);
				$self->symbolic[$regexid] = [(int)$symbolic[0], (int)($symbolic[1] ?? 0)];
				if (($r = array_search([(int)$symbolic[0], (int)($symbolic[1] ?? 0)], $unused_symbolics, true)) !== false) unset($unused_symbolics[$r]);
			}
			$self->unused_symbolics = $unused_symbolics;
		}
		foreach ($data['randoms'] ?? [] as $regexdata) {
			$regex = new RandomGeneration;
			foreach ($regexdata as $element => $chance) {
				$element = explode(':', $element);
				$regex->increaseElementChance((int)$element[0], (int)($element[1] ?? 0), (int)$chance);
			}
			$self->randoms[] = $regex;
		}
		if (isset($data['structure'])) $self->structure = $data['structure'];
		return $self;
	}

	/**
	 * @var bool
	 */
	protected $changed = false;

	public function noMoreChanges() : void {
		$this->changed = false;
		foreach ($this->randoms as $r) $r->noMoreChanges();
	}

	public function hasChanges() : bool {
		if ($this->changed) return $this->changed;
		foreach ($this->randoms as $r) if ($r->hasChanges()) return true;
		return false;
	}

	public function readyToExport() : bool {
		return (
			isset($this->startcoord) and
			isset($this->endcoord) and
			isset($this->level)
		);
	}

}