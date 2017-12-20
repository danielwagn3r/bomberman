<?php
/*
 * This file is part of the bomberman project.
 *
 * @author Nicolo Singer tuxes3@outlook.com
 * @author Lukas Müller computer_bastler@hotmail.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bomberman\logic;

use bomberman\components\Room;
use bomberman\Context;
use bomberman\io\Config;
use bomberman\io\Message;
use bomberman\logic\javascript\MessageJSLogic;
use bomberman\logic\javascript\RoomJSLogic;
use Ratchet\ConnectionInterface;

/**
 * Class RoomLogic
 * @package bomberman\logic
 */
class RoomLogic extends BaseLogic
{

    const EVENT_CREATE = 'create';
    const EVENT_JOIN = 'join';
    const EVENT_LIST = 'getAll';
    const EVENT_CLOSE = 'close';
    const EVENT_LEAVE = 'leave';

    /**
     * @var string
     */
    public static $name = 'room';

    /**
     * @return array
     */
    public  function getEventsAllowedFromClient()
    {
        return [
            self::EVENT_CREATE,
            self::EVENT_JOIN,
            self::EVENT_LEAVE,
        ];
    }

    /**
     * RoomLogic constructor.
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * @param \stdClass $data
     * @param ClientConnection $sender
     */
    protected function create($data, ClientConnection $sender)
    {
        $uniqueId = $this->context->getData()->getFreeUniqueId();
        $roomSize = $data->maxPlayers;
        $maxRoomsPerPlayer = Config::get(Config::MAX_ROOMS_PER_PLAYER);
        $roomSize = intval($roomSize);
        if ($roomSize > 10) {
            $sender->send(json_encode(Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_WARNING, 'Cannot create room with more than 10 players.')));
        } elseif ($roomSize < 1) {
            $sender->send(json_encode(Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_WARNING, 'Cannot create room with less than 1 players.')));
        }
        elseif ($this->context->getData()->findByCreatedBy($sender->getUuid())->count() >= $maxRoomsPerPlayer) {
            $sender->send(json_encode(Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_WARNING, sprintf('You cannot create more than %s rooms', $maxRoomsPerPlayer))));
        } else {
            $room = new Room($roomSize, $uniqueId, $data->name, $sender->getUuid());
            $this->context->getData()->add($room);
            $this->sendRoomsToAll();
        }
    }

    /**
     * @param \stdClass $data
     * @param ClientConnection $sender
     */
    protected function close($data, $sender)
    {
        /** @var Room $room */
        $room = $data->room;
        if ($data->inactivity) {
            $this->context->sendToClients(
                array_merge($room->getConnectedPlayers(), [$room->getCreatedBy()]),
                Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_INFO, 'Room closed due to inactivity.')
            );
        }
        $this->context->getData()->removeUniqueId($data->room->getUniqueId());
        $this->sendRoomsToAll();
    }

    /**
     * sends room changes
     */
    protected function sendRoomsToAll()
    {
        $this->context->sendToClients(Context::SEND_ALL, Message::fromCode(RoomJSLogic::NAME, RoomJSLogic::EVENT_LIST, $this->context->getData()->getValues()));
    }

    /**
     * @param \stdClass $data
     * @param ClientConnection $sender
     */
    protected function join($data, ClientConnection $sender)
    {
        $room = ($this->context->getData()->findRoomBySender($sender->getUuid()));
        if (!is_null($room)) {
            if ($room->getUniqueId() == $data->uniqueId) {
                $return = Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_INFO, 'You already are in this room');
            } else {
                $return = Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_INFO, 'You can only be in one room');
            }
        } else {
            $room = $this->context->getData()->findRoomByUniqueId($data->uniqueId);
            $return = null;
            if (is_null($room)) {
                $return = Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_WARNING, sprintf('Room (%s) not existing.', $data->uniqueId));
            } else {
                $result = $room->addPlayer($sender->getUuid());
                if (is_string($result)) {
                    $return = Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_WARNING, $result);
                } elseif ($room->isStartable()) {
                    $this->context->send(Message::fromCode(FieldLogic::$name, FieldLogic::EVENT_START, $data), $sender);
                    $this->sendRoomsToAll();
                } else {
                    $return = Message::fromCode(MessageJSLogic::NAME, MessageJSLogic::EVENT_INFO, 'Waiting for players.');
                    $this->sendRoomsToAll();
                }
            }
        }

        if (!is_null($return)) {
            $sender->send(json_encode($return));
        }
    }

    /**
     * @param \stdClass $data
     * @param ClientConnection $sender
     */
    protected function leave($data, ClientConnection $sender)
    {
        $room = $this->context->getData()->findRoomBySender($sender->getUuid());
        if (!is_null($room)) {
            $room->removePlayer($sender->getUuid());
            $this->sendRoomsToAll();
        }
    }

    /**
     * @param ClientConnection $sender
     */
    protected function getAll($data, ClientConnection $sender)
    {
        $sender->send(json_encode(Message::fromCode(RoomJSLogic::NAME, RoomJSLogic::EVENT_LIST, $this->context->getData()->getValues())));
    }

}
