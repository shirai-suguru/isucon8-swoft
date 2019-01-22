<?php
namespace App\Controllers;

use Swoft\App;
use Swoft\Http\Server\Bean\Annotation\Controller;
use Swoft\Http\Message\Bean\Annotation\Middleware;
use Swoft\Http\Message\Bean\Annotation\Middlewares;
use Swoft\Http\Server\Bean\Annotation\RequestMapping;
use Swoft\Log\Log;
use Swoft\View\Bean\Annotation\View;
use Swoft\Contract\Arrayable;
use Swoft\Http\Server\Exception\BadRequestException;
use Swoft\Http\Message\Server\Response;
use Swoft\Http\Message\Server\Request;
use Swoft\Http\Server\Bean\Annotation\RequestMethod;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Inject;
use Swoft\Core\Config;
use App\Models\Entity\Administrators;
use App\Models\Entity\Events;
use App\Models\Entity\Reservations;
use App\Models\Entity\Sheets;
use App\Models\Entity\Users;
use App\Middlewares\FillinUserMiddleware;
use App\Middlewares\LoginRequiredMiddleware;
use App\Middlewares\FillinAdminMiddleware;
use App\Middlewares\AdminLoginRequiredMiddleware;
use Swoft\Db\Db;
use App\Models\Logic\UserLogic;
use Swoft\Helper\JsonHelper;
use Swoole\Coroutine\Channel;
use Swoft\Core\Coroutine;

/**
 * Class IndexController
 * @Controller()
 */
class IndexController
{
    /**
     *
     * @Inject()
     * @var UserLogic
     */
    private $userLogic;

    private function adminLoginRequired(Response $response)
    {
        $administrator = $this->userLogic->getLoginAdministrator();

        if (!$administrator) {
            $response = $handler->handle($request);
            $swooleResponse = $response->getSwooleResponse();
            $swooleResponse->status(401);
            $swooleResponse->header('Content-Type', 'application/json');
            $swooleResponse->write(JsonHelper::encode(['error' => 'admin_login_required']));
            return $swooleResponse->end();
        }

        return false;
    }

    private function loginRequired(Response $response)
    {
        $user = $this->userLogic->getLoginUser();

        if (!$user) {
            $swooleResponse = $response->getSwooleResponse();
            $swooleResponse->status(401);
            $swooleResponse->header('Content-Type', 'application/json');
            $swooleResponse->write(JsonHelper::encode(['error' => 'login_required']));
            return $swooleResponse->end();
        }
        return false;
    }

    private function validate_rank($rank)
    {
        return (int) Db::query('SELECT COUNT(*) AS `CNT` FROM sheets WHERE `rank` = ?', [$rank])->getResult()[0]['CNT'] ?? 0;
    }

    private function sanitize_event(array $event): array
    {
        unset($event['price']);
        unset($event['public']);
        unset($event['closed']);
    
        return $event;
    }
    
    private function res_error(Response $response, string $error = 'unknown', int $status = 500): Response
    {
        return $response->json(['error' => $error], $status);
    }

    private function get_events(?callable $where = null): array
    {
        if (null === $where) {
            $where = function (array $event) {
                return $event['public_fg'];
            };
        }
    
        // Db::beginTransaction();
        try {
            $events = [];
            $event_ids = array_map(function (array $event) {
                return $event['id'];
            }, array_filter(Db::query('SELECT * FROM events ORDER BY id ASC')->getResult(), $where));
    
            foreach ($event_ids as $event_id) {
                $event = $this->get_event($event_id);
        
                foreach (array_keys($event['sheets']) as $rank) {
                    unset($event['sheets'][$rank]['detail']);
                }
        
                array_push($events, $event);
            }
        } catch (\Throwable $e) {
            // Db::rollback();
        }
        // Db::commit();

        return $events;
    }
    private function get_event(int $event_id, ?int $login_user_id = null): array
    {
        $event = Db::query('SELECT * FROM events WHERE id = ?', [$event_id])->getResult()[0] ?? null;


        if (!$event) {
            return [];
        }
    
        $event['id'] = (int) $event['id'];
    
        // zero fill
        $event['total'] = 0;
        $event['remains'] = 0;
    
        foreach (['S', 'A', 'B', 'C'] as $rank) {
            $event['sheets'][$rank]['total'] = 0;
            $event['sheets'][$rank]['remains'] = 0;
        }
    
        $sheets = Db::query('SELECT * FROM sheets ORDER BY `rank`, num')->getResult();

        foreach ($sheets as $sheet) {
            $event['sheets'][$sheet['rank']]['price'] = $event['sheet'][$sheet['rank']]['price'] ?? $event['price'] + $sheet['price'];
    
            ++$event['total'];
            ++$event['sheets'][$sheet['rank']]['total'];
    
            $reservation =  Db::query('SELECT * FROM reservations WHERE event_id = ? AND sheet_id = ? AND canceled_at IS NULL GROUP BY event_id, sheet_id HAVING reserved_at = MIN(reserved_at)', [$event['id'], $sheet['id']])->getResult()[0] ?? null;
            if ($reservation) {
                $sheet['mine'] = $login_user_id && $reservation['user_id'] == $login_user_id;
                $sheet['reserved'] = true;
                $sheet['reserved_at'] = (new \DateTime("{$reservation['reserved_at']}", new \DateTimeZone('UTC')))->getTimestamp();
            } else {
                ++$event['remains'];
                ++$event['sheets'][$sheet['rank']]['remains'];
            }

            $sheet['num'] = $sheet['num'];
            $rank = $sheet['rank'];
            unset($sheet['id']);
            unset($sheet['price']);
            unset($sheet['rank']);
    
            if (false === isset($event['sheets'][$rank]['detail'])) {
                $event['sheets'][$rank]['detail'] = [];
            }
            array_push($event['sheets'][$rank]['detail'], $sheet);
        }
    
        $event['public'] = $event['public_fg'] ? true : false;
        $event['closed'] = $event['closed_fg'] ? true : false;
    
        unset($event['public_fg']);
        unset($event['closed_fg']);
    
        return $event;
    }
    
    /**
     * @RequestMapping("/")
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $this->userLogic->getLoginUser();

        $headers = $request->getHeaders();
        $events = array_map(function (array $event) {
            return $this->sanitize_event($event);
        }, $this->get_events());
            
        $baseUrl =  ($headers['x-forwarded-proto'][0] ?? 'http') . '://' . $headers['host'][0];
        return view('index', [
            'events' => $events,
            'base_url' => $baseUrl,
            'user' => $user,
        ]);
    }

    /**
     * @RequestMapping("/initialize")
     * @param Response $response
     * @return Response
     */
    public function initialize(Response $response): Response
    {
        exec('./k8s/init.sh');
        return $response->withStatus(204);
    }

    /**
     * @RequestMapping(route="/api/users", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function apiUsers(Request $request, Response $response): Response
    {
        $nickname = $request->getBodyParams()['nickname'];
        $login_name = $request->getBodyParams()['login_name'];
        $password = $request->getBodyParams()['password'];
    
        $user_id = null;
    
    
        $duplicated = Db::query('SELECT * FROM users WHERE login_name = ?', [$login_name])->getResult();
        if ($duplicated) {
            // Db::rollback();

            return $this->res_error($response, 'duplicated', 409);
        }

        Db::beginTransaction();
        try {
                Db::query('INSERT INTO users (login_name, pass_hash, nickname) VALUES (?, SHA2(?, 256), ?)', [$login_name, $password, $nickname])->getResult();
            $user_id = Db::query('SELECT last_insert_id() as user_id')->getResult()[0]['user_id'];
            Db::commit();
        } catch (\Throwable $throwable) {
            Db::rollback();
    
            return $this->res_error($response);
        }
    
        return $response->json([
            'id' => (int)$user_id,
            'nickname' => $nickname,
        ], 201, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/api/users/{id}", method={RequestMethod::GET})
     * @param Request $request
     * @param Response $response
     * @param int    $id
     * @return Response
     */
    public function apiUsersById(int $id, Request $request, Response $response): Response
    {
        $res = $this->loginRequired($response);
        if ($res) {
            return $res;
        }

        $user = Db::query('SELECT id, nickname FROM users WHERE id = ?', [$id])->getResult()[0];
        $user['id'] = (int) $user['id'];
        if (!$user || $user['id'] !== $this->userLogic->getLoginUser()['id']) {
            return $this->res_error($response, 'forbidden', 403);
        }
    
        $recent_reservations = function () use ($user) {
            $recent_reservations = [];
    
            $rows = Db::query('SELECT r.*, s.rank AS sheet_rank, s.num AS sheet_num FROM reservations r INNER JOIN sheets s ON s.id = r.sheet_id WHERE r.user_id = ? ORDER BY IFNULL(r.canceled_at, r.reserved_at) DESC LIMIT 5', [$user['id']])->getResult();
            foreach ($rows as $row) {
                $event = $this->get_event($row['event_id']);
                $price = $event['sheets'][$row['sheet_rank']]['price'];
                unset($event['sheets']);
                unset($event['total']);
                unset($event['remains']);
    
                $reservation = [
                    'id' => $row['id'],
                    'event' => $event,
                    'sheet_rank' => $row['sheet_rank'],
                    'sheet_num' => $row['sheet_num'],
                    'price' => $price,
                    'reserved_at' => (new \DateTime("{$row['reserved_at']}", new \DateTimeZone('UTC')))->getTimestamp(),
                ];
    
                if ($row['canceled_at']) {
                    $reservation['canceled_at'] = (new \DateTime("{$row['canceled_at']}", new \DateTimeZone('UTC')))->getTimestamp();
                }
    
                array_push($recent_reservations, $reservation);
            }
    
            return $recent_reservations;
        };
    
        $user['recent_reservations'] = $recent_reservations($this);
        $user['total_price'] = Db::query('SELECT IFNULL(SUM(e.price + s.price), 0) AS `total_price` FROM reservations r INNER JOIN sheets s ON s.id = r.sheet_id INNER JOIN events e ON e.id = r.event_id WHERE r.user_id = ? AND r.canceled_at IS NULL', [$user['id']])->getResult()[0]['total_price'];
    
        $recent_events = function () use ($user) {
            $recent_events = [];
    
            $rows = Db::query('SELECT event_id FROM reservations WHERE user_id = ? GROUP BY event_id ORDER BY MAX(IFNULL(canceled_at, reserved_at)) DESC LIMIT 5', [$user['id']])->getResult();
            foreach ($rows as $row) {
                $event = $this->get_event($row['event_id']);
                foreach (array_keys($event['sheets']) as $rank) {
                    unset($event['sheets'][$rank]['detail']);
                }
                array_push($recent_events, $event);
            }
    
            return $recent_events;
        };
    
        $user['recent_events'] = $recent_events($this);
    
        return $response->json($user, 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/api/actions/login", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response): Response
    {
        $login_name = $request->getBodyParams()['login_name'];
        $password = $request->getBodyParams()['password'];
    
        $user = Db::query('SELECT * FROM users WHERE login_name = ?', [$login_name])->getResult();
        $pass_hash = Db::query('SELECT SHA2(?, 256) AS `hash`', [$password])->getResult()[0]['hash'];
    
        if (!$user || $pass_hash != $user[0]['pass_hash']) {
            return $this->res_error($response, 'authentication_failed', 401);
        }
    
        session()->put('user_id', (int)$user[0]['id']);
    
        $user = $this->userLogic->getLoginUser();
    
        return $response->json($user, 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/api/actions/logout", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function logout(Request $request, Response $response): Response
    {
        $res = $this->loginRequired($response);
        if ($res) {
            return $res;
        }

        session()->flush();

        return $response->withStatus(204);
    }

    /**
     * @RequestMapping(route="/api/events", method={RequestMethod::GET})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function apiGetEvents(Request $request, Response $response): Response
    {
        $events = array_map(function (array $event) {
            return $this->sanitize_event($event);
        }, $this->get_events());
    
        return $response->json($events, 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/api/events/{id}", method={RequestMethod::GET})
     * @param Request $request
     * @param Response $response
     * @param int    $id
     * @return Response
     */
    public function apiGetEventsById(int $id, Request $request, Response $response): Response
    {
        $event_id = $id;
        $user = $this->userLogic->getLoginUser();
        $event = $this->get_event($event_id, $user['id']);
    
        if (empty($event) || !$event['public']) {
            return $this->res_error($response, 'not_found', 404);
        }
    
        $event = $this->sanitize_event($event);
    
        return $response->json($event, 200, JSON_NUMERIC_CHECK);
    }


    /**
     * @RequestMapping(route="/api/events/{id}/actions/reserve", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @param int    $id
     * @return Response
     */
    public function apiEventsReserveById(int $id, Request $request, Response $response): Response
    {
        $res = $this->loginRequired($response);

        if ($res) {
            return $res;
        }

        $event_id = $id;
        $rank = $request->getBodyParams()['sheet_rank'];
    
        $user = $this->userLogic->getLoginUser();
        $event = $this->get_event($event_id, $user['id']);
    
        if (empty($event) || !$event['public']) {
            return $this->res_error($response, 'invalid_event', 404);
        }
    
        if (!$this->validate_rank($rank)) {
            return $this->res_error($response, 'invalid_rank', 400);
        }
    
        $sheet = null;
        $reservation_id = null;
        while (true) {
            $sheet = Db::query('SELECT * FROM sheets WHERE id NOT IN (SELECT sheet_id FROM reservations WHERE event_id = ? AND canceled_at IS NULL FOR UPDATE) AND `rank` = ? ORDER BY RAND() LIMIT 1', [$event['id'], $rank])->getResult();
            if (!$sheet) {
                return $this->res_error($response, 'sold_out', 409);
            }
    
            Db::beginTransaction();
            try {
                Db::query('INSERT INTO reservations (event_id, sheet_id, user_id, reserved_at) VALUES (?, ?, ?, ?)', [$event['id'], $sheet[0]['id'], $user['id'], (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')])->getResult();
                $result = Db::query('SELECT last_insert_id() AS `id`')->getResult();
                $reservation_id = $result[0]['id'];
    
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                continue;
            }
    
            break;
        }

        return $response->json([
            'id' => $reservation_id,
            'sheet_rank' => $rank,
            'sheet_num' => $sheet[0]['num'],
        ], 202, JSON_NUMERIC_CHECK);
    
    }

    /**
     * @RequestMapping(route="/api/events/{id}/sheets/{ranks}/{num}/reservation", method={RequestMethod::DELETE})
     * @param Request $request
     * @param Response $response
     * @param int    $id
     * @param string    $ranks
     * @param int    $num
     * @return Response
     */
    public function deletEventByIdRankNum(int $id, string $ranks, int $num, Request $request, Response $response): Response
    {
        $res = $this->loginRequired($response);
        if ($res) {
            return $res;
        }

        $event_id = $id;
        $rank = $ranks;
        $num = $num;
    
        $user = $this->userLogic->getLoginUser();
        $event = $this->get_event($event_id, $user['id']);
    
        if (empty($event) || !$event['public']) {
            return $this->res_error($response, 'invalid_event', 404);
        }
    
        if (!$this->validate_rank($rank)) {
            return $this->res_error($response, 'invalid_rank', 404);
        }
    
        $sheet = Db::query('SELECT * FROM sheets WHERE `rank` = ? AND num = ?', [$rank, $num])->getResult();
        if (!$sheet) {
            return $this->res_error($response, 'invalid_sheet', 404);
        }

        $reservation = Db::query('SELECT * FROM reservations WHERE event_id = ? AND sheet_id = ? AND canceled_at IS NULL GROUP BY event_id HAVING reserved_at = MIN(reserved_at) FOR UPDATE', [$event['id'], $sheet[0]['id']])->getResult();
        if (!$reservation) {
            // Db::rollback();

            return $this->res_error($response, 'not_reserved', 400);
        }

        if ($reservation[0]['user_id'] != $user['id']) {
            // Db::rollback();

            return $this->res_error($response, 'not_permitted', 403);
        }

        Db::beginTransaction();
        try {
        
            Db::query('UPDATE reservations SET canceled_at = ? WHERE id = ?', [(new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'), $reservation[0]['id']])->getResult();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
    
            return $this->res_error($response);
        }
    
        return $response->withStatus(204);
    }


    /**
     * @RequestMapping("/admin")
     * @Middleware(FillinAdminMiddleware::class)
     * @param Request $request
     * @return Response
     */
    public function admin(Request $request): Response
    {
        $administrator = $this->userLogic->getLoginAdministrator();

        $headers = $request->getHeaders();
        $events = $this->get_events(function ($event) { return $event; });

        $baseUrl =  ($headers['x-forwarded-proto'][0] ?? 'http' ) . '://' . $headers['host'][0];

        return view('admin', [
            'events' => $events,
            'base_url' => $baseUrl,
            'administrator' => $administrator,
        ]);
    }

    /**
     * @RequestMapping(route="/admin/api/actions/login", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function adminLogin(Request $request, Response $response): Response
    {
        $login_name = $request->getBodyParams()['login_name'];
        $password = $request->getBodyParams()['password'];
    
        $administrator = Db::query('SELECT * FROM administrators WHERE login_name = ?', [$login_name])->getResult();
        $pass_hash = Db::query('SELECT SHA2(?, 256) AS `hash`', [$password])->getResult()[0]['hash'];
    
        if (!$administrator || $pass_hash != $administrator[0]['pass_hash']) {
            return $this->res_error($response, 'authentication_failed', 401);
        }
        
        session()->put('administrator_id', (int)$administrator[0]['id']);
        
        return $response->json($administrator[0], 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/admin/api/actions/logout", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function adminLogout(Request $request, Response $response): Response
    {
        $res = $this->adminLoginRequired($response);
        if ($res) {
            return $res;
        }

        session()->flush();

        return $response->withStatus(204);
    }

    /**
     * @RequestMapping(route="/admin/api/events", method={RequestMethod::GET})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function adminGetEvents(Request $request, Response $response): Response
    {
        $res = $this->adminLoginRequired($response);
        if ($res) {
            return $res;
        }

        $events = $this->get_events(function ($event) { return $event; });
    
        return $response->json($events, 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/admin/api/events", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function adminCreateEvents(Request $request, Response $response): Response
    {
        $res = $this->adminLoginRequired($response);
        if ($res) {
            return $res;
        }

        $title = $request->getBodyParams()['title'];
        $public = $request->getBodyParams()['public'] ? 1 : 0;
        $price = $request->getBodyParams()['price'];
    
        $event_id = null;
    
        Db::beginTransaction();
        try {
            Db::query('INSERT INTO events (title, public_fg, closed_fg, price) VALUES (?, ?, 0, ?)', [$title, $public, $price])->getResult();
            $result = Db::query('SELECT last_insert_id() AS `id`')->getResult();
            $event_id = $result[0]['id'];
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    
        $event = $this->get_event($event_id);
    
        return $response->json($event, 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/admin/api/events/{id}", method={RequestMethod::GET})
     * @param Request $request
     * @param Response $response
     * @param int    $id
     * @return Response
     */
    public function adminGetEventsById(int $id, Request $request, Response $response): Response
    {
        $res = $this->adminLoginRequired($response);
        if ($res) {
            return $res;
        }

        $event_id = $id;

        $event = $this->get_event($event_id);
        if (empty($event)) {
            return $this->res_error($response, 'not_found', 404);
        }
    
        return $response->json($event, 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/admin/api/events/{id}/actions/edit", method={RequestMethod::POST})
     * @param Request $request
     * @param Response $response
     * @param int    $id
     * @return Response
     */
    public function adminEditEventsById(int $id, Request $request, Response $response): Response
    {
        $res = $this->adminLoginRequired($response);
        if ($res) {
            return $res;
        }

        $event_id = $id;
        $public = $request->getBodyParams()['public'] ? 1 : 0;
        $closed = $request->getBodyParams()['closed'] ? 1 : 0;
    
        if ($closed) {
            $public = 0;
        }
    
        $event = $this->get_event($event_id);
        if (empty($event)) {
            return $this->res_error($response, 'not_found', 404);
        }
    
        if ($event['closed']) {
            return $this->res_error($response, 'cannot_edit_closed_event', 400);
        } elseif ($event['public'] && $closed) {
            return $this->res_error($response, 'cannot_close_public_event', 400);
        }
    
        Db::beginTransaction();
        try {
            Db::query('UPDATE events SET public_fg = ?, closed_fg = ? WHERE id = ?', [$public, $closed, $event['id']])->getResult();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
        $event = $this->get_event($event_id);
    
        return $response->json($event, 200, JSON_NUMERIC_CHECK);
    }

    /**
     * @RequestMapping(route="/admin/api/reports/events/{id}/sales", method={RequestMethod::GET})
     * @param Request $request
     * @param Response $response
     * @param int    $id
     * @return Response
     */
    public function adminGetSalesById(int $id, Request $request, Response $response): Response
    {
        $event_id = $id;
        $event = $this->get_event($event_id);
    
        $reports = [];
    
        $reservations = Db::query('SELECT r.*, s.rank AS sheet_rank, s.num AS sheet_num, s.price AS sheet_price, e.price AS event_price FROM reservations r INNER JOIN sheets s ON s.id = r.sheet_id INNER JOIN events e ON e.id = r.event_id WHERE r.event_id = ? ORDER BY reserved_at ASC FOR UPDATE', [$event['id']])->getResult();
        foreach ($reservations as $reservation) {
            $report = [
                'reservation_id' => $reservation['id'],
                'event_id' => $reservation['event_id'],
                'rank' => $reservation['sheet_rank'],
                'num' => $reservation['sheet_num'],
                'user_id' => $reservation['user_id'],
                'sold_at' => (new \DateTime("{$reservation['reserved_at']}", new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u').'Z',
                'canceled_at' => $reservation['canceled_at'] ? (new \DateTime("{$reservation['canceled_at']}", new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u').'Z' : '',
                'price' => $reservation['event_price'] + $reservation['sheet_price'],
            ];
    
            array_push($reports, $report);
        }
    
        return $this->render_report_csv($response, $reports);
    }

    /**
     * @RequestMapping(route="/admin/api/reports/sales", method={RequestMethod::GET})
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function adminGetSales(Request $request, Response $response): Response
    {
        $res = $this->adminLoginRequired($response);
        if ($res) {
            return $res;
        }

        $reports = [];
        $reservations = Db::query('SELECT r.*, s.rank AS sheet_rank, s.num AS sheet_num, s.price AS sheet_price, e.id AS event_id, e.price AS event_price FROM reservations r INNER JOIN sheets s ON s.id = r.sheet_id INNER JOIN events e ON e.id = r.event_id ORDER BY reserved_at ASC FOR UPDATE')->getResult();
        foreach ($reservations as $reservation) {
            $report = [
                'reservation_id' => $reservation['id'],
                'event_id' => $reservation['event_id'],
                'rank' => $reservation['sheet_rank'],
                'num' => $reservation['sheet_num'],
                'user_id' => $reservation['user_id'],
                'sold_at' => (new \DateTime("{$reservation['reserved_at']}", new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u').'Z',
                'canceled_at' => $reservation['canceled_at'] ? (new \DateTime("{$reservation['canceled_at']}", new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u').'Z' : '',
                'price' => $reservation['event_price'] + $reservation['sheet_price'],
            ];
    
            array_push($reports, $report);
        }    
        return $this->render_report_csv($response, $reports);
    }

    private function render_report_csv(Response $response, array $reports): Response
    {
        usort($reports, function ($a, $b) { return $a['sold_at'] > $b['sold_at']; });
    
        $keys = ['reservation_id', 'event_id', 'rank', 'num', 'price', 'user_id', 'sold_at', 'canceled_at'];
        $body = implode(',', $keys);
        $body .= "\n";
        foreach ($reports as $report) {
            $data = [];
            foreach ($keys as $key) {
                $data[] = $report[$key];
            }
            $body .= implode(',', $data);
            $body .= "\n";
        }

        $tmpfname = tempnam("/tmp", "FOO");
        $handle = fopen($tmpfname, "w");
        fwrite($handle, $body);

        $swooleResponse = $response->getSwooleResponse();
        $swooleResponse->header('Content-Type', 'text/csv; charset=UTF-8');
        $swooleResponse->header('Content-Disposition', 'attachment; filename="report.csv"');

        return $swooleResponse->sendFile($tmpfname);
    }
}

