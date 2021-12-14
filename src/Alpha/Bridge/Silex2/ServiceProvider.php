<?php

namespace Alpha\Bridge\Silex2;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Alpha\Manager;


class ServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        if(!isset($app['db']))
            throw new Exception("\$app['db'] is not defined", 1);
            
        $app['models'] = $app->protect(function ($class_name, $namespace = 'Model\\') use ($app) {
            $c = $namespace.$class_name;

            if(defined("$c::DB_NAME")){
                $db = $app['dbs'][$c::DB_NAME];
            }else{
                $db = $app['db'];
            }

            return new Manager($db, $class_name, $namespace);
        });
    }

    public function boot(Container $app)
    {
    }
}