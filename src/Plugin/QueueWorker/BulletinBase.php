<?php

namespace Drupal\govdelivery_bulletins\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Provides base functionality for the NodePublish Queue Workers.
 */
class BulletinBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * BulletinBase constructor.
   */
  public function __construct() {

  }

  /**
   * The base queue service provided by Drupal.
   *
   * @var object
   */
  private function queueFactory() {
    return \Drupal::service('queue');
  }

  /**
   * The base queue service manager provided by Drupal.
   *
   * @var object
   */
  private function queueManager() {
    return \Drupal::service('plugin.manager.queue_worker');
  }

  /**
   * Processes the bulletins in the queue.
   *
   * @param DateTime $end_time
   *   The timestamp (optional) to use for grabbing items created prior.
   */
  public function processQueue($end_time = NULL) {
    // Call our queue service.
    $queue_factory = $this->queueFactory();
    $queue_manager = $this->queueManager();

    // Call bulletins service, and create an instance for processing.
    $queue = $queue_factory->get('govdelivery_bulletins');
    $queue_worker = $queue_manager->createInstance('govdelivery_bulletins');

    // Get the number of items.
    $number_of_queue = $queue->numberOfItems();

    for ($i = 0; $i < $number_of_queue; $i++) {
      // Get a queued item.
      $item = $queue->claimItem(20);
      if (($end_time && $item->created < $end_time) || empty($end_time)) {
        // Now process individual bulletin..
        $process_item = self::processItem($item);
        if ($process_item === 'good') {
          return 'success';
        }
        return 'service not available';
      }
      else {
        // Release it for another round of processing.
        $queue_worker->releaseItem($item->data);
        return 'item not within timestamp';
      }
      return 'nothing processed';
    }
  }

  /**
   * Processes the queued item.
   *
   * @param object $item
   *   The queued data item we are processing.
   */
  public function processItem($item) {
    // Call our queue service.
    $queue_factory = $this->queueFactory();
    $queue_manager = $this->queueManager();

    // Call bulletins service, and create an instance for processing.
    $queue = $queue_factory->get('govdelivery_bulletins');
    $queue_worker = $queue_manager->createInstance('govdelivery_bulletins');
    $send = $this->send($item->data->xml);
    // Check for 200 from send service.
    if ($send === 200) {
      // Response is good, so process item in queue.
      $queue_worker->processItem($item);
      // Now remove the processed item from the queue.
      $queue->deleteItem($item);
      return 'good';
    }
    return 'failure';
  }

  /**
   * Calls the send service and passes the queued item.
   *
   * @param object $data
   *   The queued data item we are passing.
   */
  private function send(object $data) {
    return \Drupal::service('govdelivery_bulletins.send_bulletin')->send($data);
  }

}
