<?php

namespace Drupal\daterange_simplify\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\daterange_simplify\Simplify;

/**
 * Wrapper for flack/ranger daterange simplifier.
 *
 * @FieldFormatter(
 *   id = "intl_formatter",
 *   label = @Translation("Intl"),
 *   field_types = {"date", "datetime"}
 * )
 */
class IntlFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Simplify service.
   *
   * @var \Drupal\daterange_simplify\Simplify
   */
  protected $simplify;

  /**
   * Constructs a SimplifyFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language Manager interface.
   * @param \Drupal\daterange_simplify\Simplify $simplify
   *   Simplify service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LanguageManagerInterface $language_manager, Simplify $simplify) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->language_manager = $language_manager;
    $this->simplify = $simplify;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('language_manager'),
      $container->get('daterange_simplify.simplify')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'time_format' => 'short',
      'date_format' => 'medium',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $allowed_date_options = $this->simplify::getAllowedFormats();
    $allowed_time_options = $this->simplify::getAllowedFormats(TRUE);

    $form['date_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Date format'),
      '#options' => array_combine($allowed_date_options, $allowed_date_options),
      '#default_value' => $this->getSetting('date_format'),
    ];

    $form['time_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Time format'),
      '#options' => array_combine($allowed_time_options, $allowed_time_options),
      '#default_value' => $this->getSetting('time_format'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = [];

    $summary[] = $this->simplify->datetime(new DrupalDateTime(),
                    $settings['date_format'],
                    $settings['time_format'],
                    $this->language_manager->getCurrentLanguage()->getId()
    );

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $settings = $this->getSettings();

    foreach ($items as $item) {
      $time = $this->simplify->toDrupalDateTime($item->value);

      $simplified = $this->simplify->datetime($time,
        $settings['date_format'],
        $settings['time_format'],
        $langcode
      );

      $elements[] = [
        '#type' => '#markup',
        '#markup' => $simplified,
      ];
    }

    return $elements;
  }

}
