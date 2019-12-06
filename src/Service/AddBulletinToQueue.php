<?php

namespace Drupal\govdelivery_bulletins\Service;

use Drupal\Component\Utility;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Class for handling adding a GovDelivery bulletin to the queue.
 */
class AddBulletinToQueue {

  // Queue Operation flags.
  /**
   * Flag to set whether the queue should be deduped as this bulletin is added.
   *
   * @var bool
   */
  private $flag_dedupe = FALSE;

  /**
   * Flag to do this bulletin as a GovDelivery Test.
   *
   * @var bool
   */
  private $flag_test = FALSE;

  // Queue Operation variables.
  /**
   * An of status, warnings, and error mesages.
   *
   * @var array
   */
  private $messages = [
    'error' => [],
    'status' => [],
    'warning' => [],
  ];

  /**
   * An id for the queue to use in deduping the queue.
   *
   * If dedupe flag set, any other queue items with this id will be removed.
   *
   * @var string
   */
  private $queueUid = NULL;

  /**
   * The timestamp of the queue item being added, used for processing the queue.
   *
   * @var DateTime
   */
  private $time = NULL;

  // XML Template variables.
  // Purpose defined here:
  // https://developer.govdelivery.com/api/comm_cloud_v1/Default.htm#API/Comm%20Cloud%20V1/API_CommCloudV1_Bulletins_CreateandSendBulletin.htm
  /**
   * Body text for the message.
   *
   * @var string
   */
  private $body = '';

  /**
   * Categories to be included.
   *
   * @var array
   */
  private $categories = [];

  /**
   * Email addresses to be sent to. Used only with the test flag is set.
   *
   * @var array
   */
  private $emailAddresses = [];

  /**
   * A string of text to be used as the footer of the bulletin.
   *
   * @var string
   */
  private $footer = '';

  /**
   * The address the bulletin is to come from.
   *
   * @var string
   */
  private $fromAddress;

  /**
   * The GovDelivery ID to use in the send.
   *
   * @var string
   */
  private $govDeliveryId = NULL;

  /**
   * The header to use for the bulletin.
   *
   * @var string
   */
  private $header = '';

  /**
   * The time that the bulletin should be sent if not sending immediately.
   * @var DateTime
   */
  private $sendTime = NULL;

  /**
   * The string to use if the bulletin should go out to SMS text (140 char max).
   *
   * @var string
   */
  private $smsBody = NULL;

  /**
   * The subject of the bulletin.  (400 char max).
   *
   * @var string
   */
  private $subject = NULL;

  /**
   *  Topics to be included in the send.
   *
   * @var array
   */
  private $topics = [];

  // XML template flags.  These are booleans only.
  /**
   * Enable click tracking on the bulletin.
   *
   * @var bool
   */
  private $clickTracking = FALSE;

  /**
   * Enable open tracking on the bulletin email.
   *
   * @var bool
   */
  private $openTracking = FALSE;

  /**
   * Enable to publish the bulletin to the account activity RSS feed.
   *
   * @var bool
   */
  private $publishRss = FALSE;

  /**
   * Enable to share the content to Facebook or Twitter.
   *
   * @var bool
   */
  private $shareContentEnabled = FALSE;

  /**
   * Enable to ignore the users' digest settings and send it immediately.
   *
   * @var bool
   */
  private $urgent = FALSE;

  /**
   * AddBulletinToQueue constructor.
   */
  public function __construct() {
   //  @todo Figure out if anything needs to be passed in as an argument.
  }

  /**
   * Adds a category to the categories array.
   *
   * @param string $category
   *   The item to add for a category in the xml.
   *
   * @return $this
   */
  public function addCategory($category) {
    if (!empty($category)) {
      $this->categories[] = $category;
    }
    return $this;
  }


  /**
   * Adds an email address  to the email_addresses array.
   *
   * @param string $address
   *   An email address to add.
   *
   * @return $this
   */
  public function addEmailAddress($address) {
    if (!empty($address)) {
      $this->emailAddresses[] = $address;
    }
    return $this;
  }

  /**
   * Add a message of a type to the $this->messages array.
   *
   * @param string $type
   *   The type of message to add ['error', 'status', 'warning'].
   * @param string $text
   *   The text of the message to add.
   *
   * @return $this
   */
  public function addMessage($type, $text) {
    $types = ['error', 'status', 'warning'];
    if (in_array($type, $types)) {
      $this->messages[$type][] = $text;
    }

    return $this;
  }

  /**
   * Add a topic to $this->topics array.
   *
   * @param string $topic
   *   A topic value to be added.
   *
   * @return $this
   */
  public function addTopic($topic) {
    if (!empty($topic)) {
      $this->topics[] = $topic;
    }
    return $this;
  }

  /**
   * Adds the bulletin the to the Drupal Queue.
   *
   * @param DateTime $time
   *   The timestamp (optional) of when the queue item is to be actionalble.
   */
  public function addToQueue($time = NULL) {
    $this->time = $time ?? time();
    $can_be_queued = \Drupal::config('govdelivery_bulletins.settings')->get('enable_bulletin_queuing');
    if ($can_be_queued) {
      // Add it to the queue.
      /** @var \Drupal\QueueFactory $queue_factory */
      $queue_factory = \Drupal::service('queue');
      /** @var \Drupal\QueueInterface $queue */
      $queue = $queue_factory->get('govdelivery_bulletins');
      $queue->createItem($this->buildBulletinData());
      \Drupal::logger('govdelivery_bulletins')->info('Queued: @subject', ['@subject' => $this->subject]);
    }
    else {
      // Queuing is not enabled in config, so only log it.
      \Drupal::logger('govdelivery_bulletins')->info('Queuing Not Enabled: @subject', ['@subject' => $this->subject]);
    }
  }

/**
 * Builds the queue item to be added.
 *
 * @return object
 *   The data that will be added to the queue.
 */
  private function buildBulletinData() {
    $data = new \stdClass();
    // The $queue_uid can be used for deduping.
    $data->qid = $this->queueUid;
    // Needed so that the queue worker could only process tests or non-tests.
    $data->test = $this->flag_test;
    $data->time = $this->time;
    $data->xml = $this->buildXml();

    return $data;
  }

  /**
   * Builds the XML payload for a test or bulletin.
   *
   * @return string
   *   XML formatted string.
   */
  private function buildXml() {
    // Create a $messages to keep state.
    $error_messages = [];
    // Check to see if this is a test.
    if ($this->validateTest($error_messages)) {
      // This is a test. Use the test template.
      $this->dedupeQueue();
      $renderable = [
      '#theme' => 'govdelivery_bulletin_test_xml',
      '#email_addresses' => $this->emailAddresses,
      ];
      $xml = \Drupal::service('renderer')->render($renderable);
    }
    elseif (!$this->flag_test && $this->validate($error_messages)) {
      // This is not a test and is valid.
      $this->dedupeQueue();
      $renderable = [
        '#theme' => 'govdelivery_bulletin_xml',
        '#body' => $this->body,
        '#categories' => $this->categories,
        '#click_tracking' => $this->clickTracking,
        '#footer' => $this->footer,
        '#from_address' => $this->fromAddress,
        '#header' => $this->header,
        '#open_tracking' => $this->open_tracking,
        '#publish_rss' => $this->publish_rss,
        '#send_time' => $this->sendTime,
        '#share_content_enabled' => $this->shareContentEnabled,
        '#sms_body' => $this->smsBody,
        '#subject' => $this->subject,
        '#topics' => $this->topics,
        '#urgent' => $this->urgent,
      ];
      $xml = \Drupal::service('renderer')->render($renderable);
    }
    else {
      // Nothing validated so log and throw an exception with $error_messages.
      // @TODO finish this.
    }

    return $xml;
  }

  /**
   * Remove any queued items with the same $queue_uid as this one.
   */
  private function dedupeQueue() {
    if ($this->flag_dedupe === TRUE) {
      // @todo Add the guts of this to dedupe based on $this->queue_uid.
      // https://www.drupal.org/project/govdelivery_bulletins/issues/3096347

      // If you can, obtain a count of the number removed and add a status message.
    }
  }


  /**
   * Obtain the type of message requested.
   *
   * @param string $type
   *   The type of message to retrieve, [ALL, warning, status or error].
   *
   * @return array
   *   An array of messages or an empty array.
   */
  public function getMessages(string $type = 'all') {
    $messages = [];
    if ($type === 'all') {
      $messages = $this->messages;
    }
    elseif (!empty($this->messages[$type])) {
      $messages = $this->messages[$type];
    }

    return $messages;
  }

  /**
   * Set the body of the XML.
   *
   * @param string $body
   *   The body text or html to include in the bulletin.
   *
   * @return $this
   */
  public function setBody($body) {
    $this->body = $body;
    return $this;
  }


  /**
   * Sets a non-XML flag to the value specified.
   *
   * @param string $flagname
   *   The name of the flag to set, [dedupe or test].
   * @param bool $value
   *   The value to set. [TRUE or FALSE].
   *
   * @return $this
   */
  public function setFlag(string $flagname, bool $value = TRUE) {
    // Strip 'flag_' prefix just in case it is used.
    $flagname = str_replace('flag_', '', $flagname);
    $flagname = "flag_{$flagname}";
    $this->$flagname = $value;

    return $this;
  }

  /**
   * Setter for the footer.
   *
   * @param string $footer
   *   The text to set as the footer for the bulletin.
   *
   * @return $this
   */
  public function setFooter($footer) {
    $this->footer = $footer;
    return $this;
  }

  /**
   * Setter for the from_address.
   *
   * @param string $value
   *   The address the bulletin email should be sent from.
   *
   * @return $this
   */
  public function setFromAddress($value) {
    $this->fromAddress = $value;
    return $this;
  }

  // @todo Is this needed?
  public function setGovDeliveryID($value) {
    $this->gov_delivery_id = $value;
    return $this;
  }

  /**
   * Setter for the header.
   *
   * @param string $header
   *   The text to set as the header for the bulletin.
   *
   * @return $this
   */
  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  /**
   * Setter for the queueUID which is used for identifiying duplicates.
   *
   * @param string $quid
   *   A unique id that is specific to identifying the queue item and
   *   identifying duplicates.
   *
   * @return $this
   */
  public function setQueueUID($quid) {
    $this->queueUid = $quid;
    return $this;
  }

  /**
   * Setter for send_time, determines when GovDelivery will send the bulletin.
   *
   * @param Datetime $send_time
   *   The timestamp for when govDelivery will send the bulletin.
   *
   * @return $this
   */
  public function setSendTime(Datetime $send_time) {
    $this->sendTime = $send_time;
    return $this;
  }

  /**
   * Sets the sms_body for the xml payload. Truncated if longer than 140 chars.
   *
   * @param string $sms_body
   *   The value for the sms_body.
   *
   * @return $this
   */
  public function setSmsBody($sms_body) {
    // Subjects have a 140 character limit.
    if (strlen($sms_body) > 140) {
      $sms_body = Unicode::truncate($sms_body, 140, TRUE, TRUE, 3);
      $this->addMessage('warning', t('smsbody was too long. It was truncated to 140 characters with ellipsis.'));
    }
    $this->smsBody = $sms_body;

    return $this;
  }

  /**
   * Setter for the xml subject.  Truncated if longer than 400 chars.
   *
   * @param string $subject
   *   The subject for the email bulletin.
   *
   * @return $this
   */
  public function setSubject($subject) {
    // Subjects have a 400 character limit.
    if (strlen($subject) > 400) {
      $subject = Unicode::truncate($subject, 400, TRUE, TRUE, 3);
      $this->addMessage('warning', t('Subject was too long. It was truncated to 400 characters with ellipsis.'));
    }
    $this->subject = $subject;

    return $this;
  }

  /**
   * Sets the boolean for an xml flag.
   *
   * @param string $flag
   *   The name of the GovDelivery template boolean to set.
   * @param bool $value
   *   The value of the boolean to set.
   *
   * @return $this
   */
  public function setXmlBool (string $flag, bool $value = FALSE) {
    if (property_exists($this, $flag)) {
      $this->$flag = $value;
    }
    else {
      $this->addMessage('warning', t("The flag '@flag' is not a property of this template and can not be set.", ['@flag' => $flag]));
    }

    return $this;
  }

  /**
   * Validate the xml data for the bulletin template.
   *
   * @param array $error_messages
   *   An empty array passed by reference so the error messages can be handled
   *   by the caller.
   *
   *   @return $this
   */
  private function validate(array &$error_messages) {
    // Length variables trigger a warning in the setter,  but are not a  defect.
    // Body is not required. If not present, gov delivery will use template on file with GovDelivery.
    // Header is not required. If not present, gov delivery will use template on file with GovDelivery.
    // sms_body has a 140 character limit.  Set nil="true" if no body should be sent.
  }


  /**
   * Validate the xml data for the bulletin test template.
   *
   * @param array $error_messages
   *   An empty array passed by reference so the error messages can be handled
   *   by the caller.
   *
   *   @return $this
   */
  private function validateTest(&$error_messages) {
    $return = FALSE;
    if ($this->flag_test === TRUE) {
      // This is a test, so proceed with validation.
      // email_addresses must be an array and have at least one value.
      if (count($this->emailAddresses < 1)) {
        $error_messages[] = t('Can not send a test bulletin without at least one address to send it to.');
      }
      // Check that each item is an email address.
      foreach ($this->emailAddresses as $address) {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
          // This address did not validate.
          $error_messages[] = t('The address @address is not a valid address.', ['@address' => $address]);
        }
      }
    }

    return (empty($error_messages)) ? TRUE : FALSE;
  }


  /**
   * Displays the messages of a given type tor drupal status.
   *
   * @param string $type
   *   The type of messages to display [warning, error, status, or all]].
   */
  public function displayMessages($type) {
    // @todo make is display each of the messages of the type.
  }

  /**
   * Loag a message to watchdog.
   *
   * @param string $message
   *   The message to the watchdog log.
   */
  private function log($message) {
    // Append vars placeholders.
    // @TODO make this meaningful.  It is placeholder gibberish right now.
    $message .= 'node @title, uid @uid';
    \Drupal::logger('govdelivery_bulletins')->notice($message, [
      '@title' => $node->getTitle(),
      '@uid' => $this->uid,
    ]);
  }
}
