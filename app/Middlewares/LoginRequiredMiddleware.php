<?php
namespace App\Middlewares;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoft\Bean\Annotation\Bean;
use Swoft\Http\Message\Middleware\MiddlewareInterface;
use Swoft\Bean\Annotation\Inject;
use Swoft\Log\Log;
use App\Models\Logic\UserLogic;

/**
 * @Bean()
 */
class LoginRequiredMiddleware implements MiddlewareInterface
{
    /**
     *
     * @Inject()
     * @var UserLogic
     */
    private $userLogic;

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \InvalidArgumentException|\Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->userLogic->getLoginUser();

        if (!$user) {
            $response = $handler->handle($request);
            $swooleResponse = $response->getSwooleResponse();
            $swooleResponse->status(401);
            $swooleResponse->header('Content-Type', 'application/json');
            $swooleResponse->write(JsonHelper::encode(['error' => 'login_required']));
            return $swooleResponse->end();
        }
        return $handler->handle($request);
    }
}