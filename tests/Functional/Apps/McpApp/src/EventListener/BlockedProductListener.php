<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\McpApp\Controller\BlockedProductCrudController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;

#[AsEventListener]
final class BlockedProductListener
{
    public function __invoke(BeforeCrudActionEvent $event): void
    {
        if (BlockedProductCrudController::class !== $event->getAdminContext()?->getCrud()?->getControllerFqcn()) {
            return;
        }

        $event->setResponse(new Response('Blocked.', Response::HTTP_FORBIDDEN));
    }
}
