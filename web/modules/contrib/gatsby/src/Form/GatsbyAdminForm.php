<?php

namespace Drupal\gatsby\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GatsbyAdminForm.
 */
class GatsbyAdminForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gatsby.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gatsby_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gatsby.settings');
    $form['server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gastby Preview Server URL'),
      '#description' => $this->t('The URL to the Gatsby preview server (with port number if needed)'),
      '#default_value' => $config->get('server_url'),
      '#weight' => 0,
    ];

    $build_title = $this->t("Build Server Callback Hook");
    $build_description = $this->t('The Callback URL to trigger the Gatsby Build. Note: Incremental builds are currently only supported with JSON:API and gatsby-source-drupal');
    if ($this->moduleHandler->moduleExists('jsonapi_extras')) {
      $build_title = $this->t("Incremental Build Server Callback Hook");
      $build_description = $this->t('The Callback URL to the Gatsby incremental builds server');
    }
    $form['incrementalbuild_url'] = [
      '#type' => 'textfield',
      '#title' => $build_title,
      '#description' => $build_description,
      '#default_value' => $config->get('incrementalbuild_url'),
      '#weight' => 1,
    ];
    $form['build_published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only trigger builds for published content'),
      '#description' => $this->t('Experimental: Depending on your content workflow, you may only
        want builds to be triggered for published content. By checking this box
        only published content will trigger a build. This means additional entities
        such as Media or Files will not trigger a rebuild until the content it\'s
        attached to is published. The downside is that this will
        only allow content entities to trigger a rebuild.'),
      '#default_value' => $config->get('build_published') !== NULL ? $config->get('build_published') : TRUE,
      '#weight' => 2,
    ];
    $form['preview_entity_types'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getContentEntityTypes(),
      '#default_value' => $config->get('preview_entity_types') ?: [],
      '#title' => $this->t('Entity types to send to Gatsby Preview Server'),
      '#description' => $this->t('What entities should be sent to the Gatsby Preview Server?'),
      '#weight' => 10,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('gatsby.settings')
      ->set('server_url', $form_state->getValue('server_url'))
      ->set('incrementalbuild_url', $form_state->getValue('incrementalbuild_url'))
      ->set('build_published', $form_state->getValue('build_published'))
      ->set('preview_entity_types', $form_state->getValue('preview_entity_types'))
      ->save();
  }

  /**
   * Gets a list of all the defined content entities in the system.
   *
   * @return array
   *   An array of content entities definitions.
   */
  private function getContentEntityTypes() {
    $content_entity_types = [];
    $allEntityTypes = $this->entityTypeManager->getDefinitions();

    foreach ($allEntityTypes as $entity_type_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $content_entity_types[$entity_type_id] = $entity_type->getLabel();
      }
    }
    return $content_entity_types;
  }

}
