<?php
namespace MailPoet\Mailer;

use MailPoet\Models\Setting;

if(!defined('ABSPATH')) exit;

class MailerLog {
  const SETTING_NAME = 'mta_log';
  const STATUS_PAUSED = 'paused';
  const RETRY_ATTEMPTS_LIMIT = 3;
  const RETRY_INTERVAL = 120; // seconds

  static function getMailerLog($mailer_log = false) {
    if($mailer_log) return $mailer_log;
    $mailer_log = Setting::getValue(self::SETTING_NAME);
    if(!$mailer_log) {
      $mailer_log = self::createMailerLog();
    }
    return $mailer_log;
  }

  static function createMailerLog() {
    $mailer_log = array(
      'sent' =>null,
      'started' => time(),
      'status' => null,
      'retry_attempt' => null,
      'retry_at' => null,
      'error' => null
    );
    Setting::setValue(self::SETTING_NAME, $mailer_log);
    return $mailer_log;
  }

  static function resetMailerLog() {
    return self::createMailerLog();
  }

  static function updateMailerLog($mailer_log) {
    Setting::setValue(self::SETTING_NAME, $mailer_log);
    return $mailer_log;
  }

  static function enforceExecutionRequirements($mailer_log = false) {
    $mailer_log = self::getMailerLog($mailer_log);
    if($mailer_log['retry_attempt'] === self::RETRY_ATTEMPTS_LIMIT) {
      $mailer_log = self::pauseSending($mailer_log);
    }
    if($mailer_log['status'] === self::STATUS_PAUSED) {
      throw new \Exception(__('Sending has been paused.', 'mailpoet'));
    }
    if(!is_null($mailer_log['retry_at'])) {
      if(time() <= $mailer_log['retry_at']) {
        throw new \Exception(__('Sending is waiting to be retried.', 'mailpoet'));
      } else {
        $mailer_log['retry_at'] = null;
        self::updateMailerLog($mailer_log);
      }
    }
    // ensure that sending frequency has not been reached
    if(self::isSendingLimitReached($mailer_log)) {
      throw new \Exception(__('Sending frequency limit has been reached.', 'mailpoet'));
    }
  }

  static function pauseSending($mailer_log) {
    $mailer_log['status'] = self::STATUS_PAUSED;
    $mailer_log['retry_attempt'] = null;
    $mailer_log['retry_at'] = null;
    return self::updateMailerLog($mailer_log);
  }

  static function resumeSending() {
    return self::resetMailerLog();
  }

  static function processSendingError($operation, $error_message) {
    $mailer_log = self::getMailerLog();
    (int)$mailer_log['retry_attempt']++;
    $mailer_log['retry_at'] = time() + self::RETRY_INTERVAL;
    $mailer_log['error'] = array(
      'operation' => $operation,
      'error_message' => $error_message
    );
    self::updateMailerLog($mailer_log);
    return self::enforceExecutionRequirements();
  }

  static function incrementSentCount() {
    $mailer_log = self::getMailerLog();
    (int)$mailer_log['sent']++;
    return self::updateMailerLog($mailer_log);
  }

  static function isSendingLimitReached($mailer_log = false) {
    $mailer_config = Mailer::getMailerConfig();
    // do not enforce sending limit for MailPoet's sending method
    if($mailer_config['method'] === Mailer::METHOD_MAILPOET) return false;
    $mailer_log = self::getMailerLog($mailer_log);
    $elapsed_time = time() - (int)$mailer_log['started'];
    if($mailer_log['sent'] === $mailer_config['frequency_limit']) {
      if($elapsed_time <= $mailer_config['frequency_interval']) return true;
      // reset mailer log if enough time has passed since the limit was reached
      self::resetMailerLog();
    }
    return false;
  }
}