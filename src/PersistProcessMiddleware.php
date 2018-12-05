<?php

declare(strict_types=1);

namespace Soa\ProcessManagerMiddleware;

use Soa\ProcessManager\Application\EventMiddleware;
use Soa\ProcessManager\Domain\DomainEvent;
use Soa\ProcessManager\Domain\Transition;
use Soa\ProcessManager\Infrastructure\Persistence\Repository;

class PersistProcessMiddleware implements EventMiddleware
{
    /**
     * @var Repository
     */
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(DomainEvent $event, callable $nextMiddleware): Transition
    {
        /** @var Transition $transition */
        $transition = $nextMiddleware($event);

        $this->repository->save($transition->process());

        return $transition;
    }
}
