<?php
namespace App\Models\Entity;

use Swoft\Db\Model;
use Swoft\Db\Bean\Annotation\Column;
use Swoft\Db\Bean\Annotation\Entity;
use Swoft\Db\Bean\Annotation\Id;
use Swoft\Db\Bean\Annotation\Required;
use Swoft\Db\Bean\Annotation\Table;
use Swoft\Db\Types;

/**
 * @Entity()
 * @Table(name="reservations")
 * @uses      Reservations
 */
class Reservations extends Model
{
    /**
     * @var int $id 
     * @Id()
     * @Column(name="id", type="integer")
     */
    private $id;

    /**
     * @var int $eventId 
     * @Column(name="event_id", type="integer")
     * @Required()
     */
    private $eventId;

    /**
     * @var int $sheetId 
     * @Column(name="sheet_id", type="integer")
     * @Required()
     */
    private $sheetId;

    /**
     * @var int $userId 
     * @Column(name="user_id", type="integer")
     * @Required()
     */
    private $userId;

    /**
     * @var string $reservedAt 
     * @Column(name="reserved_at", type="datetime")
     * @Required()
     */
    private $reservedAt;

    /**
     * @var string $canceledAt 
     * @Column(name="canceled_at", type="datetime")
     */
    private $canceledAt;

    /**
     * @param int $value
     * @return $this
     */
    public function setId(int $value)
    {
        $this->id = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setEventId(int $value): self
    {
        $this->eventId = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setSheetId(int $value): self
    {
        $this->sheetId = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setUserId(int $value): self
    {
        $this->userId = $value;

        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setReservedAt(string $value): self
    {
        $this->reservedAt = $value;

        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setCanceledAt(string $value): self
    {
        $this->canceledAt = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @return int
     */
    public function getSheetId()
    {
        return $this->sheetId;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return string
     */
    public function getReservedAt()
    {
        return $this->reservedAt;
    }

    /**
     * @return string
     */
    public function getCanceledAt()
    {
        return $this->canceledAt;
    }

}
