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

  /**
   * Check if there are anything new since last run
   */
  public function doCheckData() {
    \Drupal::logger('iq_group_bw2')->notice('starting check');
    $current_item_version = $this->config->get('current_item_version');
    $data = $this->bw2ApiService->getContacts($current_item_version);
    if (!empty($data['DataList'])){
        $this->doImport($data['DataList']);
    }
    else{
        \Drupal::logger('iq_group_bw2')->notice('nothing to import');
    }
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('bw2_api.settings');
    $config->set('current_item_version', $data['MaxItemVersion']);
    $config->save();
    return FALSE;
  }

  /**
   *
   */
  public function doImport($users) {
    \Drupal::logger('iq_group_bw2')->notice('starting import');
    $langCodes = $this->bw2ApiService->getLanguageInformation();
    $countryCodes = $this->bw2ApiService->getCountryInformation();
    $operations[] = ['_iq_group_bw2_import_users', [$users, $langCodes, $countryCodes]];
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

/**
 * TO DO
 */
// function convertDataFromBw2($data){
//     $langCode = $this->bw2ApiService->getLanguageCode($user->getPreferredLangcode());
//     $test = $user->get('field_iq_group_preferences');
//     $newsletter = ($user->hasField('field_iq_group_preferences') && !$user->get('field_iq_group_preferences')->isEmpty()) ? true : false;
//     $address = $user->get('field_iq_user_base_address')->getValue();
//     $address = reset($address);
//     $countryCode = $this->bw2ApiService->getCountryCode($address['country_code']);
//     $salutation = $user->get('field_iq_user_base_salutation')->getValue();
//     $salutation = reset($salutation);
//     $pobox = $user->get('field_iq_user_base_adress_pobox')->getValue();
//     $pobox = reset($pobox);
//     $profile_data = [
//       'status' => $data['Account_Active'],
//       'field_iq_user_base_salutation' => $data['Account_Salutation'],
//       'field_iq_group_first_name' => $data['Account_FirstName'],
//       'field_iq_group_last_name' => $data['Account_LastName'],
//       'field_iq_group_base_address_0_address_address_line1' => $data['Account_AddressLine1'],
//       'field_iq_group_base_address_0_address_address_line2' => $data['Account_Street'],
//       'field_iq_user_base_adress_pobox' => $data['Account_POBox'],
//       'field_iq_group_base_address_0_address_postal_code' => $data['Account_PostalCode'],
//       'field_iq_group_base_address_0_address_locality' => $data['Account_City'],
//       'field_iq_group_base_address_0_address_country_code' => $data['Account_Country_Dimension_ID'],
//       'mail' => $data['Account_Email1'],
//       'field_iq_group_first_name' => $data['Account_Language_Dimension_ID'],
//       'field_iq_group_preferences' => ($data['Visitor_AllowEmail']) ? 2 : 0,
//       'roles' => [
//         DRUPAL_AUTHENTICATED_RID => 'authenticated user'
//       ]
//     ];
// }


}