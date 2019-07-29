<?php

namespace Makeable\Throttle\Tests;


class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    public function setUp():void
    {
        parent::setUp();
    }

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=true');
        putenv('CACHE_DRIVER=array');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');

        $app = require __DIR__.'/../vendor/laravel/laravel/bootstrap/app.php';

        $app->useEnvironmentPath(__DIR__.'/..');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
//        $app->register(CloudImagesServiceProvider::class);
//        $app->afterResolving('migrator', function ($migrator) {
//            $migrator->path(__DIR__.'/migrations/');
//        });

        // Register facade
//        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
//        $loader->alias('CloudImageFacade', CloudImageFacade::class);

        return $app;
    }
}
