<?php
/**
 * Configuration settings for mbc-externalApplication-events.
 *
 * Message Broker configuration settings for mbc-externalApplication-events
 * application. The MB_Configuration class is used to create a singleton instance that
 * can be referenced anywhere in the application for application configuration settings.
 *
 * @package mbc-externalApplication-services
 * @link    https://github.com/DoSomething/mbc-externalApplication-services
 */
 
use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_RabbitMQManagementAPI;
use DoSomething\StatHat\Client as StatHat;

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/messagebroker-config/mb-secure-config.inc';

$mbConfig = MB_Configuration::getInstance();

$mbConfig->setProperty('statHat', new StatHat([
  'ez_key' => getenv("STATHAT_EZKEY"),
  'debug' => getenv("DISABLE_STAT_TRACKING")
]));

$mbConfig->setProperty('rabbit_credentials', [
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
]);
$rabbitCredentials = $mbConfig->getProperty('rabbit_credentials');
$mbConfig->setProperty('rabbitapi_credentials', [
  'host' =>  getenv("MB_RABBITMQ_MANAGEMENT_API_HOST"),
  'port' => getenv("MB_RABBITMQ_MANAGEMENT_API_PORT"),
  'username' => getenv("MB_RABBITMQ_MANAGEMENT_API_USERNAME"),
  'password' => getenv("MB_RABBITMQ_MANAGEMENT_API_PASSWORD"),
]);

// Create connection to exchange and queue for processing of queue contents.
$mbRabbitConfig = $mbConfig->constructRabbitConfig('directExternalApplicationsExchange', ['externalApplicationEventQueue']);
$mbConfig->setProperty('messageBroker', new MessageBroker($rabbitCredentials, $mbRabbitConfig));

// Connection to service (MailChimp, Mobile Commons and mb-user-api related queues)
$mbRabbitConfig = $mbConfig->constructRabbitConfig('transactionalExchange', ['transactionalQueue', 'mobileCommonsQueue', 'userAPIRegistrationQueue', 'userRegistrationQueue']);
$mbConfig->setProperty('messageBrokerServices', new MessageBroker($rabbitCredentials, $mbRabbitConfig));

$mbConfig->setProperty('mbToolbox', new MB_Toolbox());


$mbConfig->setProperty('ds_drupal_api_config', [
  'host' => getenv("DS_DRUPAL_API_HOST"),
  'port' => getenv('DS_DRUPAL_API_PORT'),
  'username' => getenv("DS_DRUPAL_API_USERNAME"),
  'password' => getenv('DS_DRUPAL_API_PASSWORD'),
]);
$mbConfig->setProperty('mbRabbitMQManagementAPI', new MB_RabbitMQManagementAPI([
  'domain' => getenv("MB_RABBITMQ_MANAGEMENT_API_HOST"),
  'port' => getenv('MB_RABBITMQ_MANAGEMENT_API_PORT'),
  'vhost' => getenv('MB_RABBITMQ_MANAGEMENT_API_VHOST'),
  'username' => getenv('MB_RABBITMQ_MANAGEMENT_API_USERNAME'),
  'password' => getenv('MB_RABBITMQ_MANAGEMENT_API_PASSWORD')
]));
