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
   * Tracks data changes that should be sent to Gatsby.
   *
   * @var array
   */
  public static $updateData = [];

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
   * Prepares Gatsby Data to send to the preview and build servers.
   *
   * By preparing the data in a separate step we prevent multiple requests from
   * being sent to the preview or incremental builds servers if mulutiple
   * Drupal entities are update/created/deleted in a single request.
   */
  public function gatsbyPrepareData(ContentEntityInterface $entity = NULL) {
    $preview_url = $this->config->get('server_url');

    if ($preview_url) {
      self::$updateData['preview'] = [
        'url' => $preview_url,
        'json' => FALSE,
        'path' => "/__refresh",
      ];
    }

    $incrementalbuild_url = $this->config->get('incrementalbuild_url');
    if ($incrementalbuild_url) {
      self::$updateData['incrementalbuild'] = [
        'url' => $incrementalbuild_url,
        'json' => FALSE,
        'path' => "",
      ];
    }
  }

  /**
   * Prepares Gatsby Deletes to send to the preview and build servers.
   *
   * This is a separate method to allow overriding services to override the
   * delete method to add additional data.
   */
  public function gatsbyPrepareDelete(ContentEntityInterface $entity = NULL) {
    $this->gatsbyPrepareData($entity);
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
   * Triggers the refreshing of Gatsby preview and incremental builds.
   */
  public function gatsbyUpdate() {
    foreach (self::$updateData as $data) {
      $this->triggerRefresh($data['url'], $data['json'], $data['path']);
    }
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
