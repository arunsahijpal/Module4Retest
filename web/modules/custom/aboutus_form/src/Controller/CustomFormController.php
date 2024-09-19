<?php

namespace Drupal\aboutus_form\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom controller for displaying the About Us page.
 */
class CustomFormController extends ControllerBase {

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CustomFormController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Creates an instance of the controller.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new instance of the controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the About Us page with configuration data.
   *
   * @return array
   *   A render array for the About Us page.
   */
  public function aboutUsPage() {
    $config = $this->configFactory->get('aboutus_form.settings');
    $num_groups = $config->get('num_groups');
    // Ensure it's an array.
    $deleted_groups = $config->get('deleted_groups', []);

    // If $deleted_groups is not an array, initialize it.
    if (!is_array($deleted_groups)) {
      $deleted_groups = [];
    }

    $content = [];

    for ($i = 0; $i < $num_groups; $i++) {
      if (in_array($i, $deleted_groups)) {
        continue;
      }

      $profile_image = $config->get("Leader_" . ($i + 1) . '_profile_image');
      $profile_image_url = '';

      if (!empty($profile_image)) {
        $file = $this->entityTypeManager->getStorage('file')->load($profile_image[0]);
        if ($file) {
          $profile_image_url = $file->createFileUrl();
        }
      }

      $group = [
        'leaderName' => $config->get("Leader_" . ($i + 1) . '_name'),
        'designation' => $config->get("Leader_" . ($i + 1) . '_designation'),
        'linkedinLink' => $config->get("Leader_" . ($i + 1) . '_linkedin_link'),
        'profileImage' => $profile_image_url,
      ];
      $content[] = $group;
    }

    $anchor_ref = $config->get('anchor_reference');
    $anchorReference = '';
    $field_description = '';
    $latest_news = [];

    if (!empty($anchor_ref)) {
      $anchorUser = $this->entityTypeManager->getStorage('user')->load($anchor_ref);
      if ($anchorUser) {
        $anchorReference = $anchorUser->getAccountName();
        if ($anchorUser->hasField('field_description')) {
          $field_description_field = $anchorUser->get('field_description');
          $field_description = $field_description_field->isEmpty() ? '' : $field_description_field->value;
        }

        $latest_news = $this->getLatestNewsByAnchor($anchor_ref);
      }
    }

    return [
      '#theme' => 'custom_form_data',
      '#content' => $content,
      '#anchorReference' => $anchorReference,
      '#description' => $field_description,
      '#latestNews' => $latest_news,
      '#cache' => [
        'tags' => ['config:aboutus_form.settings'],
      ],
      '#attached' => [
        'library' => [
          'aboutus_form/custom_form_styles',
        ],
      ],
    ];
  }

  /**
   * Retrieves the latest news items created by the specified anchor.
   *
   * @param int $anchor_id
   *   The user ID of the anchor.
   *
   * @return array
   *   An array of node entities representing the latest news items.
   */
  protected function getLatestNewsByAnchor($anchor_id) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', 'news')
      ->condition('status', 1)
      ->condition('uid', $anchor_id)
      ->sort('created', 'DESC')
      ->range(0, 3)
      ->accessCheck(TRUE);

    $nids = $query->execute();

    return $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
  }

}
