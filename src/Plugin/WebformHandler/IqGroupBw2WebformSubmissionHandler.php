<?php

namespace Drupal\iq_group_bw2\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iq_group\Controller\UserController;
use Drupal\user\Entity\User;
use Drupal\webform\WebformSubmissionInterface;

/**
 * IQ Group BW2 Webform submission handler.
 *
 * @WebformHandler(
 *     id = "iq_group_bw2_submission_handler",
 *     label = @Translation("IQ Group BW2 Submission Handler"),
 *     category = @Translation("Form Handler"),
 *     description = @Translation("Creates and updates users on submissions and synchronize with bw2 CRM"),
 *     cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *     results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 * @package Drupal\iq_group_bw2\Plugin\WebformHandler
 */
class IqGroupBw2WebformSubmissionHandler extends \Drupal\webform\Plugin\WebformHandlerBase {

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $user_data = [];
    $userExists = TRUE;

    $user = NULL;

    if ($form_state->getValue('customer_email')) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(
        [
          'mail' => $form_state->getValue('customer_email')
        ]
      );
      if (count($user) == 0){
        $userExists = FALSE;
        $user_data['name'] = $form_state->getValue('customer_email');
        $user_data['mail'] = $user_data['name'];
        $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();;
        $user_data['preferred_langcode'] = $currentLanguage;
        $user_data['langcode'] = $currentLanguage;
      }
      else {
        $user = reset($user);
      }
    }
    if ($form_state->getValue('customer_salutation')) {
      $user_data['field_iq_user_base_salutation'] = $form_state->getValue('customer_salutation');
    }
    if ($form_state->getValue('customer_first_name')) {
      $user_data['field_iq_user_base_address']['given_name'] = $form_state->getValue('customer_first_name');
    }
    if ($form_state->getValue('customer_last_name')) {
      $user_data['field_iq_user_base_address']['family_name'] = $form_state->getValue('customer_last_name');
    }
    if ($form_state->getValue('customer_address')) {
      $user_data['field_iq_user_base_address']['address_line1'] =  $form_state->getValue('customer_address')['address'];
      $user_data['field_iq_user_base_address']['address_line2'] =  $form_state->getValue('customer_address')['address_2'];
      $user_data['field_iq_user_base_address']['locality'] =  $form_state->getValue('customer_address')['city'];
      $user_data['field_iq_user_base_address']['postal_code'] =  $form_state->getValue('customer_address')['postal_code'];
      $user_data['field_iq_user_base_address']['country_code'] =  $form_state->getValue('customer_address')['country'];
    }
    if ($form_state->getValue('customer_birth_date')) {
      $user_data['field_gcb_custom_birth_date'] = $form_state->getValue('customer_birth_date');
    }

    $user_data['field_iq_group_preferences'] = ($form_state->getValue('customer_newsletter')) ? [1,2] : [1];

    // Set the country code to Switzerland as it is required.
    if (!empty($user_data['field_iq_user_base_address']['country_code'])){
       $user_data['field_iq_user_base_address']['country_code'] = 'CH';
    }

    // If user exists, attribute the submission to the user.
    if (!empty($user) && $userExists) {
        $webform_submission->setOwnerId($user->id())->save();
    }
    // If the user does not exists,
    // Create the user, register him to the iq group and attribute the submission to the user.
    else  {
      $user = UserController::createMember($user_data);
      $store = \Drupal::service('user.shared_tempstore')->get('iq_group.user_status');
      $store->set($user->id().'_pending_activation', true);
      $webform_submission->setOwnerId($user->id())->save();
      $group_general = \Drupal\group\Entity\Group::load(1);
      \Drupal\iq_group\Controller\UserController::addGroupRoleToUser($group_general, $user, 'subscription-subscriber');
      \Drupal::logger('iq_group_bw2')->notice('user added to the general group');
    }

  }
}