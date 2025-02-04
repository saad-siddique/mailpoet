<?php

namespace MailPoet\Util;

class CdnAssetUrl {
  const CDN_URL = 'https://ps.w.org/mailpoet/';
  /** @var string */
  private $baseUrl;

  public function __construct(
    string $baseUrl
  ) {
    $this->baseUrl = $baseUrl;
  }

  public function generateCdnUrl($path) {
    $useCdn = defined('MAILPOET_USE_CDN') ? MAILPOET_USE_CDN : true;
    return ($useCdn ? self::CDN_URL : $this->baseUrl . '/plugin_repository/') . "assets/$path";
  }
}
