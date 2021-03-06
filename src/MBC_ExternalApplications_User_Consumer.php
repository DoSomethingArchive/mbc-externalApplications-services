<?php
/**
 * MBC_ExternalApplications_User: Class to perform user registration activities
 * submitted by external applications.
 */

namespace DoSomething\MBC_ExternalApplications;

use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;

/**
 * MBC_ExternalApplications_User class - functionality related to the Message Broker
 * consumer mbc-externalApplications-user.
 */
class MBC_ExternalApplications_User
{

  /**
   * Access credentials settings
   *
   * @var object
   */
  private $credentials;

  /**
   * Service settings
   *
   * @var array
   */
  private $settings;

  /**
   * Setting from external services - StatHat.
   *
   * @var object
   */
  private $toolbox;

  /**
   * Setting from external services - StatHat.
   *
   * @var object
   */
  private $statHat;

  /**
   * Constructor for MBC_ExternalApplications_User
   *
   * @param array $credentials
   *   Connection credentials
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
   * Consumer entries in externalApplicationUserQueue
   *
   * @param string $payload
   *   The contents of the message in a serial format
   */
  public function consumeQueue($payload) {

    echo '------- mbc-externalApplication->MBC_ExternalApplications_User->consumeQueue() START:' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;

    $message = unserialize($payload->body);

    $isAffiliate = FALSE;
    if (isset($message['country_code'])) {
      $isAffiliate = $this->toolbox->isDSAffiliate($message['country_code']);
    }

    // US based user created, Send SMS
    if ($message['mobile'] !== NULL) {
      $this->produceUSUser($message);
    }
    elseif ($message['email'] !== NULL && $isAffiliate) {
      $this->produceInternationalAffilateUser($message);
    }
    elseif ($message['email'] !== NULL) {
      $this->produceInternationalUser($message);
    }
    else {
      echo 'ERROR consumerQueue: email or mobile not defined - $message: ' . print_r($message, TRUE), PHP_EOL;
    }

    echo '------- mbc-externalApplication->MBC_ExternalApplications_User->consumeQueue() END:' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;
  }

  /**
   * Produce domestic (US) based users creation transaction.
   *
   * @param array $message
   *   Details about the transaction for US based signups.
   */
  private function produceUSUser($message) {

    $mbConfig = new MB_Configuration($this->settings, CONFIG_PATH . '/mb_config.json');
    $config = $mbConfig->constructConfig('transactionalExchange', array('mobileCommonsQueue'));
    $config['routingKey'] = 'user.registration.agg';

    $mbMobileCommons = new \MessageBroker($this->credentials, $config);
    $payload = serialize($message);
    $mbMobileCommons->publishMessage($payload);

    echo '- produceUSUser() - SMS message sent to queue: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;
    $this->statHat->ezCount('mbc-externalApplications-user: produceUSUser - mobile', 1);
  }

  /**
   * Produce international affiliate users.
   *
   * @param array $message
   *   Details about the transaction that has triggered producing international,
   *   Message Broker functionality.
   */
  private function produceInternationalAffilateUser($message) {
    echo 'produceInternationalAffilateUser - ' . $message['email'], PHP_EOL;
  }

  /**
   * Produce international users.
   *
   * @param array $message
   *   Details about the transaction that has triggered producing international,
   *   non-affiliate Message Broker functionality.
   */
  private function produceInternationalUser($message) {
    echo 'produceInternationalUser - ' . $message['email'] . ': ' . $message['country_code'], PHP_EOL;
  }

}
