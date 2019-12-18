<?php

namespace Drupal\govdelivery_bulletins\Service;

/**
 * POST a GovDelivery bulletin to the API.
 *
 * Use like: `\Drupal::service('govdelivery_bulletins.send_bulletin')->send($xml_bulletin);`
 */
class SendBulletin {

  /**
   * The config object for govdelivery_bulletins.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config = NULL;

  /**
   * Flag to indicate it sending is enabled.
   *
   * @var bool
   */
  private $enabled = NULL;

  /**
   * The address of the govdelivery endpoint.
   *
   * @var string
   */
  private $endpoint = NULL;

  /**
   * The password of the govdelivery endpoint.
   *
   * @var string
   */
  private $password = NULL;

  /**
   * The username of the govdelivery endpoint.
   *
   * @var string
   */
  private $username= NULL;

  /**
   * SendBulletin constructor.
   */
  public function __construct() {
    $this->config = \Drupal::config('govdelivery_bulletins.settings');
    $this->enabled = $this->config->get('enable_bulletin_queue_sends_to_govdelivery');
    $this->username = $this->config->get('govdelivery_username');
    $this->password = $this->config->get('govdelivery_password');
    $this->endpoint = $this->config->get('govdelivery_endpoint');
  }

  /**
   * Send the xml request to GovDelivery Bulletin API.
   *
   * @param string $xml
   *   Properly formed XML required by the GovDelivery Bulletin API.
   *
   * @param int $item_id
   *   The item ID of the queued item being sent.
   *
   * @return mixed
   *   Post response code, or FALSE.
   */
  public function send($xml, $item_id = NULL) {
    $this->itemId = $item_id;
    // Logging vars.
    $vars = [
      '@item_id' => $this->itemId,
    ];
    if ($this->enabled) {
      // Sending is enabled, see if we can.
      if ($this->canSend() && $this->isValidXml($xml)) {
        // Has the requirements to send.
        $client = \Drupal::httpClient();

        try {
          $response = $client->post($this->endpoint, [
            'auth' => [$this->username, $this->password],
            'headers' => [
              'Content-Type' => 'application/xml',
            ],
            'body' => $xml,
          ]);
          $status_code = $response->getStatusCode();

          // Log it.
          $vars['@status_code'] = $status_code;
          if ($status_code == 200) {
            \Drupal::logger('govdelivery_bulletins')->info('Sent bulletin @item_id to GovDelivery.', $vars);
          }
          else {
            \Drupal::logger('govdelivery_bulletins')->error('Send of bulletin @item_id to GovDelivery failed with code: @status_code.', $vars);
          }

          return $status_code;
        }
        catch (\Exception $e) {
          watchdog_exception('govdelivery_bulletins', $e);
        }
      }
        // Can't send, due to lack of credentials, already logged.
        return FALSE;
    }
    else {
      // Sending is not enabled, log it only.
      \Drupal::logger('govdelivery_bulletins')->error('Tried to send bulletin @item_id, but disabled by govdelivery_bulletins config.', $vars);
      return FALSE;
    }
  }

  /**
   * Validator to make sure credentials are present to send to the API.
   */
  private function canSend() {
    // Validates whether we have what we need to send.
    $can_send = TRUE;
    $missing_vars = '';

    // Have endpoint?
    if (empty($this->endpoint)) {
      $can_send = FALSE;
      $missing_vars .= "endpoint, ";
    }

    // Have username?
    if (empty($this->username)) {
      $can_send = FALSE;
      $missing_vars .= "username, ";
    }
    // Have password?
    if (empty($this->password)) {
      $can_send = FALSE;
      $missing_vars .= "password, ";
    }

    if ($can_send) {
      return $can_send;
    }
    else {
      \Drupal::logger('govdelivery_bulletins')->error('Could not contact GovDelivery endpoint, @missing_vars were missing from credentials.', ['@missing_vars' => $missing_vars]);
      return FALSE;
    }
  }

  /**
   * Validator to make sure xml is present and properly formed.
   *
   * @param string $xml
   *   Properly formed xml as required by the GovDelivery Bulletin API.
   *
   * @return bool
   *   TRUE if it can be sent, FALSE if anything is missing.
   */
  private function isValidXml($xml) {
    $is_valid = TRUE;
    // Check to see if it is empty.
    if (empty(trim($xml))) {
      // Xml is empty.  That's not valid.
      $is_valid = FALSE;
      \Drupal::logger('govdelivery_bulletins')->error('Could not send XML to GovDelivery endpoint, XML was empty.');
    }
    // @TODO Run additional check to check for valid xml.
    // Maybe like https://magp.ie/2011/02/12/check-for-valid-xml-with-php this.

    return $is_valid;
  }
}
