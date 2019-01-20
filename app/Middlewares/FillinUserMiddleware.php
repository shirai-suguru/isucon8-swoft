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
class FillinUserMiddleware implements MiddlewareInterface
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

        if ($user) {
            $view = \Swoft::getBean('view');
            $view->addAttribute('user', $user);
        }

        return $handler->handle($request);
    }
}
