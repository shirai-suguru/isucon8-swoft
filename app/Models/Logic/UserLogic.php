<?php
namespace App\Models\Logic;

use Swoft\Db\Db;
use Swoft\Db\Query;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Inject;
use Swoft\Log\Log;
use Swoft\Core\Config;
use App\Models\Entity\Users;

/**
 *
 * @Bean()
 * @uses      UserLogic
 * @version   2018/1/17
 */
class UserLogic
{

    public function getLoginUser()
    {
        $user_id = session()->get('user_id');
        if (null === $user_id) {
            return false;
        }

        $user = Users::findById($user_id)->getResult()->toArray();
        $user['id'] = (int) $user['id'];
        return $user;
    }

    public function getLoginAdministrator()
    {
        $administrator_id = session()->get('administrator_id');
        if (null === $administrator_id) {
            return false;
        }

        $administrator = Db::query('SELECT id, nickname FROM administrators WHERE id = ?', [$administrator_id])->getResult()[0];
        $administrator['id'] = (int) $administrator['id'];
        return $administrator;
    }
}
