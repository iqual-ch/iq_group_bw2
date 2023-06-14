<?php

namespace Drupal\iq_group_bw2\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * IQ Group BW2 Webform submission handler.
 *
 * If added to a webform and active
 * this will register the user in bw2.
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
class IqGroupBw2WebformSubmissionHandler extends WebformHandlerBase {

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $user_data = [];
    $userExists = TRUE;
    $user_manager = \Drupal::service('iq_group.user_manager');

    $user = NULL;

    if ($form_state->getValue('customer_mail')) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(
        [
          'mail' => $form_state->getValue('customer_mail'),
        ]
      );
      if (count($user) == 0) {
        $userExists = FALSE;
        $user_data['name'] = $form_state->getValue('customer_mail');
        $user_data['mail'] = $user_data['name'];
        $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
        ;
        $user_data['preferred_langcode'] = $currentLanguage;
        $user_data['langcode'] = $currentLanguage;
      }
      else {
        $user = reset($user);
      }
    }
    if ($form_state->getValue('customer_salutation')) {
      $user_data['field_iq_group_salutation'] = $form_state->getValue('customer_salutation');
    }
    if ($form_state->getValue('customer_first_name')) {
      $user_data['field_iq_user_base_address']['given_name'] = $form_state->getValue('customer_first_name');
    }
    if ($form_state->getValue('customer_last_name')) {
      $user_data['field_iq_user_base_address']['family_name'] = $form_state->getValue('customer_last_name');
    }
    if ($form_state->getValue('customer_address')) {
      $user_data['field_iq_user_base_address']['address_line1'] = $form_state->getValue('customer_address');
    }
    if ($form_state->getValue('customer_address_2')) {
      $user_data['field_iq_user_base_address']['address_line2'] = $form_state->getValue('customer_address_2');
    }
    if ($form_state->getValue('customer_city')) {
      $user_data['field_iq_user_base_address']['locality'] = $form_state->getValue('customer_city');
    }
    if ($form_state->getValue('customer_postal_code')) {
      $user_data['field_iq_user_base_address']['postal_code'] = $form_state->getValue('customer_postal_code');
    }
    if ($form_state->getValue('customer_country')) {
      $user_data['field_iq_user_base_address']['country_code'] = $form_state->getValue('customer_country');
    }

    // Set the country code to Switzerland as it is required.
    if (empty($user_data['field_iq_user_base_address']['country_code'])) {
      $user_data['field_iq_user_base_address']['country_code'] = 'CH';
    }

    // If user exists, attribute the submission to the user.
    if (!empty($user) && $userExists) {
      $webform_submission->setOwnerId($user->id());
    }

    /*
     * If the user does not exists,
     * Create the user, register the user to the iq groups
     * and attribute the submission to the user.
     */
    if (!empty(\Drupal::config('iq_group.settings')->get('default_redirection'))) {
      $destination = \Drupal::config('iq_group.settings')->get('default_redirection');
    }
    else {
      $destination = '';
    }

    // Allow other module to modify the user object before save.
    \Drupal::moduleHandler()
      ->invokeAll('iq_group_bw2_before_submission',
        [
          &$user_data,
          $user,
          $form_state,
        ]
      );

    $user = $user_manager->createMember($user_data, [], $destination . '&source_form=' . rawurlencode($webform_submission->getWebform()->id()));
    $store = \Drupal::service('tempstore.shared')->get('iq_group.user_status');
    $store->set($user->id() . '_pending_activation', TRUE);
    $webform_submission->setOwnerId($user->id());
    $group_general = $user_manager->getGeneralGroup();
    $user_manager->addGroupRoleToUser($group_general, $user, 'subscription-subscriber');
    $this->getLogger('iq_group_bw2')->notice('user added to the general group');

    // Allow other module to perform other operation after submission.
    \Drupal::moduleHandler()
      ->invokeAll('iq_group_bw2_after_submission',
        [
          &$user_data,
          $user,
          $form_state,
        ]
      );

  }

}
