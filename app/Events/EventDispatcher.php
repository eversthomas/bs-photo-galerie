<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Events;

/**
 * Synchroner Event-Bus für Erweiterungen (kein Queue, kein Framework).
 *
 * Listener-Fehler werden geloggt und brechen den Hauptablauf nicht ab.
 */
final class EventDispatcher
{
    /** @var array<class-string, list<callable(object): void>> */
    private array $listeners = [];

    /**
     * @param class-string $eventClass
     * @param callable(object): void $listener
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): void
    {
        $class = $event::class;
        foreach ($this->listeners[$class] ?? [] as $listener) {
            try {
                $listener($event);
            } catch (\Throwable $e) {
                error_log(
                    '[BSPHOTO][Event] '
                    . $class
                    . ' listener '
                    . $e::class
                    . ': '
                    . $e->getMessage()
                    . ' in '
                    . $e->getFile()
                    . ':'
                    . (string) $e->getLine()
                );
            }
        }
    }
}
