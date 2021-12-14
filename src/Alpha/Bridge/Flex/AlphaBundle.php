<?php

namespace Alpha\Bridge\Flex;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Console\Application;

/**
 * Bundle.
 *
 * @author Julien Zamor <julien@troisyaourts.com>
 */

class AlphaBundle extends Bundle{


  /**
   * {@inheritDoc}
   */
  public function build(ContainerBuilder $container)
  {
    parent::build($container);

  }
  /**
   * {@inheritDoc}
   */
  public function boot()
  {
  }
  /**
   * {@inheritDoc}
   */
  public function shutdown()
  {
  }

  /**
   * {@inheritDoc}
   */
  public function registerCommands(Application $application)
  {
  }

}