<?php
/*
 * Copyright (c) 2017, whatwedo GmbH
 * All rights reserved
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace bomberman\logic;

use bomberman\components\field\Bomb;
use bomberman\components\field\Player;
use bomberman\components\Room;
use bomberman\io\Message;
use bomberman\logic\javascript\FieldJSLogic;
use bomberman\logic\javascript\GameJSLogic;
use bomberman\logic\javascript\PlayerJSLogic;
use bomberman\logic\javascript\RoomJSLogic;
use Ratchet\ConnectionInterface;

class PlayerLogic extends BaseLogic
{

    public static $name = 'player';

    const EVENT_MOVE = 'move';
    const EVENT_PLAN = 'plant';
    const EVENT_INIT = 'init';

    /**
     * @param \stdClass $data
     * @param ClientConnection $sender
     */
    protected function init($data, ClientConnection $sender)
    {
        $rooms = $this->context->getData();
        /** @var Room $room */
        $room = $rooms->findRoomBySender($sender->getUuid());
        if (!is_null($room) && $room->isStartable()) {
            $sender->send(json_encode(Message::fromCode(GameJSLogic::NAME, GameJSLogic::EVENT_STARTED, null)));
            echo ('init');
            $sender->send(json_encode(Message::fromCode(FieldJSLogic::NAME, FieldJSLogic::EVENT_UPDATE, $room->getField())));
        } else {
            $sender->send(json_encode(Message::fromCode(RoomJSLogic::NAME, RoomJSLogic::EVENT_LIST, $rooms->getValues())));
        }
    }

    /**
     * @param \stdClass $data
     * @param ClientConnection $sender
     */
    protected function move($data, ClientConnection $sender)
    {
        $player = $this->context->getData()->findPlayerBySender($sender->getUuid());
        if ($player instanceof Player && $player->canPlayerMove()) {
            $room = $this->context->getData()->findRoomBySender($sender->getUuid());
            $x = -1;
            $y = -1;
            switch ($data->direction) {
                case 'w':
                    $x = $player->getX() - 1;
                    $y = $player->getY();
                    break;
                case 'a';
                    $x = $player->getx();
                    $y = $player->getY() - 1;
                    break;
                case 's':
                    $x = $player->getX() + 1;
                    $y = $player->getY();
                    break;
                case 'd':
                    $x = $player->getX();
                    $y = $player->getY() + 1;
                    break;
            }
            $nextField = $room->getField()->getXY($x, $y);
            if (!is_null($nextField) && $nextField->canPlayerEnter()) {
                $room->getField()->moveTo($player, $x, $y);
                $player->setLastMoved();
                $this->context->sendToClients($room->getConnectedPlayers(),
                    Message::fromCode(FieldJSLogic::NAME, FieldJSLogic::EVENT_UPDATE, $room->getField())
                );
                $sender->send(json_encode(Message::fromCode(PlayerJSLogic::NAME, PlayerJSLogic::EVENT_NEXT_MOVEMENT, $player->getNextMovement())));
            }
        }
    }

    /**
     * @param \stdClass $data
     * @param ClientConnection $sender
     */
    protected function plant($data, ClientConnection $sender)
    {
        /** @var Room $room */
        $room = $this->context->getData()->findRoomBySender($sender->getUuid());
        if (!is_null($room)) {
            $player = $room->getField()->getFieldCollection()->findPlayerBySender($sender->getUuid());
            $room->getField()->addTo(new Bomb($player->getX(), $player->getY(), $player->getExplosionSpread()));
            $this->context->sendToClients($room->getConnectedPlayers(),
                Message::fromCode(FieldJSLogic::NAME, FieldJSLogic::EVENT_UPDATE, $room->getField())
            );
        }
    }

}