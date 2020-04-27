<?php

namespace Drupal\gatsby_instantpreview;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\gatsby\GatsbyPreview;
use Drupal\jsonapi_extras\EntityToJsonApi;

/**
 * Class GatsbyInstantPreview.
 */
class GatsbyInstantPreview extends GatsbyPreview {

  /**
   * Drupal\jsonapi_extras\EntityToJsonApi definition.
   *
   * @var \Drupal\jsonapi_extras\EntityToJsonApi
   */
  private $entityToJsonApi;

  /**
   * Constructs a new GatsbyInstantPreview object.
   */
  public function __construct(GatsbyPreview $inner_service,
      ClientInterface $http_client,
      ConfigFactoryInterface $config,
      EntityTypeManagerInterface $entity_type_manager,
      LoggerChannelFactoryInterface $logger,
      EntityToJsonApi $entity_to_json_api) {
    $this->innerService = $inner_service;
    $this->entityToJsonApi = $entity_to_json_api;
    parent::__construct($http_client, $config, $entity_type_manager, $logger);
  }

  /**
   * Prepares Gatsby Data to send to the preview and build servers.
   *
   * By preparing the data in a separate step we prevent multiple requests from
   * being sent to the preview or incremental builds servers if mulutiple
   * Drupal entities are update/created/deleted in a single request.
   */
  public function gatsbyPrepareData(ContentEntityInterface $entity = NULL) {
    $encoded_json = $this->entityToJsonApi->serialize($entity);

    // If there is a secret key, we decode the json, add the key, then encode.
    if ($this->innerService->config->get('secret_key')) {
      $json_object = json_decode($encoded_json);
      $json_object->secret = $this->innerService->config->get('secret_key');
      $encoded_json = json_encode($json_object);
    }

    $preview_url = $this->innerService->config->get('server_url');

    // Only trigger the preview refresh if gatsby_instantpreview is not enabled.
    if ($preview_url) {
      self::$updateData['preview'] = [
        'url' => $preview_url,
        'json' => $encoded_json,
        'path' => "/__updatePreview",
      ];
    }

    $incrementalbuild_url = $this->innerService->config->get('incrementalbuild_url');
    if ($incrementalbuild_url) {
      self::$updateData['incrementalbuild'] = [
        'url' => $incrementalbuild_url,
        'json' => FALSE,
        'path' => "",
      ];
    }
  }

  /**
   * Triggers the refreshing of Gatsby preview and incremental builds.
   */
  public function gatsbyDelete(ContentEntityInterface $entity = NULL) {
    $data = [
      'id' => $entity->uuid(),
      'action' => 'delete',
    ];

    // If there is a secret key, add the key to the request.
    if ($this->innerService->config->get('secret_key')) {
      $data['secret'] = $this->innerService->config->get('secret_key');
    }

    $encoded_json = json_encode($data);
    $preview_url = $this->innerService->config->get('server_url');

    // Only trigger the preview refresh if gatsby_instantpreview is not enabled.
    if ($preview_url) {
      $this->innerService->triggerRefresh($preview_url, $encoded_json, "/___updatePreview");
    }

    $incrementalbuild_url = $this->innerService->config->get('incrementalbuild_url');
    if ($incrementalbuild_url) {
      $this->innerService->triggerRefresh($incrementalbuild_url, $encoded_json, "/___updatePreview");
    }
  }

}