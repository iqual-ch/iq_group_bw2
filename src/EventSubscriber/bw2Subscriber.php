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
      IqGroupEvents::USER_PROFILE_UPDATE => [['updatebw2Contact', 300]]
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
      if ($user->hasField('field_iq_group_bw2_id') && !empty($user->get('field_iq_group_bw2_id')->getValue())) {
        $profile_data = $this->convertDataForBw2($user);
        $this->bw2ApiService->editContact($user->get('field_iq_group_bw2_id')->getValue(), $profile_data);
      } else {
        $bw2_id = $this->bw2ApiService->createContact($profile_data);
        $user->set('field_iq_group_bw2_id', $bw2_id);
      }
    }
  }

  /**
   * Helper function to convert the user data to the bw2 format.
   */
  public function convertDataForBw2($user){
    $langCode = $this->bw2ApiService->getLanguageCode($user->getPreferredLangcode());
    $countryCode = $this->bw2ApiService->getCountryCode(reset($user->get('field_iq_user_base_address')->getValue())['country_code']);
    $profile_data = [
      'Account_Active' => $user->status->value,
      // 'Account_Salutation' => reset($user->get('field_iq_user_base_address')->getValue())['given_name'],
      // 'Account_Drupal_ID' => $user->id(),
      'Account_FirstName' => reset($user->get('field_iq_user_base_address')->getValue())['given_name'],
      'Account_LastName' => reset($user->get('field_iq_user_base_address')->getValue())['family_name'],
      // 'Account_AddressLine1' => reset($user->get('field_iq_user_base_address')->getValue())['address_line1'],
      'Account_Street' => reset($user->get('field_iq_user_base_address')->getValue())['address_line1'],
      // 'Account_POBox' => reset($user->get('field_iq_user_base_address')->getValue())['street'],
      'Account_PostalCode' => reset($user->get('field_iq_user_base_address')->getValue())['postal_code'],
      'Account_City' => reset($user->get('field_iq_user_base_address')->getValue())['locality'],
      'Account_Country_Dimension_ID' => $countryCode,
      'Account_Email1' => $user->getEmail(),
      'Account_Language_Dimension_ID' => $langCode
    ];

    if ($user->hasField('field_iq_group_preferences') && !$user->get('field_iq_group_preferences')->isEmpty()) {
      $profile_data['Visitor_AllowEmail'] = array_filter(array_column($user->field_iq_group_preferences->getValue(), 'target_id'));
    }
    // if ($user->hasField('field_iq_group_bw2_id') && !empty($user->get('field_iq_group_bw2_id')->getValue())) {
    //   $this->bw2ApiService->editContact($user->field_iq_group_bw2_id->value, $profile_data);
    // } else {
    //   $bw2_id = $this->bw2ApiService->createContact($email, $profile_data);
    //   $user->set('field_iq_group_bw2_id', $bw2_id);
    // }
    return $profile_data;
  }

}
