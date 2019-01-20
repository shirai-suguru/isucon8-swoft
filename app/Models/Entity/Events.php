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
 * @Table(name="events")
 * @uses      Events
 */
class Events extends Model
{
    /**
     * @var int $id 
     * @Id()
     * @Column(name="id", type="integer")
     */
    private $id;

    /**
     * @var string $title 
     * @Column(name="title", type="string", length=128)
     * @Required()
     */
    private $title;

    /**
     * @var int $publicFg 
     * @Column(name="public_fg", type="tinyint")
     * @Required()
     */
    private $publicFg;

    /**
     * @var int $closedFg 
     * @Column(name="closed_fg", type="tinyint")
     * @Required()
     */
    private $closedFg;

    /**
     * @var int $price 
     * @Column(name="price", type="integer")
     * @Required()
     */
    private $price;

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
     * @param string $value
     * @return $this
     */
    public function setTitle(string $value): self
    {
        $this->title = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setPublicFg(int $value): self
    {
        $this->publicFg = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setClosedFg(int $value): self
    {
        $this->closedFg = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setPrice(int $value): self
    {
        $this->price = $value;

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
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return int
     */
    public function getPublicFg()
    {
        return $this->publicFg;
    }

    /**
     * @return int
     */
    public function getClosedFg()
    {
        return $this->closedFg;
    }

    /**
     * @return int
     */
    public function getPrice()
    {
        return $this->price;
    }

}
