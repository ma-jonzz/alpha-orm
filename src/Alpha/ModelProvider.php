<?php

namespace Alpha;

use Doctrine\DBAL\Connection;

/**
 * Model Provider
 *
 * @author Julien Zamor <julien@troisyaourts.com>
 */

class ModelProvider{

  private $connection;

  public function __construct(Connection $connection)
  {
      $this->connection = $connection;
  }

  public function __invoke($class_name, $namespace = 'App\\Model\\'){

    $c = $namespace.$class_name;

  return new Manager($this->connection, $class_name, $namespace);

  }

}