<?php

/**
 * @file
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Database\Database;

/**
 *
 */
function _iq_group_bw2_import_users($users, $max_item_version, $langCodes, $countryCodes, &$context) {
  if (count($users) < 1) {
    \Drupal::logger('iq_group_bw2_import')->notice('nothing to import');
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
      // There could be multiple accounts with the same E-Mail from BW2, so we have to create unique name for drupal
      $exists = user_load_by_name($user_data['Account_Email1']);
      if ($exists){
        $i = 1;
        $name = $user_data['Account_Email1'] . '-' . $i;
        while(user_load_by_name($name)){
          $i++;
          $name = $user_data['Account_Email1'] . '-' . $i;
        }
      }
      else{
        $name = $user_data['Account_Email1'];
      }
      $user->set('name',$name);
      $user->set('field_iq_group_bw2_id', $bw2_id);
      $context['results']['imported']['users']++;
    }
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
    
    if ($user_data['Account_Birthday']){
      $date = substr($user_data['Account_Birthday'],0,10);
      \Drupal::logger('iq_group_bw2')->notice('user has a birth date: ' . $date);
      $user->set('field_gcb_custom_birth_date',$date);
    }
    if ($user_data['Visitor_AllowEmail']){
      $user->set('field_iq_group_preferences', [1,2]);
    }
    else{
      $user->set('field_iq_group_preferences', [1]);
    }
    $user->save();

    // If new user we add him to the general group
    if (!$result){
      $group_general = \Drupal\group\Entity\Group::load(1);
      \Drupal\iq_group\Controller\UserController::addGroupRoleToUser($group_general, $user, 'subscription-subscriber');
    }

    if ($user_data['Visitor_AllowEmail']){
      $group_newsletter = \Drupal\group\Entity\Group::load(2);
      \Drupal\iq_group\Controller\UserController::addGroupRoleToUser($group_newsletter, $user, 'subscription-subscriber');
      \Drupal::logger('iq_group_bw2')->notice('user added to the newsletter group');
    }
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
  
  if (isset($results['max_item_version']) && !empty($results['max_item_version'])){
     $config = \Drupal::getContainer()->get('config.factory')->getEditable('bw2_api.settings');
    $config->set('current_item_version', $results['max_item_version']);
    $config->save();
    \Drupal::logger('iq_group_bw2_import')->notice('Configuration updated to ' . $results['max_item_version']);
  }
  return 0;
}