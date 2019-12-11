<?php

namespace Drupal\govdelivery_bulletins\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GovDeliveryBulletinsAdminForm.
 */
class GovDeliveryBulletinsAdminForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gov_delivery_bulletins_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('govdelivery_bulletins.settings');

    $form['enable_bulletin_queuing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Bulletin Queuing'),
      '#description' => $this->t(
        'Enables calls of Service AddBulletinToQueue to actually add to the queue. Useful for disabling for testing. If disabled, will still write to log.'
      ),
      '#weight' => '0',
      '#default_value' => $config->get('enable_bulletin_queuing'),
    ];

    $form['enable_bulletin_queue_sends_to_govdelivery'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Bulletin Queue Sends to GovDelivery API'),
      '#description' => $this->t(
        'Enables the queue to post to the GovDelivery Bulletin API. Useful for disabling for testing. If disabled, will still write to log.'
      ),
      '#weight' => '2',
      '#default_value' => $config->get('enable_bulletin_queue_sends_to_govdelivery'),
    ];

    $form['api_queue_trigger_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable API Queue Trigger'),
      '#description' => $this->t(
        'Enables the ability to visit "@path" to trigger processing of the bulletin queue.',
        ['@path' => '/api/govdelivery_bulletins/queue?EndTime=[unix timestamp]']
      ),
      '#weight' => '4',
      '#default_value' => $config->get('api_queue_trigger_enabled'),
    ];

    $form['govdelivery_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GovDelivery Username'),
      '#description' => $this->t(
        'Stores GovDelivery username in database - THIS IS NOT RECOMMENDED.
        Preferred method is to store in settings.local.php - see README for
        instructions.'),
      '#weight' => '5',
      '#default_value' => $config->get('govdelivery_username'),
    ];

    $form['govdelivery_password'] = [
      '#type' => 'password',
      '#title' => $this->t('GovDelivery Password'),
      '#description' => $this->t(
        'Stores GovDelivery password in database - THIS IS NOT RECOMMENDED.
        Preferred method is to store in settings.local.php - see README for
        instructions.'),
      '#weight' => '6',
      '#default_value' => $config->get('govdelivery_password'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => '20',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = self::configFactory()->getEditable('govdelivery_bulletins.settings');
    $config
      ->set('api_queue_trigger_enabled', $form_state->getValue('api_queue_trigger_enabled'))
      ->set('enable_bulletin_queuing', $form_state->getValue('enable_bulletin_queuing'))
      ->set('enable_bulletin_queue_sends_to_govdelivery', $form_state->getValue('enable_bulletin_queue_sends_to_govdelivery'))
      ->set('govdelivery_username', $form_state->getValue('govdelivery_username'))
      ->set('govdelivery_password', $form_state->getValue('govdelivery_password'))
      ->save();
  }

}
