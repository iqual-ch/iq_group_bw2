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
      $uri = \Drupal::request()->getRequestUri();
      // If user is anonymous and the referer does not come from opt-in 
      // then it means the update came from the import task -> we do nothing
      if(\Drupal::currentUser()->isAnonymous() && strpos($uri, 'de/auth') === false){
        \Drupal::logger('iq_group_bw2')->notice('bw2 update event triggered by anonymous user - do nothing.');
      }
      else{
        /** @var \Drupal\user\UserInterface $user */
        $user = $event->getUser();
        if ($user->hasField('field_iq_group_bw2_id')){
          /**
           * 3 cases:
           * - newly created user, not active yet
           * - newly created user, active
           * - existing user (with bw2_id)
           */
          if (!empty($user->get('field_iq_group_bw2_id')->getValue())){
            $profile_data = $this->convertDataForBw2($user);
            $bw2_id = $user->get('field_iq_group_bw2_id')->getValue();
            $bw2_id = reset($bw2_id)['value'];
            $this->bw2ApiService->editContact($bw2_id, $profile_data);
          }
          if (empty($user->get('field_iq_group_bw2_id')->getValue()) && $user->isActive()){
            $profile_data = $this->convertDataForBw2($user);
            $bw2_id = $this->bw2ApiService->createContact($profile_data);
            $user->set('field_iq_group_bw2_id', $bw2_id);
          }
        }
      }
    }
  }


  /**
   * Helper function to convert the user data to the bw2 format.
   *  @var \Drupal\user\UserInterface $user
   */
  public function convertDataForBw2($user){
    $langCode = $this->bw2ApiService->getLanguageCode($user->getPreferredLangcode());
    $newsletter = ($user->hasField('field_iq_group_preferences') && !$user->get('field_iq_group_preferences')->isEmpty()) ? true : false;
    $address = $user->get('field_iq_user_base_address')->getValue();
    $address = reset($address);
    $countryCode = $this->bw2ApiService->getCountryCode($address['country_code']);
    $salutation = $user->get('field_iq_user_base_salutation')->getValue();
    $salutation = reset($salutation);
    $pobox = $user->get('field_iq_user_base_adress_pobox')->getValue();
    $pobox = reset($pobox);
    $birthdate = $user->get('field_gcb_custom_birth_date')->getValue();
    $birthdate = reset($birthdate);
    $profile_data = [
      'Account_Active' => $user->status->value,
      'Account_Salutation' => $salutation['value'],
      'Account_FirstName' => $address['given_name'],
      'Account_LastName' => $address['family_name'],
      'Account_AddressLine1' => $address['address_line1'],
      'Account_Street' => $address['address_line2'],
      'Account_POBox' => $pobox['value'],
      'Account_PostalCode' => $address['postal_code'],
      'Account_City' => $address['locality'],
      'Account_Country_Dimension_ID' => $countryCode,
      'Account_Email1' => $user->getEmail(),
      'Account_Language_Dimension_ID' => $langCode,
      'Visitor_AllowEmail' => $newsletter,
      'Account_Birthday' => $birthdate['value']
    ];

    return $profile_data;
  }

}
