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
 * @Table(name="sheets")
 * @uses      Sheets
 */
class Sheets extends Model
{
    /**
     * @var int $id 
     * @Id()
     * @Column(name="id", type="integer")
     */
    private $id;

    /**
     * @var string $rank 
     * @Column(name="rank", type="string", length=128)
     * @Required()
     */
    private $rank;

    /**
     * @var int $num 
     * @Column(name="num", type="integer")
     * @Required()
     */
    private $num;

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
    public function setRank(string $value): self
    {
        $this->rank = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setNum(int $value): self
    {
        $this->num = $value;

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
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * @return int
     */
    public function getNum()
    {
        return $this->num;
    }

    /**
     * @return int
     */
    public function getPrice()
    {
        return $this->price;
    }

}
