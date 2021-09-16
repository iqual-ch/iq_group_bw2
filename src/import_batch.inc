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
  $max_item_version = $data['MaxItemVersion'];
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
    $user = $this->processUser($users[$i]);

    \Drupal::entityTypeManager()->getStorage('user')->resetCache([$user->getId()]);

  }
  gc_collect_cycles();

  $context['message'] = 'Processed ' . $processed_abs . ' of ' . ($context['sandbox']['max'] + 1) . ' records (' . round($processed_rel) . ' %). Elapsed time: ' . round($time_elapsed, 2) . ' estimated time: ' . round($time_estimated, 2) . ' s';

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}


/**
 *
 */
function processUser($user_data){
    //Create if new user, update if existing
    $bw2_id = $user_data['Account_ID'];

    $query = Database::getConnection()->select('user__field_iq_group_bw2_id')
        ->fields('entity_id')
        ->condition('user__field_iq_group_bw2_id', $bw2_id, 'LIKE');
    $result = $result->execute();

    if (!empty($result)){
        $user = \Drupal\user\Entity\User::load($result[0]);
    }
    else {
        $user = \Drupal\user\Entity\User::create();
        $user->setPassword('test');
        $user->enforceIsNew();
        $user->set('field_iq_group_bw2_id', $bw2_id);
        $user->setEmail($user_data['Account_Email1']);
        $user->save();
    }
    return $user;
}

/**
 *
 */
function _iq_group_bw2_finished_import($success, $results, $operations) {
  $msg = 'Import finished';
  if (!empty($results['users'])) {
    $msg .= '<br />Imported ' . $results['imported']['users'] . ' users and updated ' . $results['updated']['users'];
  }
  $config = \Drupal::getContainer()->get('config.factory')->getEditable('bw2_api.settings');
  $config->set('current_item_version', $results['max_item_version']);
  $config->save();
  \Drupal::logger('iq_group_bw2_import')->notice($msg);
  drupal_set_message(new TranslatableMarkup($msg));
  return 0;
}