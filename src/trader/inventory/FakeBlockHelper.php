<?php

namespace trader\inventory;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\scheduler\Task;

use trader\GUITrader;

class FakeBlockHelper {

    private $trader;
    private $pos;
    private $inventory;
    private $player;
    private $target;

    public function __construct(GUITrader $trader, Vector3 $pos, TradeInventory $inventory, Player $player, Player $target) {
        $this->trader = $trader;
        $this->pos = $pos;
        $this->inventory = $inventory;
        $this->player = $player;
        $this->target = $target;
    }

    public function openFakeChest(): void {
        $player = $this->player;
        $x = $this->pos->getFloorX();
        $y = $this->pos->getFloorY();
        $z = $this->pos->getFloorZ();

        $block1 = Block::get(Block::CHEST, 2, new Position($x, $y, $z));
        $block2 = Block::get(Block::CHEST, 2, new Position($x + 1, $y, $z));
        $player->getLevel()->sendBlocks([$player], [$block1, $block2]);

        $nbt = new CompoundTag();
        $nbt->setString("id", "Chest");
        $nbt->setInt("x", $x);
        $nbt->setInt("y", $y);
        $nbt->setInt("z", $z);
        $nbt->setInt("pairx", $x + 1);
        $nbt->setInt("pairz", $z);
        $nbt->setString("CustomName", "あなた                  " . $this->target->getName());

        $stream = new NetworkLittleEndianNBTStream();
        $pk = new BlockActorDataPacket();
        $pk->x = $x;
        $pk->y = $y;
        $pk->z = $z;
        $pk->namedtag = $stream->write($nbt);
        $player->dataPacket($pk);
        
        $nbt->setInt("x", $x + 1);
        $nbt->setInt("pairx", $x);
        $pk->x = $x + 1;
        $pk->namedtag = $stream->write($nbt);
        $player->dataPacket($pk);

        $this->trader->getScheduler()->scheduleDelayedTask(new class($this->inventory, $player) extends Task {

            private $inventory;
            private $player;

            public function __construct(TradeInventory $inventory, Player $player) {
                $this->inventory = $inventory;
                $this->player = $player;
            }

            public function onRun(int $currentTick) {
                $holder = $this->inventory->getHolder();

                $pk = new ContainerOpenPacket();
                $pk->windowId = $this->player->getWindowId($this->inventory);
                $pk->type = $this->inventory->getNetworkType();
                $pk->entityUniqueId = -1;
                $pk->x = $holder->getFloorX();
                $pk->y = $holder->getFloorY();
                $pk->z = $holder->getFloorZ();
                $this->player->dataPacket($pk);
        
                $this->inventory->sendContents($this->player);
            }

        }, 20);
    }

    public function closeFakeChest(): void {
		$pk = new ContainerClosePacket();
        $pk->windowId = $this->player->getWindowId($this->inventory);
        $this->player->dataPacket($pk);

        $this->trader->getScheduler()->scheduleDelayedTask(new class($this->player, $this->pos) extends Task {

            private $player;
            private $holder;

            public function __construct(Player $player, Vector3 $holder) {
                $this->player = $player;
                $this->holder = $holder;
            }

            public function onRun(int $currentTick) {
                if (!$this->player->isOnline()) {
                    return;
                }
                $this->player->getLevel()->sendBlocks([$this->player], [$this->holder, $this->holder->add(1, 0, 0)]);
            }

        }, 40);
    }
}