<?php

namespace Drupal\govdelivery_bulletins\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates the queue view and process form.
 *
 * @package Drupal\govdelivery_bulletins\Form
 */
class GovDeliveryBulletinsQueueForm extends FormBase {

  /**
   * Queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Queue Worker Manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Database
   */
  private $database;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('renderer'),
      $container->get('date.formatter')
    );
  }

  /**
   * PostApiQueueForm constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   Queue factory.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   Queue manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(Connection $database, QueueFactory $queue, QueueWorkerManagerInterface $queue_manager, RendererInterface $renderer, DateFormatterInterface $date_formatter) {
    $this->database = $database;
    $this->queueFactory = $queue;
    $this->queueManager = $queue_manager;
    $this->renderer = $renderer;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'govdelivery_bulletins_queue_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $queue = $this->queueFactory->get('govdelivery_bulletins');
    $queue_items = $this->getItems('govdelivery_bulletins');

    $rows = [];

    foreach ($queue_items as $item) {
      $data = [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#plain_text' => json_encode(unserialize($item->data)),
      ];

      $data = $this->renderer->renderPlain($data);
      $timezone = $this->config('system.date')->get('timezone')['default'];

      $rows[] = [
        'data' => [
          $item->item_id,
          $data,
          $item->expire ? $this->dateFormatter->format($item->expire, 'custom', 'm/d/Y H:i:s T', $timezone) : 0,
          $this->dateFormatter->format($item->created, 'custom', 'm/d/Y H:i:s T', $timezone),
        ],
      ];
    }

    $form['queue_items'] = [
      '#type' => 'table',
      '#tableselect' => FALSE,
      '#header' => [
        'item_id' => $this->t('Item ID'),
        'data' => $this->t('Data'),
        'expired' => $this->t('Expires'),
        'created' => $this->t('Created'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No queue items found.'),
    ];

    $form['pager'] = [
      '#type' => 'pager',
    ];

    $markup = $this->t('Submitting this form will process the GovDelivery Bulletins Queue which contains @number items.', ['@number' => $queue->numberOfItems()]);

    if ($queue->numberOfItems() > 10) {
      $markup .= '<br>';
      $markup .= $this->t('NOTE: You may need to trigger queue processing (manually or via Cron) multiple times to process all of the items.');
    }

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => $markup,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process queue'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queue_worker = $this->queueManager->createInstance('govdelivery_bulletins');
    $queue_worker->processQueue();
    $this->messenger()->addStatus('GovDelivery Bulletins queue has been processed.');
  }

  /**
   * Get queue items for display in the UI.
   *
   * @param string $queue_name
   *   Queue name.
   *
   * @return mixed
   *   Queue items.
   */
  public function getItems($queue_name) {
    $query = $this->database->select('queue', 'q');
    $query->addField('q', 'item_id');
    $query->addField('q', 'data');
    $query->addField('q', 'expire');
    $query->addField('q', 'created');
    $query->condition('q.name', $queue_name);
    $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(10);

    return $query->execute()->fetchAll();
  }

}
