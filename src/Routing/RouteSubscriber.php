<?php

namespace Drupal\govdelivery_bulletins\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * GraphQL endpoint route alter.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Is basic_auth module enabled?
    $moduleHandler = \Drupal::service('module_handler');
    $basic_auth_enabled = $moduleHandler->moduleExists('basic_auth');
    $basic_auth_allowed = \Drupal::config('govdelivery_bulletins.settings')->get('api_queue_trigger_allow_basic_auth');
    if ($basic_auth_enabled && $basic_auth_allowed) {
      // Make all routes at api/govdelivery_bulletins/queue* accessible to any
      // users with perm 'Access Bulletin Queue Trigger API' using basic_auth.
      $route_provider = \Drupal::service('router.route_provider');
      $bulletin_routes = $route_provider->getRoutesByPattern('api/govdelivery_bulletins/queue');
      $routes_iterator = $bulletin_routes->getIterator();

      foreach ($routes_iterator as $route_name => $route_params) {
        if ($route = $collection->get($route_name)) {
          $route->setOption('_auth', ['basic_auth', 'cookie']);
          $route->setRequirement('_user_is_logged_in', 'TRUE');
        }
      }
    }
  }

}
