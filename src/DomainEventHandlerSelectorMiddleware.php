<?php

declare(strict_types=1);

namespace Soa\ProcessManagerMiddleware;

use Psr\Container\ContainerInterface;
use Soa\ProcessManager\Application\EventMiddleware;
use Soa\ProcessManager\Domain\DomainEvent;
use Soa\ProcessManager\Domain\DomainEventHandler;
use Soa\ProcessManager\Domain\Transition;
use Soa\ProcessManager\Infrastructure\Persistence\Repository;

class DomainEventHandlerSelectorMiddleware implements EventMiddleware
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Repository
     */
    private $repository;

    public function __construct(ContainerInterface $container, Repository $repository)
    {
        $this->container  = $container;
        $this->repository = $repository;
    }

    public function __invoke(DomainEvent $event, callable $nextMiddleware): Transition
    {
        /** @var DomainEventHandler $handler */
        $handler = $this->container->get(get_class($event) . 'Handler');

        $process = $this->repository->findOfId($event->processId());

        $transition = $handler->handle($event, $process);

        return $transition;
    }
}
