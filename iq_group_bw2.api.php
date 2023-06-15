<?php

/**
 * @file
 * Hooks for the iq_group_bw2 module.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the profile data before sending it to the API.
 *
 * @param array $data
 *   An array containing the profile data to be altered and the user entity.
 * @param \Drupal\user\UserInterface $user
 *   The user to process.
 */
function hook_iq_group_bw2_profile_data_alter(array &$profile_data, UserInterface $user) {
  /*
   * Here you can manipulate $profile_data however you like.
   * Note that $profile_data is passed by reference (&profile_data),
   * so changes here will affect the original array.
   */
}

/**
 * Alter the data before importing users.
 *
 * @param array $user_data
 *   The user data to be altered before the import.
 * @param \Drupal\user\UserInterface $user
 *   The user to import data into.
 * @param string $countryCode
 *   The unique id for the country from bw2.
 * @param string $langCode
 *   The unique id for the language from bw2.
 */
function hook_iq_group_bw2_before_import(
  array &$user_data, 
  UserInterface $user, 
  $countryCode, 
  $langCode
) {
  /*
   * Here you can manipulate $user_data and user however you like before saving the user.
   * Note that $user_data is passed by reference (&user_data),
   * so changes here will affect the original array.
   */
}

/**
 * Alter the data after importing users.
 * 
 * @param array $user_data
 *   The user data that has been imported.
 * @param \Drupal\user\UserInterface $user
 *   The user entity.
 * @param string $countryCode
 *   The unique id for the country from bw2.
 * @param string $langCode
 *   The unique id for the language from bw2.
 */
function hook_iq_group_bw2_after_import(
  array $user_data, 
  UserInterface $user, 
  $countryCode, 
  $langCode
) {
  /*
   * Here you perform further operations after import the data into the user.
   */
}

/**
 * Alter the data before webform submission.
 *
 * @param array $user_data
 *   The user data that will be send to bw2.
 * @param \Drupal\user\UserInterface $user
 *   The user entity.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The submitted form state.
 */
function hook_iq_group_bw2_before_submission(
  array &$user_data,
  UserInterface $user,
  FormStateInterface $form_state
) {
  /*
   * Here you can manipulate $user_data and user however you like before saving the user.
   * Note that $user_data is passed by reference (&user_data),
   * so changes here will affect the original array.
   */
}

/**
 * Perform other operations after webform submission.
 *
 * @param array $user_data
 *   The user data that will be send to bw2.
 * @param \Drupal\user\UserInterface $user
 *   The user entity.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The submitted form state.
 */
function hook_iq_group_bw2_after_submission(
  array &$user_data,
  UserInterface $user,
  FormStateInterface $form_state
) {
  /*
   * Here you perform further operations after submitting the data,
   * like custom redirection.
   */
}

/**
 * @} End of "addtogroup hooks".
 */
