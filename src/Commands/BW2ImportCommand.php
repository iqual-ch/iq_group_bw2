<?php

namespace Drupal\iq_group_bw2\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\iq_group_bw2\Service\Importer;
use Drush\Commands\DrushCommands;

/**
 * Drush Command to import users from Bw2.
 */
class BW2ImportCommand extends DrushCommands {

  /**
   * The Import service.
   *
   * @var \Drupal\iq_group_bw2\Service\Importer
   */
  protected $importer = NULL;

  /**
   * The entityTypeManager.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory = NULL;

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
