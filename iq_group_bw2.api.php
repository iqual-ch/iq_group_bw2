<?php

/**
 * @file
 * Hooks for the iq_group_bw2 module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the profile data before sending it to the API.
 *
 * @param array $data
 *   An array containing the profile data to be altered and the user entity.
 */
function hook_iq_group_bw2_profile_data_alter(array &$data) {
  /*
   * "$data" contains [
   *    &$profile_data,
   *    $user,
   *  ]
   * Here you can manipulate $profile_data however you like.
   * Note that $profile_data is passed by reference (&profile_data),
   * so changes here will affect the original array.
   */
}

/**
 * Alter the data before importing users.
 *
 * @param array $data
 *   The user data to be altered before the import.
 */
function hook_iq_group_bw2_before_import(array &$data) {
  /*
   * "$data" contains [
   *    &$user_data,
   *    $user,
   *    $userCountryCode,
   *    $userLanguageCode,
   *  ]
   */
}

/**
 * Alter the data after importing users.
 *
 * @param array $data
 *   The user data to be altered after the import.
 */
function hook_iq_group_bw2_after_import(array &$data) {
  /*
   * "$data" contains [
   *    &$user_data,
   *    $user,
   *    $userCountryCode,
   *    $userLanguageCode,
   *  ]
   */
}

/**
 * Alter the data before webform submission.
 *
 * @param array $data
 *   The user data to be altered before submission.
 */
function hook_iq_group_bw2_before_submission(array &$data) {
  /*
   * "$data" contains [
   *    &$user_data,
   *    $user,
   *    $form_state,
   *  ]
   */
}

/**
 * Perform other operations after webform submission.
 *
 * @param array $data
 *   The user data to be altered after the import.
 */
function hook_iq_group_bw2_after_submission(array &$data) {
  /*
   * "$data" contains [
   *    &$user_data,
   *    $user,
   *    $form_state,
   *  ]
   */
}

/**
 * @} End of "addtogroup hooks".
 */
