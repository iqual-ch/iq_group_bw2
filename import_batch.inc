<?php

/**
 * @file
 * Process the batch command to import users from bw2.
 */

use Drupal\user\Entity\User;

/**
 * Process the users data from bw2 and import it to Drupal.
 *
 * @param array $users
 *   An array of user data.
 * @param string $max_item_version
 *   The latest version of the bw2 application.
 * @param int $total_users
 *   The total amount of users to process.
 * @param array $langCodes
 *   An array of predefined langcode values from Bw2.
 * @param array $countryCodes
 *   An array of predefined countrycode values from Bw2.
 * @param \DrushBatchContext $context
 *   The current batch context.
 */
function _iq_group_bw2_import_users(
  array $users,
  $max_item_version,
  int $total_users,
  array $langCodes,
  array $countryCodes,
  DrushBatchContext &$context
) {
  if ((is_countable($users) ? count($users) : 0) < 1) {
    \Drupal::logger('iq_group_bw2_import')->notice('nothing to import');
    return;
  }

  if (!isset($context['sandbox']['progress'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_index'] = 0;
    $context['sandbox']['current_max'] = max(100, is_countable($users) ? count($users) : 0);
    $context['sandbox']['max'] = $total_users - 1;
    $context['sandbox']['start'] = microtime(TRUE);

  }
  if (!isset($context['results']['users']) || empty($context['results']['users'])) {
    $context['results']['users'] = $users;
    $context['results']['progress'] = 0;
    $context['results']['imported']['users'] = 0;
    $context['results']['updated']['users'] = 0;
    $context['results']['error']['users'] = 0;
    $context['results']['max_item_version'] = $max_item_version;
  }

  $indexes = range($context['sandbox']['progress'], min($context['sandbox']['progress'] + 99, $context['sandbox']['max']));
  foreach ($indexes as $i) {
    $context['sandbox']['progress']++;
    $context['sandbox']['current_index'] = $i;
    if ($users[$i] == NULL) {
      continue;
    }
    if (!empty($users[$i]['Account_Email1'])) {
      $user = process_user($users[$i], $langCodes, $countryCodes, $context);
      \Drupal::entityTypeManager()->getStorage('user')->resetCache([$user->id()]);
    }
    else {
      $context['results']['error']['users']++;
    }
  }

  gc_collect_cycles();

  $context['results']['progress'] += $context['sandbox']['current_max'];
  $processed_rel = $context['results']['progress'] / ($context['sandbox']['max'] + 1) * 100;
  $context['message'] = 'Processed ' . $context['results']['progress'] . ' of ' . ($context['sandbox']['max'] + 1) . ' records (' . round($processed_rel) . ' %).';
}

/**
 * Process a single user.
 *
 * @param array $user_data
 *   The data to import.
 * @param array $langCodes
 *   An array of predefined langcode values from Bw2.
 * @param array $countryCodes
 *   An array of predefined countrycode values from Bw2.
 * @param \DrushBatchContext $context
 *   The current batch context.
 *
 * @return \Drupal\user\UserInterface
 *   The new / edited Drupal user.
 */
function process_user(
  array $user_data,
  array $langCodes,
  array $countryCodes,
  DrushBatchContext &$context
) {
  // Create if new user, update if existing.
  $bw2_id = $user_data['Account_ID'];
  $database = \Drupal::database();
  $query = $database->select('user__field_iq_group_bw2_id', 'u')
    ->condition('u.field_iq_group_bw2_id_value', $bw2_id, '=')
    ->fields('u', ['entity_id'])
    ->range(0, 1)
    ->execute();
  $result = $query->fetchObject();

  if ($result) {
    $user = User::load($result->entity_id);
    $context['results']['updated']['users']++;
  }
  else {
    $user = User::create();
    $user->setPassword('test');
    $user->enforceIsNew();
    /*
     * There could be multiple accounts with the same E-Mail from BW2,
     * so we have to create unique name for drupal.
     */
    $exists = user_load_by_name($user_data['Account_Email1']);
    if ($exists) {
      $i = 1;
      $name = $user_data['Account_Email1'] . '-' . $i;
      while (user_load_by_name($name)) {
        $i++;
        $name = $user_data['Account_Email1'] . '-' . $i;
      }
    }
    else {
      $name = $user_data['Account_Email1'];
    }
    $user->set('name', $name);
    $user->set('field_iq_group_bw2_id', $bw2_id);
    $context['results']['imported']['users']++;
  }
  $user->setEmail($user_data['Account_Email1']);
  if ($user->hasField('field_iq_group_salutation')) {
    $user->set('field_iq_group_salutation', $user_data['Account_Salutation']);
  }
  $userCountryCode = get_country_code($user_data['Account_Country_Dimension_ID'], $countryCodes);
  $userLanguageCode = get_language_code($user_data['Account_Language_Dimension_ID'], $langCodes);
  $address = [
    'langcode' => $userLanguageCode,
    'family_name' => $user_data['Account_LastName'],
    'given_name' => $user_data['Account_FirstName'],
    'address_line1' => $user_data['Account_AddressLine1'],
    'address_line2' => $user_data['Account_Street'],
    'postal_code' => $user_data['Account_PostalCode'],
    'locality' => $user_data['Account_City'],
    'country_code' => $userCountryCode,
  ];
  $user->set('field_iq_user_base_address', $address);

  if ($user_data['Account_Active']) {
    $user->activate();
  }

  // Allow other module to modify the user object before save.
  \Drupal::moduleHandler()
    ->invokeAll('iq_group_bw2_before_import',
      [
        &$user_data,
        $user,
        $userCountryCode,
        $userLanguageCode,
      ]
    );

  $user->save();

  // If new user we add the user to the general group.
  if (!$result) {
    $group_general = \Drupal::service('iq_group.user_manager')->getGeneralGroup();
    \Drupal::service('iq_group.user_manager')->addGroupRoleToUser($group_general, $user, 'subscription-subscriber');
  }

  // Allow other module to perform other operation after save.
  \Drupal::moduleHandler()
    ->invokeAll('iq_group_bw2_after_import',
      [
        $user_data,
        $user,
        $userCountryCode,
        $userLanguageCode,
      ]
    );

  return $user;
}

/**
 * Get matching langcode from bw2.
 *
 * @param string $bw2_code
 *   The bw2 langcode.
 * @param array $lang_codes
 *   An array of all bw2 langcodes.
 *
 * @return string|null
 *   The matching code or null.
 */
function get_language_code($bw2_code, array $lang_codes) {
  foreach ($lang_codes['DataList'] as $code) {
    if ($code['Dimension_ID'] === $bw2_code) {
      return $code['Dimension_Code'];
    }
  }
  return NULL;
}

/**
 * Get matching countrycode from bw2.
 *
 * @param string $bw2_code
 *   The bw2 country_code.
 * @param array $country_codes
 *   An array of all bw2 countrycodes.
 *
 * @return string|null
 *   The matching code or null.
 */
function get_country_code($bw2_code, array $country_codes) {
  foreach ($country_codes['DataList'] as $code) {
    if ($code['Dimension_ID'] === $bw2_code) {
      return $code['Dimension_Code'];
    }
  }
  return NULL;
}

/**
 * Report import status and save bw2 version in config.
 */
function _iq_group_bw2_finished_import($success, $results, $operations) {
  $msg = 'Import finished';
  if (!empty($results['users'])) {
    $msg .= '<br />Imported ' . $results['imported']['users'] . ' users and updated ' . $results['updated']['users'];
    $msg .= '<br />Errors: ' . $results['error']['users'];
  }
  \Drupal::logger('iq_group_bw2_import')->notice($msg);
  if (isset($results['max_item_version']) && !empty($results['max_item_version'])) {
    $config = \Drupal::getContainer()->get('config.factory')->getEditable('bw2_api.settings');
    $config->set('current_item_version', $results['max_item_version']);
    $config->save();
    \Drupal::logger('iq_group_bw2_import')->notice('Configuration updated to ' . $results['max_item_version']);
  }
  return 0;
}
