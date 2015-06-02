<?php

use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;

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
    $this->statHat = new StatHat($settings['stathat_ez_key'], 'mbc-externalApplications-user:');
    $this->statHat->setIsProduction(TRUE);
  }

  /* 
   * Consumer entries in externalApplicationUserQueue
   *
   * @param string $payload
   *   The contents of the message in a serial format
   */
  public function consumeQueue($payload) {
    echo '------- mbc-externalApplication-users->consumeQueue() START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

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

    echo '------- mbc-externalApplication-users->consumeQueue() END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
  }

  /**
   * Produce domestic (US) based users creation transaction.
   *
   * @param array $message
   *   Details about the transaction for US based signups.
   */
  private function produceUSUser($message) {

    $config = array();
    $source = __DIR__ . '/messagebroker-config/mb_config.json';
    $mb_config = new MB_Configuration($source, $this->settings);
    $transactionalExchange = $mb_config->exchangeSettings('transactionalExchange');

    $config['exchange'] = array(
      'name' => $transactionalExchange->name,
      'type' => $transactionalExchange->type,
      'passive' => $transactionalExchange->passive,
      'durable' => $transactionalExchange->durable,
      'auto_delete' => $transactionalExchange->auto_delete,
    );
    $config['queue'][] = array(
      'name' => $transactionalExchange->queues->mobileCommonsQueue->name,
      'passive' => $transactionalExchange->queues->mobileCommonsQueue->passive,
      'durable' => $transactionalExchange->queues->mobileCommonsQueue->durable,
      'exclusive' => $transactionalExchange->queues->mobileCommonsQueue->exclusive,
      'auto_delete' => $transactionalExchange->queues->mobileCommonsQueue->auto_delete,
      'binding_pattern' => $transactionalExchange->queues->mobileCommonsQueue->binding_pattern,
    );
    $config['routing_key'] = 'user.registration.cgg';

    $mbMobileCommons = new MessageBroker($this->credentials, $config);
    $payload = serialize($message);
    $mbMobileCommons->publishMessage($payload);

    echo 'produceUSUser() - SMS message sent to queue: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

    $this->statHat->clearAddedStatNames();
    $this->statHat->addStatName('produceUSUser - mobile');
    $this->statHat->reportCount(1);
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