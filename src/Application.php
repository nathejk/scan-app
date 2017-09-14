<?php
namespace Nathejk\Scan;

class Application extends \Silex\Application
{
    public function boot()
    {
        $this['time'] = time();

        $this->registerRoutes();
        $this->registerServices();
        parent::boot();
    }

    protected function registerRoutes()
    {
        $this->match('/', Controller::class . '::loginAction');
        $this->match('/scan/{teamId}/{checksum}', Controller::class . '::scanAction');
    }

    protected function registerServices()
    {
        $this->register(new \Silex\Provider\TwigServiceProvider(), ['twig.path' => __DIR__ . '/../twig']);
        $this->register(
            new \Silex\Provider\DoctrineServiceProvider(),
            ['dbs.options' => [
                'default' => [
                    'url' => $this['config']['DB_DSN'] ?? null,
                    'charset' => 'utf8',

                    // Do not silently truncate strings/numbers that are too big.
                    'driverOptions' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode = "STRICT_ALL_TABLES"'],
                ],
                'monolith' => [
                    'url' => $this['config']['MONOLITH_DB_DSN'] ?? null,
                    'charset' => 'utf8',

                    // Do not silently truncate strings/numbers that are too big.
                    'driverOptions' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode = "STRICT_ALL_TABLES"'],
                ],
            ]]
        );
        $this['repo'] = function ($app) { return new Repository($app); };
    }
}
