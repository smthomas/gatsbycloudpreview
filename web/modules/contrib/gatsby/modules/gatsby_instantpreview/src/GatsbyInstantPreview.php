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
   * Triggers the refreshing of Gatsby preview and incremental builds.
   */
  public function gatsbyUpdate(ContentEntityInterface $entity = NULL) {
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
      $this->innerService->triggerRefresh($preview_url, $encoded_json, "/___updatePreview");
    }

    $incrementalbuild_url = $this->innerService->config->get('incrementalbuild_url');
    if ($incrementalbuild_url) {
      $this->innerService->triggerRefresh($incrementalbuild_url, FALSE, "");
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

  /**
   * Send updates to Gatsby Preview server.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   */
  // public function updatePreviewEntity(ContentEntityInterface $entity) {
  //   $encoded_json = $this->entityToJsonApi->serialize($entity);

  //   // If there is a secret key, we decode the json, add the key, then encode.
  //   if ($this->config->get('secret_key')) {
  //     $json_object = json_decode($encoded_json);
  //     $json_object->secret = $this->config->get('secret_key');
  //     $encoded_json = json_encode($json_object);
  //   }

  //   $server_url = $this->config->get('server_url');
  //   try {
  //     $this->httpClient->post(
  //       $server_url . "/___updatePreview",
  //       [
  //         'json' => $encoded_json,
  //         'timeout' => 1,
  //       ]
  //     );
  //   }
  //   catch (ServerException | ConnectException $e) {
  //     // Do nothing as no response is returned from the preview server.
  //   }
  //   catch (\Exception $e) {
  //     $this->logger->error($e->getMessage());
  //   }
  // }

  /**
   * Send delete request to Gatsby Preview server.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to delete.
   */
  // -

  /**
   * Verify the entity is selected to sync to the Gatsby site.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   If the entity type should be sent to Gatsby Preview.
   */
  // public function isPreviewEntity(ContentEntityInterface $entity) {
  //   return $this->innerService->isPreviewEntity($entity);
  // }

}
