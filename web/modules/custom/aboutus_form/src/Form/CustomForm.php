<?php

namespace Drupal\aboutus_form\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing leadership data.
 */
class CustomForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CustomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_module_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'aboutus_form.settings',
    ];
  }

  /**
   * Builds the configuration form for leadership data.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The rendered form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('aboutus_form.settings');
    $num_groups = $form_state->get('num_groups') ?? 1;

    $form['actions']['add_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      '#submit' => ['::addMore'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'groups-wrapper',
      ],
    ];

    $form['groups'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Leaderships'),
      '#prefix' => '<div id="groups-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['#tree'] = TRUE;

    $deleted_groups = $form_state->get('deleted_groups') ?: [];

    for ($i = 0; $i < $num_groups; $i++) {
      if (in_array($i, $deleted_groups)) {
        continue;
      }

      $form['groups'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Leader @num', ['@num' => $i + 1]),
      ];

      $form['groups'][$i]['leader_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $config->get('Leader_' . ($i + 1) . '_name'),
      ];

      $form['groups'][$i]['designation'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Designation'),
        '#default_value' => $config->get('Leader_' . ($i + 1) . '_designation'),
      ];

      $form['groups'][$i]['linkedin_link'] = [
        '#type' => 'textfield',
        '#title' => $this->t('LinkedIn Profile Link'),
        '#default_value' => $config->get('Leader_' . ($i + 1) . '_linkedin_link'),
      ];

      $form['groups'][$i]['profile_image'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Profile Image'),
        '#upload_location' => 'public://leaders_images/',
        '#upload_validators' => [
          'file_validate_extensions' => ['png', 'jpg', 'jpeg'],
        ],
        '#default_value' => $config->get('Leader_' . ($i + 1) . '_profile_image'),
      ];

      $form['groups'][$i]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#submit' => ['::deleteLeader'],
        '#ajax' => [
          'callback' => '::addMoreCallback',
          'wrapper' => 'groups-wrapper',
        ],
        '#name' => 'delete_' . $i,
      ];
    }

    $form['anchor_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Best Anchor of the Week'),
      '#prefix' => '<div id="anchor-section-wrapper">',
      '#suffix' => '</div>',
    ];

    $anchor_ref = $config->get('anchor_reference');
    $default_anchor = !empty($anchor_ref) ? $this->entityTypeManager->getStorage('user')->load($anchor_ref) : NULL;

    $form['anchor_section']['anchor_ref'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select a News Anchor'),
      '#target_type' => 'user',
      '#required' => TRUE,
      '#default_value' => $default_anchor,
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'filter' => [
          'role' => ['content_editor'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submits the configuration form for leadership data.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('aboutus_form.settings');
    $num_groups = $form_state->get('num_groups') ?? 1;

    $deleted_groups = $form_state->get('deleted_groups') ?: [];

    for ($i = 0; $i < $num_groups; $i++) {
      if (in_array($i, $deleted_groups)) {
        continue;
      }

      $leader_name = $form_state->getValue(['groups', $i, 'leader_name']);
      $designation = $form_state->getValue(['groups', $i, 'designation']);
      $linkedin_link = $form_state->getValue(['groups', $i, 'linkedin_link']);
      $profile_image = $form_state->getValue(['groups', $i, 'profile_image']);

      $config->set('Leader_' . ($i + 1) . '_name', $leader_name)
        ->set('Leader_' . ($i + 1) . '_designation', $designation)
        ->set('Leader_' . ($i + 1) . '_linkedin_link', $linkedin_link)
        ->set('Leader_' . ($i + 1) . '_profile_image', $profile_image);
    }

    $anchor_ref_value = $form_state->getValue(['anchor_section', 'anchor_ref']);
    $config->set('anchor_reference', $anchor_ref_value);
    $config->set('num_groups', $num_groups);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Adds another group of leader fields.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function addMore(array &$form, FormStateInterface $form_state) {
    $num_groups = $form_state->get('num_groups') ?? 1;
    $form_state->set('num_groups', $num_groups + 1);
    $form_state->setRebuild();
  }

  /**
   * Callback for removing a leader group.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $num_groups = $form_state->get('num_groups') ?? 1;
    if ($num_groups > 1) {
      $num_groups--;
      $form_state->set('num_groups', $num_groups);
      $form_state->setRebuild();
    }
  }

  /**
   * Callback for adding more leader fields.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The form elements to return.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['groups'];
  }

  /**
   * Callback for deleting a specific leader.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function deleteLeader(array &$form, FormStateInterface $form_state) {
    $button_name = $form_state->getTriggeringElement()['#name'];
    if (preg_match('/delete_(\d+)/', $button_name, $matches)) {
      $index_to_delete = (int) $matches[1];
      $deleted_groups = $form_state->get('deleted_groups', []);

      $deleted_groups[] = $index_to_delete;
      $form_state->set('deleted_groups', array_unique($deleted_groups));

      $config = $this->config('aboutus_form.settings');
      $config->set('Leader_' . ($index_to_delete + 1) . '_name', NULL)
        ->set('Leader_' . ($index_to_delete + 1) . '_designation', NULL)
        ->set('Leader_' . ($index_to_delete + 1) . '_linkedin_link', NULL)
        ->set('Leader_' . ($index_to_delete + 1) . '_profile_image', NULL);
      $config->save();

      $form_state->setRebuild();
    }
  }

}
