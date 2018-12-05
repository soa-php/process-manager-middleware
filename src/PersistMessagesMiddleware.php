<?php

declare(strict_types=1);

namespace Soa\ProcessManagerMiddleware;

use function Martinezdelariva\Hydrator\extract;
use Soa\Clock\Clock;
use Soa\IdentifierGenerator\IdentifierGenerator;
use Soa\MessageStore\Message;
use Soa\MessageStore\MessageStore;
use Soa\ProcessManager\Application\EventMiddleware;
use Soa\ProcessManager\Domain\Command;
use Soa\ProcessManager\Domain\DomainEvent;
use Soa\ProcessManager\Domain\Transition;
use Soa\Traceability\Trace;

class PersistMessagesMiddleware implements EventMiddleware
{
    /**
     * @var MessageStore
     */
    private $commandStore;

    /**
     * @var Trace
     */
    private $trace;

    /**
     * @var string
     */
    private $processManagerName;

    /**
     * @var string
     */
    private $replyTo;

    /**
     * @var IdentifierGenerator
     */
    private $identifierGenerator;

    /**
     * @var MessageStore
     */
    private $eventStore;

    /**
     * @var Clock
     */
    private $clock;

    public function __construct(
        Clock $clock,
        string $processManagerName,
        MessageStore $commandStore,
        MessageStore $eventStore,
        Trace $trace,
        string $replyTo,
        IdentifierGenerator $identifierGenerator
    ) {
        $this->commandStore        = $commandStore;
        $this->trace               = $trace;
        $this->processManagerName  = $processManagerName;
        $this->replyTo             = $replyTo;
        $this->identifierGenerator = $identifierGenerator;
        $this->eventStore          = $eventStore;
        $this->clock               = $clock;
    }

    public function __invoke(DomainEvent $event, callable $nextMiddleware): Transition
    {
        $eventId = $this->identifierGenerator->nextIdentity();
        $this->eventStore->appendMessages($this->convertEventIntoMessage($event, $eventId));

        /** @var Transition $transition */
        $transition = $nextMiddleware($event);

        $this->commandStore->appendMessages(...$this->convertCommandsIntoMessages($transition->commands(), $eventId));

        return $transition;
    }

    private function convertEventIntoMessage(DomainEvent $domainEvent, string $eventId): Message
    {
        return new Message(
            'com.' . $this->trace->replyTo() . '.events.' . $this->messageType($domainEvent),
            $this->clock->now()->format(Clock::MICROSECONDS_FORMAT),
            extract($domainEvent),
            $domainEvent->streamId(),
            $this->trace->correlationId(),
            $this->trace->messageId(),
            $this->replyTo,
            $eventId,
            $this->replyTo,
            $this->trace->processId()
        );
    }

    private function convertCommandsIntoMessages(array $commands, string $causationId): array
    {
        return array_map(function (Command $command) use ($causationId) {
            return new Message(
                'com.' . $command->recipient() . '.commands.' . $this->messageType($command),
                $this->clock->now()->format(Clock::MICROSECONDS_FORMAT),
                extract($command),
                $command->streamId(),
                $this->trace->correlationId(),
                $causationId,
                $this->replyTo,
                $this->identifierGenerator->nextIdentity(),
                $command->recipient(),
                $command->processId()
            );
        }, $commands);
    }

    private function messageType($object): string
    {
        $parts = explode('\\', get_class($object));

        return $this->camelCaseToSnakeCase(end($parts));
    }

    private function camelCaseToSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    }
}
