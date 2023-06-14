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
 * @param array $profile_data
 *   The profile data to be altered.
 */
function hook_iq_group_bw2_profile_data_alter(array &$profile_data) {
  /*
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
  // Here others will make a module that will call this to alter "$data".
}

/**
 * Alter the data after importing users.
 *
 * @param array $data
 *   The user data to be altered after the import.
 */
function hook_iq_group_bw2_after_import(array &$data) {
  // Here others will make a module that will call this to alter "$data".
}

/**
 * @} End of "addtogroup hooks".
 */
