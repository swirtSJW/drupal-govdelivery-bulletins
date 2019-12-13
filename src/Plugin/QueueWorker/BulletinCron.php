<?php

namespace Drupal\govdelivery_bulletins\Plugin\QueueWorker;

/**
 * Provides base functionality for the Bulletin Queue Worker.
 *
 * @QueueWorker(
 *   id = "govdelivery_bulletins",
 *   title = @Translation("Send bulletins to service"),
 *   cron = {"time" = 60}
 * )
 */
class BulletinCron extends BulletinBase {

  /**
   * BulletinCron constructor.
   */
  public function __construct() {

  }

}
