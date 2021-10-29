<?php

namespace MailPoet\Jetpack;

use Automattic\Jetpack\Connection\Client;
use Automattic\Jetpack\Connection\Manager;
use WP_Error;

class JetpackFollowersHelper {
  public static function getEmailFollowers() {
    if (!class_exists('\Automattic\Jetpack\Connection\Manager')) {
      return new WP_Error('Jetpack error', __( 'Can not get email followers because jetpack is not installed', 'Mailpoet' ));
    }
    $connection = new Manager();
    if ( !$connection->is_connected()) {
      return new WP_Error('Jetpack error', __( 'Can not get email followers because jetpack is not connected', 'Mailpoet' ));
    }
    // @todo handle pagination and return the full list of emails
    $url      = sprintf( '/sites/%d/stats/followers?type=email', \Jetpack_Options::get_option( 'id' ) );
    $response = Client::wpcom_json_api_request_as_blog( $url, '1.1' );
    if ( is_wp_error( $response ) ) {
      return $response;
    }

    return json_decode( wp_remote_retrieve_body( $response ) );
  }
}
