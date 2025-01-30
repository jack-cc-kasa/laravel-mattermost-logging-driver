<?php

namespace colbygarland\Mattermost\Logger;

use colbygarland\Mattermost\Logger\Interfaces\Options;
use colbygarland\Mattermost\Logger\Interfaces\Scribe;
use colbygarland\Mattermost\Logger\Values\Level;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord; // ✅ Correctly importing Monolog's LogRecord

final class Handler extends AbstractProcessingHandler
{
    /** @var \colbygarland\Mattermost\Logger\Interfaces\Options */
    private $options;

    /** @var \colbygarland\Mattermost\Logger\Mattermost */
    private $mattermost;

    /** @var \colbygarland\Mattermost\Logger\Interfaces\Scribe */
    private $scribeClass;

    /** @var \colbygarland\Mattermost\Logger\Interfaces\Message */
    private $messageClass;

    public function __construct(
        Mattermost $mattermost,
        Options $options,
        string $scribeClass,
        string $messageClass
    ) {
        $this->mattermost = $mattermost;
        $this->options = $options;
        $this->scribeClass = $scribeClass;
        $this->messageClass = $messageClass;
    }

    public function write(LogRecord $record): void
    {
        if (!$this->shouldWrite($record->level->value)) { // ✅ Fix: Convert level object to int
            return;
        }
        
        $this->mattermost->send(
            $this->makeScribe($record)->message(),
            $this->options->webhook()
        );
    }

    private function makeScribe(LogRecord $record): Scribe
    {
        $logRecordArray = [
            'level' => $record->level->value,             // Getting the level value
            'level_name' => $record->level->name ?? null,  // Getting the string name of the level (safe fallback)
            'channel' => $record->channel ?? null,         // Channel (fallback in case it's null)
            'message' => $record->message ?? null,         // Message (fallback in case it's null)
            'context' => $record->context ?? [],           // Context (fallback empty array)
            'extra' => $record->extra ?? [],               // Extra (fallback empty array)
            'datetime' => $record->datetime->format('Y-m-d H:i:s') ?? null, // Formatted datetime
        ];
        return new $this->scribeClass(
            new $this->messageClass,
            $this->options,
            $logRecordArray
        );
    }

    private function shouldWrite(int $level): bool
    {
        $level = new Level($level);

        return $this->options->level()->isLessThan($level);
    }
}
