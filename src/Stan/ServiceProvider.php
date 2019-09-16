<?php
namespace Nathejk\Stan;

use Pimple\ServiceProviderInterface;
use Pimple\Container;

/**
 * Silex service provider.
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Setup amqp lazy connection and channel, and provide queues and exchanges.
     *
     * @see ServiceProviderInterface::register().
     */
    public function register(Container $app)
    {
        $app['stan.connection.create'] = $app->protect(function ($client = null) use ($app) {
            $client = $client ?: (gethostname() . '-' . md5(microtime()));
            return new Connection($app['stan.options']['dsn'], $client);
        });

        $app['stan'] = function () use ($app) {
            return $app['stan.connection.create']();
        };
    }
}
