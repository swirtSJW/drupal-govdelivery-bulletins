# GovDelivery Bulletins

This module is used to interact with the Granicus GovDelivery Bulletins API to send bulletins.


## How to use the service to add a bulletin to the queue.
The service to add a bulletin to the queue can be called from anything that you
would like to perform this [ Example: hook_entity_save(), hook_entity_delete(),
hook_update_N() ...]

It is called like this:
```
\Drupal::service('govdelivery_bulletins.add_bulletin_to_queue')
        ->setFlag('dedupe', TRUE)  // Optional.
        ->setBody("Some body text for the message.")
        ->addCategory('sales')  // Optional.
        ->addCategory('emergency')  // Optional.
        ->setFooter("Some body text for the message.")  // Optional.
        ->setFromAddress('us@example.org')
        ->setGovDeliveryID('some_unique_id')
        ->setHeader("Some body text for the message.")
        ->setSubject('Some text for the email subject.')
        ->setSMSBody('Some text SMS text.')  // Optional.
        ->addTopic('ABC-123')  // Optional.
        ->addTopic('DEF-789')  // Optional.
        ->setXmlBool('click_tracking', FALSE)  // Optional.
        ->setXmlBool('open_tracking', FALSE)  // Optional.
        ->setXmlBool('publish_rss', FALSE)  // Optional.
        ->setXmlBool('share_content_enabled', FALSE)  // Optional.
        ->setXmlBool('urgent', FALSE)  // Optional.

        ->addToQueue();
```

To add a test, it would look like this.
```
\Drupal::service('govdelivery_bulletins.add_bulletin_to_queue')
        ->setFlag('test', TRUE)
        ->addEmailAddress('test.recipient1@example.org')
        ->addEmailAddress('test.recipient2@example.org')
        ->addToQueue();
```

[GovDelivery Bulletins API](https://developer.govdelivery.com/api/comm_cloud_v1/Default.htm#API/Comm%20Cloud%20V1/API_CommCloudV1_Bulletins_CreateandSendBulletin.htm)

## How to securely store GovDelivery username and password.

Should be stored in settings.local.php, like so:
```
// GovDelivery settings.
$settings['govdelivery_username'] = 'YOUR-GOVDELIVERY-USERNAME';
$settings['govdelivery_password'] = 'YOUR-GOVDELIVERY-PASSWORD';
```
Then to access:
```
$apiUsername = \Drupal\Core\Site\Settings::get('govdelivery_username');
$apiPassword = \Drupal\Core\Site\Settings::get('govdelivery_password');

```
You can also save your username and password by setting them in the [GovDelivery Bulletins Admin form](/admin/config/services/govdelivery_bulletins).

This is strongly discouraged, and presents a security vulnerability.
