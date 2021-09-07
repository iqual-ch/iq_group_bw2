<?php

namespace Drupal\iq_group_bw2\EventSubscriber;

use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
use Drupal\iq_group\bw2Events;
use Drupal\iq_group\Event\bw2Event;
use Drupal\bw2_api\bw2ApiServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Event subscriber to handle bw2 events dispatched by iq_group module.
 */
class bw2Subscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\bw2_api\bw2ApiServiceInterface
   */
  protected $bw2ApiService;

  /**
   * OrderReceiptSubscriber constructor.
   *
   * @param \Drupal\bw2_api\bw2ApiServiceInterface $bw2_api_service
   */
  public function __construct(bw2ApiServiceInterface $bw2_api_service) {
    $this->bw2ApiService = $bw2_api_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      IqGroupEvents::USER_PROFILE_UPDATE => [['updatebw2Contact', 300]],
      IqGroupEvents::USER_PROFILE_DELETE => [['deletebw2Contact', 300]],
    ];
  }

  /**
   * Update a bw2 contact.
   *
   * @param \Drupal\iq_group\Event\IqGroupEvent $event
   *   The event.
   */
  public function updatebw2Contact(IqGroupEvent $event) {
    if ($event && $event->getUser()->id()) {
      \Drupal::logger('iq_group_bw2')->notice('bw2 update event triggered for ' . $event->getUser()->id());

      /** @var \Drupal\user\UserInterface $user */
      $user = $event->getUser();
      if ($user->status->value) {
        $bw2_id = $user->field_iq_group_bw2_id->value;

        $email = $user->getEmail();
        $profile_data = [
          'user_id' => $user->id(),
          'email' => $email,
          'langcode' => $user->getPreferredLangcode(),
          'ip_address' => \Drupal::request()->getClientIp(),
          "first_name" => reset($user->get('field_iq_user_base_address')->getValue())['given_name'],
          "last_name" => reset($user->get('field_iq_user_base_address')->getValue())['family_name'],
          'token' => $user->field_iq_group_user_token->value,
          "address" => reset($user->get('field_iq_user_base_address')->getValue())['address_line1'],
          "postcode" => reset($user->get('field_iq_user_base_address')->getValue())['postal_code'],
          "city" => reset($user->get('field_iq_user_base_address')->getValue())['locality']
        ];

        if ($user->hasField('field_gcb_custom_birth_date') && !$user->get('field_gcb_custom_birth_date')->isEmpty()) {
          $profile_data["birth_date"] = $user->field_gcb_custom_birth_date->value;
        }
        if ($user->hasField('field_iq_group_preferences') && !$user->get('field_iq_group_preferences')->isEmpty()) {
          $profile_data["preferences"] = array_filter(array_column($user->field_iq_group_preferences->getValue(), 'target_id'));
        }
        if ($user->hasField('field_iq_group_bw2_id') && !empty($user->get('field_iq_group_bw2_id')->getValue())) {
          $profile_data['bw2_id'] = $user->field_iq_group_bw2_id->value;
          $this->bw2ApiService->editContact($profile_data['bw2_id'], $profile_data);
        } else {
          $bw2_id = $this->bw2ApiService->createContact($email, $profile_data);
          $user->set('field_iq_group_bw2_id', $bw2_id);
        }
        // Delete from blacklist - because the user is active.
        $this->bw2ApiService->deleteFromBlacklist($email);
      }
      else if (!empty($user->field_iq_group_bw2_id->value)){
        $email = $user->getEmail();
        // Update blacklist if the user is blocked and there he is registered on bw2.
        $this->bw2ApiService->updateBlacklist($email);
      }

    }
  }

   /**
   * Delete a bw2 contact.
   *
   * @param \Drupal\iq_group\Event\IqGroupEvent $event
   *   The event.
   */
  public function deletebw2Contact(IqGroupEvent $event) {
    if ($event && $event->getUser()->id()) {
      \Drupal::logger('iq_group_bw2')->notice('bw2 delete event triggered for ' . $event->getUser()->id());

      $user = $event->getUser();

      $bw2_id = $user->field_iq_group_bw2_id->value;

      if (!empty($bw2_id) || $bw2_id != 0) {
        $contact = $this->bw2ApiService->deleteContact($bw2_id);
      }
    }
  }
}
