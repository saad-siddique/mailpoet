<?php

namespace MailPoet\Config;

use MailPoet\Settings\SettingsController;
use MailPoet\Settings\TrackingConfig;
use MailPoet\Util\Url;
use MailPoet\WooCommerce\Helper;
use MailPoet\WP\Functions as WPFunctions;

class Changelog {
  /** @var WPFunctions */
  private $wp;

  /** @var SettingsController */
  private $settings;

  /** @var Helper */
  private $wooCommerceHelper;

  /** @var Url */
  private $urlHelper;

  /** @var MP2Migrator */
  private $mp2Migrator;

  /** @var TrackingConfig */
  private $trackingConfig;

  public function __construct(
    SettingsController $settings,
    WPFunctions $wp,
    Helper $wooCommerceHelper,
    Url $urlHelper,
    MP2Migrator $mp2Migrator,
    TrackingConfig $trackingConfig
  ) {
    $this->wooCommerceHelper = $wooCommerceHelper;
    $this->settings = $settings;
    $this->wp = $wp;
    $this->urlHelper = $urlHelper;
    $this->mp2Migrator = $mp2Migrator;
    $this->trackingConfig = $trackingConfig;
  }

  public function init() {
    $doingAjax = (bool)(defined('DOING_AJAX') && DOING_AJAX);

    // don't run any check when it's an ajax request
    if ($doingAjax) {
      return;
    }

    // don't run any check when we're not on our pages
    if (
      !(isset($_GET['page']))
      or
      (isset($_GET['page']) && strpos($_GET['page'], 'mailpoet') !== 0)
    ) {
      return;
    }

    WPFunctions::get()->addAction(
      'admin_init',
      [$this, 'check']
    );
  }

  public function check() {
    $version = $this->settings->get('version');
    $this->checkMp2Migration();
    if ($version === null) {
      $this->setupNewInstallation();
      $this->checkWelcomeWizard();
    }
    $this->checkWooCommerceListImportPage();
    $this->checkRevenueTrackingPermissionPage();
  }

  public function shouldShowWelcomeWizard() {
    if ($this->wp->applyFilters('mailpoet_skip_welcome_wizard', false)) {
      return false;
    }
    return $this->settings->get('version') === null;
  }

  public function shouldShowWooCommerceListImportPage() {
    if ($this->wp->applyFilters('mailpoet_skip_woocommerce_import_page', false)) {
      return false;
    }
    return !$this->settings->get('woocommerce_import_screen_displayed')
      && $this->wooCommerceHelper->isWooCommerceActive()
      && $this->wooCommerceHelper->getOrdersCountCreatedBefore($this->settings->get('installed_at')) > 0
      && $this->wp->currentUserCan('administrator');
  }

  public function shouldShowRevenueTrackingPermissionPage() {
    return ($this->settings->get('woocommerce.accept_cookie_revenue_tracking.set') === null)
      && $this->trackingConfig->isEmailTrackingEnabled()
      && $this->wooCommerceHelper->isWooCommerceActive()
      && $this->wp->currentUserCan('administrator');
  }

  public function isMp2MigrationInProgress() {
    return $this->mp2Migrator->isMigrationStartedAndNotCompleted();
  }

  public function shouldShowMp2Migration() {
    return $this->settings->get('version') === null && $this->mp2Migrator->isMigrationNeeded();
  }

  private function checkMp2Migration() {
    if (!in_array($_GET['page'], ['mailpoet-migration', 'mailpoet-settings']) && $this->isMp2MigrationInProgress()) {
      // Force the redirection if the migration has started but is not completed
      $this->terminateWithRedirect($this->wp->adminUrl('admin.php?page=mailpoet-migration'));
    }

    if ($this->shouldShowMp2Migration()) {
      $this->terminateWithRedirect($this->wp->adminUrl('admin.php?page=mailpoet-migration'));
    }
  }

  private function setupNewInstallation() {
    $this->settings->set('show_congratulate_after_first_newsletter', true);
  }

  private function checkWelcomeWizard() {
    if ($this->shouldShowWelcomeWizard()) {
      $this->terminateWithRedirect($this->wp->adminUrl('admin.php?page=mailpoet-welcome-wizard'));
    }
  }

  private function checkWooCommerceListImportPage() {
    if (
      !in_array($_GET['page'], ['mailpoet-woocommerce-setup', 'mailpoet-welcome-wizard', 'mailpoet-migration'])
      && $this->shouldShowWooCommerceListImportPage()
    ) {
      $this->urlHelper->redirectTo($this->wp->adminUrl('admin.php?page=mailpoet-woocommerce-setup'));
    }
  }

  private function checkRevenueTrackingPermissionPage() {
    if (
      !in_array($_GET['page'], ['mailpoet-woocommerce-setup', 'mailpoet-welcome-wizard', 'mailpoet-migration'])
      && $this->shouldShowRevenueTrackingPermissionPage()
    ) {
      $this->urlHelper->redirectTo($this->wp->adminUrl('admin.php?page=mailpoet-woocommerce-setup'));
    }
  }

  private function terminateWithRedirect($redirectUrl) {
    // save version number
    $this->settings->set('version', Env::$version);
    $this->urlHelper->redirectWithReferer($redirectUrl);
  }
}
