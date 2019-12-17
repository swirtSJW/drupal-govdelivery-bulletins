<?php

namespace Drupal\govdelivery_bulletins\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Provides base functionality for the GovDelivery Queue Workers.
 */
class BulletinBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * BulletinBase constructor.
   */
  public function __construct() {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.queue_worker')
    );
  }

  /**
   * The base queue service provided by Drupal.
   *
   * @return object
   *   A Drupal service wrapper.
   */
  private function queueFactory() {
    return \Drupal::service('queue');
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

    // Call bulletins service, and create an instance for processing.
    $queue = $queue_factory->get('govdelivery_bulletins');

    // Get the number of items.
    $number_of_queue = $queue->numberOfItems();

    for ($i = 0; $i < $number_of_queue; $i++) {
      // Get a queued item.
      // @TODO the release time should be close to the timeout time on the govdelivery API.
      $item = $queue->claimItem(20);
      if ((!empty($item)) && (($end_time && $item->created < $end_time) || empty($end_time))) {
        // Now process individual bulletin.
        $response = $this->processItem($item->data);
        if ($response === 200) {
          // Connection a success - remove the processed item from the queue.
          $queue->deleteItem($item);
        }
        // Service not available - release the item.
        $queue->releaseItem($item);
      }
      else {
        // Connection not made - release the item another round of processing.
        if (!empty($item)) {
          $queue->releaseItem($item);
        }
      }
    }
  }

  /**
   * Processes the queued item.
   *
   * @param object $data
   *   The queued data item we are processing.
   */
  public function processItem($data) {
    // Send and return response.
    return \Drupal::service('govdelivery_bulletins.send_bulletin')->send($data->xml);
  }

}
