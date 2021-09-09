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
      // IqGroupEvents::USER_PROFILE_DELETE => [['deletebw2Contact', 300]],
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
        $this->bw2ApiService->editContact($user->get('field_iq_group_bw2_id')->getValue(), $user);
      } else {
        $bw2_id = $this->bw2ApiService->createContact($user);
        $user->set('field_iq_group_bw2_id', $bw2_id);
      }
    }
  }

  //  /**
  //  * Delete a bw2 contact.
  //  *
  //  * @param \Drupal\iq_group\Event\IqGroupEvent $event
  //  *   The event.
  //  */
  // public function deletebw2Contact(IqGroupEvent $event) {
  //   if ($event && $event->getUser()->id()) {
  //     \Drupal::logger('iq_group_bw2')->notice('bw2 delete event triggered for ' . $event->getUser()->id());

  //     $user = $event->getUser();

  //     $bw2_id = $user->field_iq_group_bw2_id->value;

  //     if (!empty($bw2_id) || $bw2_id != 0) {
  //       $contact = $this->bw2ApiService->deleteContact($bw2_id);
  //     }
  //   }
  // }
}
