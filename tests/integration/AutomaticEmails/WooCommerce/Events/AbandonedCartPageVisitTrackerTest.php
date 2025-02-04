<?php

namespace MailPoet\AutomaticEmails\WooCommerce\Events;

use Codeception\Stub;
use MailPoet\Settings\SettingsController;
use MailPoet\Settings\TrackingConfig;
use MailPoet\Statistics\Track\SubscriberCookie;
use MailPoet\Util\Cookies;
use MailPoet\WooCommerce\Helper as WooCommerceHelper;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Session;
use WooCommerce;
use WP_User;

class AbandonedCartPageVisitTrackerTest extends \MailPoetTest {
  /** @var Carbon */
  private $currentTime;

  /** @var WPFunctions|MockObject */
  private $wp;

  /** @var mixed[] */
  private $sessionStore = [];

  /** @var AbandonedCartPageVisitTracker */
  private $pageVisitTracker;

  public function _before() {
    $this->currentTime = Carbon::now();
    Carbon::setTestNow($this->currentTime);

    /** @var WPFunctions|MockObject $wp - for phpstan*/
    $wp = $this->makeEmpty(WPFunctions::class, [
      'currentTime' => $this->currentTime->getTimestamp(),
    ]);
    $this->wp = $wp;

    $wooCommerceMock = $this->mockWooCommerceClass(WooCommerce::class, []);
    $wooCommerceMock->session = $this->createWooCommerceSessionMock();
    $wooCommerceHelperMock = $this->make(WooCommerceHelper::class, [
      'isWooCommerceActive' => true,
      'WC' => $wooCommerceMock,
    ]);

    $settings = $this->diContainer->get(SettingsController::class);
    $this->sessionStore = [];
    $this->pageVisitTracker = new AbandonedCartPageVisitTracker(
      $this->wp,
      $wooCommerceHelperMock,
      new SubscriberCookie(new Cookies(), new TrackingConfig($settings))
    );
  }

  public function testItSetsTimestampWhenTrackingStarted() {
    $this->pageVisitTracker->startTracking();
    expect($this->sessionStore['mailpoet_last_visit_timestamp'])->same($this->currentTime->getTimestamp());
  }

  public function testItDeletesTimestampWhenTrackingStopped() {
    $this->pageVisitTracker->stopTracking();
    expect($this->sessionStore)->isEmpty();
  }

  public function testItTracks() {
    $this->wp->method('isAdmin')->willReturn(false);
    $this->wp->method('wpGetCurrentUser')->willReturn(
      $this->makeEmpty(WP_User::class, ['exists' => true])
    );

    $hourAgoTimestamp = $this->currentTime->getTimestamp() - 60 * 60;
    $this->sessionStore['mailpoet_last_visit_timestamp'] = $hourAgoTimestamp;

    $trackingCallbackExecuted = false;
    $this->pageVisitTracker->trackVisit(function () use (&$trackingCallbackExecuted) {
      $trackingCallbackExecuted = true;
    });
    expect($this->sessionStore['mailpoet_last_visit_timestamp'])->same($this->currentTime->getTimestamp());
    expect($trackingCallbackExecuted)->true();
  }

  public function testItTracksByCookie() {
    $this->wp->method('isAdmin')->willReturn(false);
    $this->wp->method('wpGetCurrentUser')->willReturn(
      $this->makeEmpty(WP_User::class, ['exists' => false])
    );
    $_COOKIE['mailpoet_subscriber'] = json_encode(['subscriber_id' => '123']);

    $hourAgoTimestamp = $this->currentTime->getTimestamp() - 60 * 60;
    $this->sessionStore['mailpoet_last_visit_timestamp'] = $hourAgoTimestamp;
    $this->pageVisitTracker->trackVisit();
    expect($this->sessionStore['mailpoet_last_visit_timestamp'])->same($this->currentTime->getTimestamp());
  }

  public function testItTracksByLegacyCookie() {
    $this->wp->method('isAdmin')->willReturn(false);
    $this->wp->method('wpGetCurrentUser')->willReturn(
      $this->makeEmpty(WP_User::class, ['exists' => false])
    );

    $cookiesMock = $this->createMock(Cookies::class);
    $cookiesMock->method('get')->willReturnCallback(function (string $name) {
      if ($name === 'mailpoet_abandoned_cart_tracking') {
        return ['subscriber_id' => '123'];
      }
      return null;
    });
    $cookiesMock->expects($this->once())->method('set')->with('mailpoet_subscriber', ['subscriber_id' => '123']);
    $cookiesMock->expects($this->once())->method('delete')->with('mailpoet_abandoned_cart_tracking');

    $settings = $this->diContainer->get(SettingsController::class);
    $pageVisitTracker = Stub::copy($this->pageVisitTracker, [
      'subscriberCookie' => new SubscriberCookie($cookiesMock, new TrackingConfig($settings)),
    ]);

    $hourAgoTimestamp = $this->currentTime->getTimestamp() - 60 * 60;
    $this->sessionStore['mailpoet_last_visit_timestamp'] = $hourAgoTimestamp;
    $pageVisitTracker->trackVisit();
    expect($this->sessionStore['mailpoet_last_visit_timestamp'])->same($this->currentTime->getTimestamp());
  }

  public function testItDoesNotTrackWhenUserNotFound() {
    $this->wp->method('isAdmin')->willReturn(false);
    $this->wp->method('wpGetCurrentUser')->willReturn(
      $this->makeEmpty(WP_User::class, ['exists' => false])
    );

    $hourAgoTimestamp = $this->currentTime->getTimestamp() - 60 * 60;
    $this->sessionStore['mailpoet_last_visit_timestamp'] = $hourAgoTimestamp;
    $this->pageVisitTracker->trackVisit();
    expect($this->sessionStore['mailpoet_last_visit_timestamp'])->same($hourAgoTimestamp);
  }

  public function testItDoesNotTrackAdminPage() {
    $this->wp->method('isAdmin')->willReturn(true);
    $this->wp->method('wpGetCurrentUser')->willReturn(
      $this->makeEmpty(WP_User::class, ['exists' => true])
    );

    $hourAgoTimestamp = $this->currentTime->getTimestamp() - 60 * 60;
    $this->sessionStore['mailpoet_last_visit_timestamp'] = $hourAgoTimestamp;
    $this->pageVisitTracker->trackVisit();
    expect($this->sessionStore['mailpoet_last_visit_timestamp'])->same($hourAgoTimestamp);
  }

  public function testItDoesNotTrackMultipleTimesPerMinute() {
    $tenSecondsAgoTimestamp = $this->currentTime->getTimestamp() - 10;
    $this->sessionStore['mailpoet_last_visit_timestamp'] = $tenSecondsAgoTimestamp;
    $this->pageVisitTracker->trackVisit();
    expect($this->sessionStore['mailpoet_last_visit_timestamp'])->same($tenSecondsAgoTimestamp);
  }

  private function createWooCommerceSessionMock() {
    $mock = $this->mockWooCommerceClass(WC_Session::class, ['get', 'set', '__unset']);

    $mock->method('get')->willReturnCallback(function ($key) {
      return isset($this->sessionStore[$key]) ? $this->sessionStore[$key] : null;
    });
    $mock->method('set')->willReturnCallback(function ($key, $value) {
      $this->sessionStore[$key] = $value;
    });
    $mock->method('__unset')->willReturnCallback(function ($key) {
      unset($this->sessionStore[$key]);
    });
    return $mock;
  }

  /**
   * @param class-string<WooCommerce|WC_Session> $className
   */
  private function mockWooCommerceClass($className, array $methods) {
    // WooCommerce class needs to be mocked without default 'disallowMockingUnknownTypes'
    // since WooCommerce may not be active (would result in error mocking undefined class)
    return $this->getMockBuilder($className)
      ->disableOriginalConstructor()
      ->disableOriginalClone()
      ->disableArgumentCloning()
      ->setMethods($methods)
      ->getMock();
  }

  public function _after() {
    Carbon::setTestNow();
  }
}
