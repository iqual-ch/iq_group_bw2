<?php

namespace Drupal\iq_group_bw2\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\iq_group_bw2\Service\Importer;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class BW2ImportCommand extends DrushCommands {

  /**
   * Constructs a new BW2ImportCommand object.
   *
   * @param \Drupal\iq_group_bw2\Service\Importer $importer
   *   Importer service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger service.
   */
  public function __construct(Importer $importer, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->importer = $importer;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * Import all users.
   *
   * @command bw2:import-all
   * @aliases bw2-import-all
   *
   * @usage bw2:import-all
   */
  public function importAll() {
    $operations = [];
    $operations = $this->importer->getOperations();
    $this->importer->doImport($operations);
    drush_backend_batch_process();
    return 0;
  }

}
