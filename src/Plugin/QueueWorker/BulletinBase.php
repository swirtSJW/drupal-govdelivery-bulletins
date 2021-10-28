<?php

namespace Drupal\govdelivery_bulletins\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Provides base functionality for the GovDelivery Queue Workers.
 *
 * @QueueWorker(
 *   id = "govdelivery_bulletins",
 *   title = @Translation("Process GovDelivery Bulletins"),
 * )
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
   * @param \DateTime|null $end_time
   *   The timestamp (optional) to use for grabbing items created prior.
   */
  public function processQueue($end_time = NULL) {
    // Call our queue service.
    $queue_factory = $this->queueFactory();

    // Call bulletins service, and create an instance for processing.
    $queue = $queue_factory->get('govdelivery_bulletins');

    // Get the number of items.
    $number_of_queue = $queue->numberOfItems();
    // The queue will keep grabbing the same item after it is released, so
    // we need to grab them all and mass release them.
    $queued_bulletins_to_release = [];

    for ($i = 0; $i < $number_of_queue; $i++) {
      // Get a queued item.
      // @todo the release time should be close to the timeout time on the
      // govdelivery API.
      $item = $queue->claimItem(20);
      if ((!empty($item)) && (($end_time && $item->created < $end_time) || empty($end_time))) {
        // Now process individual bulletin.
        $response = $this->processItem($item->data);
        if ($response === 200) {
          // Connection a success - remove the processed item from the queue.
          $queue->deleteItem($item);
        }
        // Service not available - release the item.
        $queued_bulletins_to_release[$item->item_id] = $item;

      }
      else {
        // Item intentionally not processed - release for another time.
        if (!empty($item)) {
          $queued_bulletins_to_release[$item->item_id] = $item;
        }
      }
    }

    // Processing of queue done, release all unprocessed items.
    foreach ($queued_bulletins_to_release as $bulletin_to_release) {
      $queue->releaseItem($bulletin_to_release);
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
    return \Drupal::service('govdelivery_bulletins.send_bulletin')->send($data->xml, $data->qid);
  }

}
