# GovDelivery Bulletins

This module provides a set of utilities to have your Drupal powered site use [GovDelivery's Communications Cloud API - Bulletins resource](https://developer.govdelivery.com/api/comm_cloud_v1/Default.htm#API/Comm%20Cloud%20V1/API_CommCloudV1_Bulletins.htm) to create and send Bulletin notifications including an external trigger endpoint.  This module supports custom code a developer must create to send the bulletin.  It does not create them by itself.

It is more focused in function than the [GovDelivery Integration module](https://www.drupal.org/project/govdelivery).




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
        ->setHeader("Some body text for the message.")
        ->setQueueUid('some unique string') //optional
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

## How to securely store GovDelivery API endpoint, username and password.

Should be stored in settings.local.php, like so:
```
// GovDelivery settings.
$config['govdelivery_bulletins.settings']['govdelivery_endpoint'] = 'GOVDELIVERY-API-ENDPOINT';
$config['govdelivery_bulletins.settings']['govdelivery_username'] = 'YOUR-GOVDELIVERY-USERNAME';
$config['govdelivery_bulletins.settings']['govdelivery_password'] = 'YOUR-GOVDELIVERY-PASSWORD';
```
Then to access:
```
$config = \Drupal::config('govdelivery_bulletins.settings');
$apiEndPoint = $config->get('govdelivery_endpoint');
$apiUsername = $config->get('govdelivery_username');
$apiPassword = $config->get('govdelivery_password');

```
You can also save your username and password by setting them in the [GovDelivery Bulletins Admin form](/admin/config/services/govdelivery-bulletins/settings).

This is strongly discouraged, and presents a security vulnerability.
