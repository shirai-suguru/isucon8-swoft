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
 * @Table(name="users")
 * @uses      Users
 */
class Users extends Model
{
    /**
     * @var int $id 
     * @Id()
     * @Column(name="id", type="integer")
     */
    private $id;

    /**
     * @var string $nickname 
     * @Column(name="nickname", type="string", length=128)
     * @Required()
     */
    private $nickname;

    /**
     * @var string $loginName 
     * @Column(name="login_name", type="string", length=128)
     * @Required()
     */
    private $loginName;

    /**
     * @var string $passHash 
     * @Column(name="pass_hash", type="string", length=128)
     * @Required()
     */
    private $passHash;

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
    public function setNickname(string $value): self
    {
        $this->nickname = $value;

        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setLoginName(string $value): self
    {
        $this->loginName = $value;

        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setPassHash(string $value): self
    {
        $this->passHash = $value;

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
    public function getNickname()
    {
        return $this->nickname;
    }

    /**
     * @return string
     */
    public function getLoginName()
    {
        return $this->loginName;
    }

    /**
     * @return string
     */
    public function getPassHash()
    {
        return $this->passHash;
    }

}
