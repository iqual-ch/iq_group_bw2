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
    $values = $webform_submission->getData();

    $email = '';
    $user = NULL;
    foreach ($form['elements'] as $key => $element) {
      if ($key == 'customer_email') {
        $user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(
          [
            'mail' => $form_state->getValue($key)
          ]
        );
        if (count($user) == 0){
          $userExists = FALSE;

          $user_data['name'] = $form_state->getValue($key);
          $user_data['mail'] = $user_data['name'];
          $currentLanguage = $language = \Drupal::languageManager()->getCurrentLanguage()->getId();;
          $user_data['preferred_langcode'] = $currentLanguage;
          $user_data['langcode'] = $currentLanguage;
        }
        else {
          $user = reset($user);
          $email = $user->getEmail();
        }
      }
      else if ($form_state->getValue($key) && $key == 'newsletter_subscription') {
        $user_data['field_iq_group_preferences'] = $form_state->getValue($key);
      }
      elseif ($form_state->getValue($key) && $key == 'customer_salutation') {
        $user_data['field_iq_user_base_salutation'] = $form_state->getValue($key);
      }
    }
    // Set the country code to Switzerland as it is required.
    $user_data['field_iq_user_base_address']['country_code'] = 'CH';

    
    // If user exists, attribute the submission to the user.
    if (!empty($user) && $userExists) {
        $webform_submission->setOwnerId($user->id())->save();
    }
    // If the user does not exists and the user checked the newsletter,
    // Create the user and attribute the submission to the user.
    else if (!empty($user_data['field_iq_group_preferences'])) {
      $user = UserController::createMember($user_data);
      $store = \Drupal::service('user.shared_tempstore')->get('iq_group.user_status');
      $store->set($user->id().'_pending_activation', true);
      $webform_submission->setOwnerId($user->id())->save();
    }
  }
}