<?php

namespace Drupal\iq_group_bw2\EventSubscriber;

use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
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
      $profile_data = $this->convertDataForBw2($user);
      if ($user->hasField('field_iq_group_bw2_id') && !empty($user->get('field_iq_group_bw2_id')->getValue())) {
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
    $newsletter = ($user->hasField('field_iq_group_preferences') && !$user->get('field_iq_group_preferences')->isEmpty()) ? true : false;
    $address = $user->get('field_iq_user_base_address')->getValue();
    $address = reset($address);
    $profile_data = [
      'Account_Active' => $user->status->value,
      // 'Account_Salutation' => ,
      // 'Account_Drupal_ID' => ,
      'Account_FirstName' => $address['given_name'],
      'Account_LastName' => $address['family_name'],
      // 'Account_AddressLine1' => ,
      'Account_Street' => $address['address_line1'],
      // 'Account_POBox' => ,
      'Account_PostalCode' => $address['postal_code'],
      'Account_City' => $address['locality'],
      'Account_Country_Dimension_ID' => $countryCode,
      'Account_Email1' => $user->getEmail(),
      'Account_Language_Dimension_ID' => $langCode,
      'Visitor_AllowEmail' => $newsletter
    ];

    return $profile_data;
  }

}
