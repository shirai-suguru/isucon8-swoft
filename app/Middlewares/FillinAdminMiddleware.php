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
class FillinAdminMiddleware implements MiddlewareInterface
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

        if ($user) {
            $view = \Swoft::getBean('view');
            $view->addAttribute('administrator', $administrator);
        }

        return $handler->handle($request);
    }
}
