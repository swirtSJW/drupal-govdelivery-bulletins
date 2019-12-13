<?php

namespace Drupal\govdelivery_bulletins\Service;

/**
 * POST a GovDelivery bulletin to the API.
 *
 * Use like: `\Drupal::service('govdelivery_bulletins.send_bulletin')->send($xml_bulletin);`
 */
class SendBulletin {

  /**
   * Make the request.
   */
  public function send($xml) {

    $client = \Drupal::httpClient();

    try {
      $response = $client->post(\Drupal::config('govdelivery_endpoint'), [
        'auth' => [\Drupal::config('govdelivery_username'), \Drupal::config('govdelivery_password')],
        'headers' => [
          'Content-Type' => 'application/xml',
        ],
        'body' => $xml,
      ]);

      return $response->getStatusCode();
    }
    catch (RequestException $e) {
      watchdog_exception('govdelivery_bulletins', $e);
    }
  }

}
