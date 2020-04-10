<?php

namespace Drupal\gatsby;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class GatsbyPreview.
 */
class GatsbyPreview {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Config Interface for accessing site configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs a new GatsbyPreview object.
   */
  public function __construct(ClientInterface $http_client,
      ConfigFactoryInterface $config,
      EntityTypeManagerInterface $entity_type_manager,
      LoggerChannelFactoryInterface $logger) {
    $this->httpClient = $http_client;
    $this->config = $config->get('gatsby.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('gatsby');
  }

  /**
   * Triggers the refreshing of Gatsby preview and incremental builds.
   */
  public function gatsbyUpdate(ContentEntityInterface $entity = NULL) {
    $preview_url = $this->config->get('server_url');

    // Only trigger the preview refresh if gatsby_instantpreview is not enabled.
    if ($preview_url) {
      $this->triggerRefresh($preview_url);
    }

    $incrementalbuild_url = $this->config->get('incrementalbuild_url');
    if ($incrementalbuild_url) {
      $this->triggerRefresh($incrementalbuild_url, FALSE, "");
    }
  }

  /**
   * Verify the entity is selected to sync to the Gatsby site.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   If the entity type should be sent to Gatsby Preview.
   */
  public function isPreviewEntity(ContentEntityInterface $entity) {
    $entityType = $entity->getEntityTypeId();
    $selectedEntityTypes = $this->config->get('preview_entity_types') ?: [];
    return in_array($entityType, array_values($selectedEntityTypes), TRUE);
  }

  /**
   * Triggers Gatsby refresh endpoint.
   *
   * @param string $server_url
   *   The Gatsby URL to refresh.
   * @parm string $json
   *   Optional JSON to post to the server.
   */
  protected function triggerRefresh($server_url, $json = FALSE, $path = "/__refresh") {
    $data = ['timeout' => 1];

    if ($json) {
      $data['json'] = $json;
    }

    try {
      $this->httpClient->post($server_url . $path, $data);
    }
    catch (ServerException | ConnectException $e) {
      // Do nothing as no response is returned from the preview server.
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

}
