<?php
/**
 * MBC_ExternalApplications_Events: Class to perform user event activities
 * submitted by external applications.
 */
namespace DoSomething\MBC_ExternalApplications;

use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MBC_ExternalApplications\MBC_ExternalApplications_Events_CGG;
use DoSomething\MBC_ExternalApplications\MBC_ExternalApplications_Events_AGG;

/**
 * MBC_UserEvent class - functionality related to the Message Broker
 * producer mbp-user-event.
 */
class MBC_ExternalApplications_Events
{

  /**
   * Setting from external services - StatHat.
   *
   * @var object
   */
  private $toolbox;

  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $statHat;

  /**
   * Constructor for MBC_UserEvent
   *
   * @param array $settings
   *   Settings of additional services used by the class.
   */
  public function __construct($credentials, $settings) {

    $this->credentials = $credentials;
    $this->settings = $settings;

    $this->toolbox = new MB_Toolbox($settings);
    $this->statHat = new StatHat([
      'ez_key' => $settings['stathat_ez_key'],
      'debug' => $settings['stathat_disable_tracking']
    ]);
  }

  /* 
   * Consume entries in externalApplicationEventQueue. Events are activities that are not specific to managing a user account.
   *
   * Currently supports external applications:
   *   - Celebrities Gone Good (CGG)
   *   - Athletes Gone Good (AGG)
   */
  public function consumeQueue($payload) {

    echo '------- mbc-externalApplication->MBC_ExternalApplications_Events->consumeQueue() START: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;

    $message = unserialize($payload->body);

    switch ($message['application_id']) {

      case 'CGG':

        $isAffiliate = FALSE;
        if (isset($message['country_code'])) {
          $isAffiliate = $this->toolbox->isDSAffiliate($message['country_code']);
        }

        $mbcExternalApplicationsEvents_CGG = new MBC_ExternalApplications_Events_CGG();

        if ($message['email'] !== NULL && $isAffiliate) {
          $$mbcExternalApplicationsEvents_CGG->produceInternationalAffilateEvent($message, $isAffiliate);
        }
        elseif ($message['email'] !== NULL) {
          $$mbcExternalApplicationsEvents_CGG->produceInternationalEvent($message);
        }
        elseif ($message['email'] === NULL && isset($message['mobile'])) {
          $$mbcExternalApplicationsEvents_CGG->produceUSEvent($message);
          echo 'mobile vote - ' . $message['mobile'] . ': ' . $message['country_code'], PHP_EOL;
        }
        else {
          echo 'ERROR consumeQueue: email not defined - $message: ' . print_r($message, TRUE), PHP_EOL;
        }

        break;

      case 'AGG':

        $mbcExternalApplicationsEvents_AGG = new MBC_ExternalApplications_Events_AGG();

        if ($message['country_code'] == 'US' && isset($message['mobile'])) {
          $mbcExternalApplicationsEvents_AGG->produceUSEvent($message);
        }
        elseif ($message['country_code'] != 'US' && isset($message['email'])) {
          $mbcExternalApplicationsEvents_AGG->produceInternationalEvent($message);
          $this->logEvent($message);
        }
        else {
          echo 'ERROR consumeQueue for AGG, missing required message payload items, country_code and mobile/email - $message: ' . print_r($message, TRUE), PHP_EOL;
        }

        break;

      default:
        echo '** MBC_ExternalApplications_Events->consumeQueue() - ERROR - Undefined application_id: ' . print_r($message, YRUE), PHP_EOL;
    }

    echo '------- mbc-externalApplication->MBC_ExternalApplications_Events->consumeQueue() END: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;
  }}
