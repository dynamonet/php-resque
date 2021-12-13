<?php declare(strict_types=1);

namespace Dynamo\Resque\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Redis pubsub logging handler for monolog
 *
 * usage example:
 *
 *   $log = new Logger('resque');
 *   $redis = new RedisPubSubHandler(new \Dynamo\Redis\Client([...]), "logs", Logger::WARNING);
 *   $log->pushHandler($redis);
 *
 * @author Edu <eduardo@dynamonet.com.ar>
 */
class RedisPubSubHandler extends AbstractProcessingHandler
{
    /** @var \Dynamo\Redis\Client|\Redis */
    private $redisClient;
    
    /** @var string */
    private $channelKey;

    /**
     * @param \Predis\Client|\Redis $redis The redis instance
     * @param string                $key   The channel key to publish records to
     */
    public function __construct($redis, string $key, $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->redisClient = $redis;
        $this->channelKey = $key;

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record): void
    {
        $this->redisClient->publish($this->channelKey, json_encode($record));
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter();
    }
}
