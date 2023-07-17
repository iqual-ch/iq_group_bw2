<?php

namespace Drupal\iq_group_bw2\Service;

use Drupal\bw2_api\Bw2ApiServiceInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A service to import users from bw2 into drupal.
 */
class Importer {

  use StringTranslationTrait;

  /**
   * Configuration for the iq_group_bw2 settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Bw2 API service.
   *
   * @var \Drupal\bw2_api\Bw2ApiServiceInterface
   */
  protected $bw2ApiService;

  /**
   * Importer service constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\bw2_api\Bw2ApiServiceInterface $bw2_api_service
   *   The BW2 Api service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Bw2ApiServiceInterface $bw2_api_service) {
    $this->config = $config_factory->get('bw2_api.settings');
    $this->bw2ApiService = $bw2_api_service;
  }

  /**
   * Prepare array of operations for the batch.
   *
   * @return array
   *   An array of operations to perform.
   */
  public function getOperations() {
    $langCodes = $this->bw2ApiService->getLanguageInformation();
    $countryCodes = $this->bw2ApiService->getCountryInformation();
    $current_item_version = $this->config->get('current_item_version');
    $data = $this->bw2ApiService->getContacts($current_item_version);
    $users = $data['DataList'];
    $total_users = is_countable($users) ? count($users) : 0;
    $operations = [];

    if ($total_users > 0) {
      foreach (array_chunk($users, 100) as $batch_users) {
        $operations[] = [
          '_iq_group_bw2_import_users',
          [
            $batch_users,
            $data['MaxItemVersion'],
            $total_users,
            $langCodes,
            $countryCodes,
          ],
        ];
      }
    }
    return $operations;
  }

  /**
   * Run batch import.
   *
   * @param array $operations
   *   An array of operations to perform.
   */
  public function doImport(array $operations) {
    \Drupal::logger('iq_group_bw2')->notice('starting import');

    $batch = [
      'title' => $this->t('Import'),
      'operations' => $operations,
      'finished' => '_iq_group_bw2_finished_import',
      'file' => \Drupal::service('extension.list.module')->getPath('iq_group_bw2') . '/import_batch.inc',
      'init_message' => $this->t('Starting import, this may take a while.'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('An error occurred during processing'),
    ];
    batch_set($batch);
  }

}
