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
use Swoft\Helper\JsonHelper;
use Swoft\Http\Message\Server\Response;

/**
 * @Bean()
 */
class AdminLoginRequiredMiddleware implements MiddlewareInterface
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
        $administrator = $this->userLogic->getLoginAdministrator();

        Log::trace(var_export($administrator, true));
        if (!$administrator) {
            $response = $handler->handle($request);
            $swooleResponse = $response->getSwooleResponse();
            $swooleResponse->status(401);
            $swooleResponse->header('Content-Type', 'application/json');
            $swooleResponse->write(JsonHelper::encode(['error' => 'admin_login_required']));
            return $swooleResponse->end();
        }
        return $handler->handle($request);
    }
}
