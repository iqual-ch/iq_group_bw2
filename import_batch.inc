<?php

/**
 * @file
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Database\Database;

/**
 *
 */
function _iq_group_bw2_import_users($data, $langCodes, $countryCodes, &$context) {
  $users = $data['DataList'];
  $config = \Drupal::getContainer()->get('config.factory')->getEditable('bw2_api.settings');
  $config->set('current_item_version', $data['MaxItemVersion']);
  $config->save();
  if (count($users) < 1) {
    return;
  }
  if (empty($context['sandbox'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_index'] = 0;
    $context['sandbox']['max'] = count($users) - 1;
    $context['sandbox']['start'] = microtime(TRUE);

  }
  if (!isset($context['results']['users']) || empty($context['results']['users'])) {
    $context['results']['users'] = $users;
    $context['results']['imported']['users'] = 0;
    $context['results']['updated']['users'] = 0;
    $context['results']['max_item_version'] = $max_item_version;
  }
  $indexes = range($context['sandbox']['progress'], min($context['sandbox']['progress'] + 99, $context['sandbox']['max']));
  foreach ($indexes as $i) {
    $context['sandbox']['progress']++;
    $context['sandbox']['current_index'] = $i;

    $processed_abs = $i + 1;
    $processed_rel = $processed_abs / ($context['sandbox']['max'] + 1) * 100;

    $time_elapsed = (microtime(TRUE) - $context['sandbox']['start']);
    $time_estimated = $time_elapsed / $processed_abs * ($context['sandbox']['max'] + 1);

    if ($users[$i] == NULL) {
      continue;
    }
    $user = processUser($users[$i], $langCodes, $countryCodes, $context);

    \Drupal::entityTypeManager()->getStorage('user')->resetCache([$user->id()]);

  }
  gc_collect_cycles();

  $context['message'] = 'Processed ' . $processed_abs . ' of ' . ($context['sandbox']['max'] + 1) . ' records (' . round($processed_rel) . ' %). Elapsed time: ' . round($time_elapsed, 2) . ' estimated time: ' . round($time_estimated, 2) . ' s';
}


/**
 *
 */
function processUser($user_data, $langCodes, $countryCodes, &$context){
    //Create if new user, update if existing
    $bw2_id = $user_data['Account_ID'];
    $database = \Drupal::database();
    $query = $database->select('user__field_iq_group_bw2_id', 'u')
    ->condition('u.field_iq_group_bw2_id_value', $bw2_id, '=')
    ->fields('u', ['entity_id'])
    ->range(0, 1)
    ->execute();
    $result = $query->fetchObject();

    if ($result){
        $user = \Drupal\user\Entity\User::load($result->entity_id);
        $context['results']['updated']['users']++;
    }
    else {
      $user = \Drupal\user\Entity\User::create();
      $user->setPassword('test');
      $user->enforceIsNew();
      $context['results']['imported']['users']++;
    }
    $user->set('field_iq_group_bw2_id', $bw2_id);
    $user->set('name',$user_data['Account_Email1']);
    $user->setEmail($user_data['Account_Email1']);
    $user->set('field_iq_user_base_salutation', $user_data['Account_Salutation']);
    $userCountryCode = getCountryCode($user_data['Account_Country_Dimension_ID'],$countryCodes);
    $userLanguageCode = getLanguageCode($user_data['Account_Language_Dimension_ID'],$langCodes);
    $address = [
      'langcode' => $userLanguageCode,
      'family_name' => $user_data['Account_LastName'],
      'given_name' => $user_data['Account_FirstName'],
      'address_line1' => $user_data['Account_AddressLine1'],
      'address_line2' => $user_data['Account_Street'],
      'postal_code' => $user_data['Account_PostalCode'],
      'locality' => $user_data['Account_City'],
      'country_code' => $userCountryCode
    ];
    $user->set('field_iq_user_base_address', $address);
    $user->set('field_iq_user_base_adress_pobox', $user_data['Account_POBox']);
    if ($user_data['Account_Active']){
      $user->activate();
    }
    if ($user_data['Visitor_AllowEmail']){
      $user->set('field_iq_group_preferences', [2]);
    }
    if ($user_data['Account_Birthday']){
      preg_match('/Date\(-?([0-9]{12})\)/',$user_data['Account_Birthday'], $matches);
      if (!empty($matches)){
        $timestamp = intval($matches[1]) / 1000;
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $user->set('field_gcb_custom_birth_date',$date->format('Y-m-d'));
      }
    }
    $user->save();
    return $user;
}

function getLanguageCode($bw2_code, $lang_codes){
  foreach($lang_codes['DataList'] as $key => $code){
    if ($code['Dimension_ID'] === $bw2_code){
      return $code['Dimension_Code'];
    }
  }
  return NULL;
}

function getCountryCode($bw2_code, $country_codes){
  foreach($country_codes['DataList'] as $key => $code){
    if ($code['Dimension_ID'] === $bw2_code){
      return $code['Dimension_Code'];
    }
  }
  return NULL;
}

/**
 *
 */
function _iq_group_bw2_finished_import($success, $results, $operations) {
  $msg = 'Import finished';
  if (!empty($results['users'])) {
    $msg .= '<br />Imported ' . $results['imported']['users'] . ' users and updated ' . $results['updated']['users'];
  }
  \Drupal::logger('iq_group_bw2_import')->notice($msg);
  drupal_set_message(new TranslatableMarkup($msg));
  return 0;
}