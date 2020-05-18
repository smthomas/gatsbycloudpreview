<?php

namespace Drupal\gatsby_instantpreview;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\gatsby\GatsbyPreview;
use Drupal\jsonapi_extras\EntityToJsonApi;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * Drupal\Core\Entity\EntityRepository definition.
   *
   * @var \Drupal\Core\Entity\EntityRepository
   */
  private $entityRepository;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new GatsbyInstantPreview object.
   */
  public function __construct(GatsbyPreview $inner_service,
      ClientInterface $http_client,
      ConfigFactoryInterface $config,
      EntityTypeManagerInterface $entity_type_manager,
      LoggerChannelFactoryInterface $logger,
      EntityToJsonApi $entity_to_json_api,
      EntityRepository $entity_repository,
      RequestStack $request_stack) {
    $this->innerService = $inner_service;
    $this->entityToJsonApi = $entity_to_json_api;
    $this->entityRepository = $entity_repository;
    $this->requestStack = $request_stack;
    parent::__construct($http_client, $config, $entity_type_manager, $logger);
  }

  /**
   * Prepares Gatsby Data to send to the preview and build servers.
   *
   * By preparing the data in a separate step we prevent multiple requests from
   * being sent to the preview or incremental builds servers if mulutiple
   * Drupal entities are update/created/deleted in a single request.
   */
  public function gatsbyPrepareData(ContentEntityInterface $entity = NULL, string $action = 'update') {
    $json = $this->getJson($entity);
    $json['id'] = $entity->uuid();
    $json['action'] = $action;

    // If there is a secret key, we decode the json, add the key, then encode.
    if ($this->innerService->config->get('secret_key')) {
      $json['secret'] = $this->innerService->config->get('secret_key');
    }

    $preview_path = "/__refresh";
    if ($this->innerService->config->get('legacy_preview_url')) {
      // The legacy URL expects an encoded JSON string.
      $encoded_json = json_encode($this->bundleData('preview', $json));
      $preview_path = '/___updatePreview';
    }

    $preview_url = $this->innerService->config->get('server_url');
    if ($preview_url) {
      self::$updateData['preview'] = [
        'url' => $preview_url,
        'json' => !empty($encoded_json) ? $encoded_json : $this->bundleData('preview', $json),
        'path' => $preview_path,
      ];
    }

    $incrementalbuild_url = $this->config->get('incrementalbuild_url');
    if (!$incrementalbuild_url) {
      return;
    }

    if ($this->config->get('build_published')) {
      if (!($entity instanceof NodeInterface) || !$entity->isPublished()) {
        return;
      }

      if (empty($json['data']['relationships'])) {
        return;
      }

      // Generate JSON for all related entities to send to Gatsby.
      $entity_data = [];
      $this->buildRelationshipJson($json['data']['relationships'], $entity_data);

      if (!empty($entity_data)) {
        $original_data = $json['data'];
        $entity_data[] = $original_data;
        $json['data'] = $entity_data;
      }
    }

    self::$updateData['incrementalbuild'] = [
      'url' => $incrementalbuild_url,
      'json' => $this->bundleData('incrementalbuild', $json),
      'path' => "",
    ];

  }

  /**
   * Triggers the refreshing of Gatsby preview and incremental builds.
   */
  public function gatsbyPrepareDelete(ContentEntityInterface $entity = NULL) {
    $json = [
      'id' => $entity->uuid(),
      'action' => 'delete',
    ];

    // If there is a secret key, add the key to the request.
    if ($this->innerService->config->get('secret_key')) {
      $json['secret'] = $this->innerService->config->get('secret_key');
    }

    $preview_path = "/__refresh";
    if ($this->innerService->config->get('legacy_preview_url')) {
      // The legacy URL expects an encoded JSON string.
      $json = json_encode($json);
      $preview_path = '/___updatePreview';
    }

    $preview_url = $this->innerService->config->get('server_url');
    if ($preview_url) {
      self::$updateData['preview'] = [
        'url' => $preview_url,
        'json' => $json,
        'path' => $preview_path,
      ];
    }

    $incrementalbuild_url = $this->innerService->config->get('incrementalbuild_url');
    if ($incrementalbuild_url) {
      self::$updateData['incrementalbuild'] = [
        'url' => $incrementalbuild_url,
        'json' => $json,
        'path' => "",
      ];
    }
  }

  /**
   * Bundles entity JSON data so it can be passed in a single request.
   */
  public function bundleData($key, $json) {
    $updated = &self::$updateData;
    // The first time this method is called our updated data array is
    // empty so we can just return the data we were given.
    if (empty($updated)) {
      return $json;
    }

    // This shouldn't be empty but just in case.
    // @TODO: Determine if we can remove this check.
    if (!empty($updated[$key]['json'])) {
      if (!empty($updated[$key]['json']['data']['type'])) {
        // If there is only one entity, convert it to an array.
        $json['data'] = [$updated[$key]['json']['data'], $json['data']];
      }
      else {
        // Add new entities to the updated json data array.
        $updated[$key]['json']['data'][] = $json['data'];
        // Update our json data array with the updated entities.
        $json['data'] = $updated[$key]['json']['data'];
      }
    }

    return $json;
  }

  /**
   * Builds an array of entity JSON data based on entity relationships.
   */
  private function buildRelationshipJson($relationships, &$entity_data) {
    foreach ($relationships as $data) {
      // Only add JSON if the entity type is one that should be sent to Gatsby.
      $entityType = !empty($data['data']['type']) ? explode('--', $data['data']['type']) : "";
      $selectedEntityTypes = $this->config->get('preview_entity_types') ?: [];
      if (!empty($entityType) && in_array($entityType[0], array_values($selectedEntityTypes), TRUE)) {
        $related_entity = $this->entityRepository->loadEntityByUuid($entityType[0], $data['data']['id']);
        $related_json = $this->getJson($related_entity);

        // We need to traverse all related entities to get all relevant JSON.
        if (!empty($related_json['data']['relationships'])) {
          $this->buildRelationshipJson($related_json['data']['relationships'], $entity_data);
        }

        $entity_data[] = $related_json['data'];
      }
    }
  }

  /**
   * Gets the JSON object for an entity.
   *
   * This is needed because of issue
   * https://www.drupal.org/project/jsonapi_extras/issues/3135950
   * which causes EntityToJsonApi not to work with the AjaxForm included with
   * Media Library. This is a workaround until the issue above is fixed.
   */
  private function getJson(ContentEntityInterface $entity) {
    // Get the current request and verify this is not an ajax request.
    $request = $this->requestStack->getCurrentRequest();

    if ($request->query->has('ajax_form')) {
      $json = $this->entityToJsonApi->normalize($entity);
      $this->requestStack->pop();
      return $json;
    }

    return $this->entityToJsonApi->normalize($entity);

  }

}
