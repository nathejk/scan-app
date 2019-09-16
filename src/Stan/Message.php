<?php
namespace Nathejk\Stan;

use Ramsey\Uuid\Uuid;

class Message
{
    protected $message;

    public function __construct()
    {
        $eventId = 'event-' . Uuid::uuid4()->toString();
        $this->message = [
            "type" => '',
            "body" => (object)[],
            "meta" => (object)[],
            "eventId" => $eventId,
            "correlationId" => $eventId,
            "causationId" => $eventId,
            "datetime" => (new \DateTime)->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    public function setBody(\stdClass $body)
    {
        $this->message['body'] = $body;
        return $this;
    }

    public function setMeta(\stdClass $meta)
    {
        $this->message['meta'] = $meta;
        return $this;
    }

    public function setType(string $type)
    {
        $this->message['type'] = $type;
        return $this;
    }
    
    public function getMessage()
    {
        return $this->message;
    }
}
