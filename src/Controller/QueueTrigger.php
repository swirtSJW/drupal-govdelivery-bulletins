<?php

namespace Drupal\govdelivery_bulletins\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\govdelivery_bulletins\Plugin\QueueWorker\BulletinBase;

/**
 * A controller for the GovDelivery Queue Trigger.
 */
class QueueTrigger extends ControllerBase {

  /**
   * Initiates the queue worker to process the queue.
   */
  public function processToEndTime() {
    // Check to see if the config says the endpoint can be called.
    $can_be_called = \Drupal::config('govdelivery_bulletins.settings')->get('api_queue_trigger_enabled');
    $time = \Drupal::request()->query->get('EndTime');
    // Check to see that $time is a valid timestamp.
    if (!$can_be_called || empty($time) || (!is_numeric($time)) || ((string) (int) $time !== $time) || (strtotime(date('d-m-Y H:i:s', $time)) !== (int) $time)) {
      // Either the endpoint can't be called, or the timestamp  was invalid.
      // Return 404.
      throw new NotFoundHttpException();
    }
    else {
      // Process our bulletins.
      $queueWorker = new BulletinBase();
      $status = $queueWorker->processQueue($time);

      // The endpoint has been called.
      // Log status.
      $vars = ['@time' => date('m-d-Y H:i:s', $time)];
      $build = [
        '#markup' => $this->t('The queue has been triggered for anything prior to: @time', $vars),
      ];
      \Drupal::logger('govdelivery_bulletins')->info('The queue has been triggered for anything prior to: @time', $vars);

      return $build;
    }
  }

}
