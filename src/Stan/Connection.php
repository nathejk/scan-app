<?php
namespace Nathejk\Stan;

class Connection
{
    protected $connection = null;

    public function __construct($dsn, $client)
    {
        $this->dsn = $dsn;
        $this->client = $client;
        $this->connection = $this->createConnection($dsn, $client);
    }

    public function createConnection($dsn, $client)
    {
        $coin = parse_url($dsn) + [
            'host' => 'localhost',
            'port' => 4222,
            'user' => null,
            'pass' => null,
            'path' => '//'
        ];
        $opts = (new \NatsStreaming\ConnectionOptions())
            ->setNatsOptions((new \Nats\ConnectionOptions)->setHost($coin['host'])->setPort($coin['port']))
            ->setClientID($client)
            ->setClusterID(substr($coin['path'], 1));

        $connection = new \NatsStreaming\Connection($opts);
        @$connection->connect(999);
        return $connection;
    }

    public function publish(string $channel, Message $message)
    {
        try {
            $r = $this->connection->publish($channel, json_encode($message->getMessage()));
        } catch (\Exception $e) {
            $this->connection = $this->createConnection($this->dsn, $this->client);
            $r = $this->connection->publish($channel, json_encode($message->getMessage()));
        }
    }

    public function subscribe(string $channel, callable $callback)
    {
        $subOptions = (new \NatsStreaming\SubscriptionOptions())
            ->setManualAck(true)
            ->setStartAt(\NatsStreamingProtos\StartPosition::First());

        return $this->connection->subscribe($channel, function ($message) use ($callback) {
            $message->ack();
            $t = $message->getTimestamp() / 1e9;
            $d = new \DateTime(date("Y-m-d H:i:s." . sprintf("%06d", ($t - floor($t)) * 1e6), $t));

            $callback((object)[
                'sequence' => $message->getSequence(),
                'time' => $d->format("Y-m-d H:i:s.u"),
                'data' => json_decode($message->getData()->getContents()),
                'subject' => $message->getSubject(),
                'reply' => $message->getReply(),
                //'raw' => $message,
            ]);
        }, $subOptions);
    }

    public function listen(string $channel, callable $callback)
    {
        $subscription = $this->subscribe($channel, $callback);
        while (true) {
            try {
                $subscription->wait();
            } catch (\Exception $e) {
                print get_class($e);
            }
        }
    }

    public function listenOnce(string $channel, callable $callback)
    {
        $this->subscribe($channel, $callback)->wait(1);
    }

    public function close()
    {
        $this->connection->close();
    }
}
