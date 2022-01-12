<?php

namespace Drupal\iq_group_bw2\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\bw2_api\bw2ApiServiceInterface;
/**
 *
 */
class Importer {

  /**
   * Importer service constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param bw2ApiServiceInterface $bw2_api_service
   *   The BW2 Api service
   */
  public function __construct(ConfigFactoryInterface $config_factory, bw2ApiServiceInterface $bw2_api_service) {
    $this->config = $config_factory->get('bw2_api.settings');
    $this->bw2ApiService = $bw2_api_service;
  }

  public function getOperations(){
    $langCodes = $this->bw2ApiService->getLanguageInformation();
    $countryCodes = $this->bw2ApiService->getCountryInformation();
    $current_item_version = $this->config->get('current_item_version');
    $data = $this->bw2ApiService->getContacts($current_item_version);
    $users = $data['DataList'];
    $total_users = count($users);
    $operations = [];
    
/*     $resumeId = '-2144237137';
    $resume_key = array_search($resumeId, array_column($data['DataList'], 'Account_ID'));
    \Drupal::logger('iq_group_bw2')->notice('resume at '. $resume_key);
    $users = array_slice($data['DataList'], $resume_key);
    $total_users = count($remainingUsers); */

    if ($total_users > 0) {
      foreach (array_chunk($users, 100) as $batchId => $batch_users) {
        $operations[] = ['_iq_group_bw2_import_users', [$batch_users, $data['max_item_version'], $total_users, $langCodes, $countryCodes]];
      }
    }
    return $operations;
  }

  /**
   *
   */
  public function doImport($operations) {
    \Drupal::logger('iq_group_bw2')->notice('starting import');

    $batch = [
      'title' => t('Import'),
      'operations' => $operations,
      'finished' => '_iq_group_bw2_finished_import',
      'file' => drupal_get_path('module', 'iq_group_bw2') . '/import_batch.inc',
      'init_message' => t('Starting import, this may take a while.'),
      'progress_message' => t('Processed @current out of @total.'),
      'error_message' => t('An error occurred during processing'),
    ];
    batch_set($batch);
  }
}