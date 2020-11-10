<?php

namespace Drupal\daterange_simplify\TwigExtension;

use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Language\LanguageInterface;

/**
 * Formatting theme support.
 */
class Extension extends Twig_Extension {

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName() {
    return 'daterange_simplify.twig_extensions';
  }

  /**
   * Filters.
   */
  public function getFilters() {
    return [
      // Formatting filter.
      new Twig_SimpleFilter('intl_date', [$this, 'format']),
    ];
  }

  /**
   * Functions.
   */
  public function getFunctions() {
    return [
      // Language.
      new Twig_SimpleFunction('current_lang', [$this, 'currentLanguageId']),
    ];
  }

  /**
   * Format a date (or datetime) according to intl formatting options.
   */
  public function format($datetime, $dateformat = 'medium', $timeformat = 'none', $lang = NULL) {
    $simplify_service = \Drupal::service('daterange_simplify.simplify');
    $lang = $lang ?? $this->currentLanguageId();
    if (!($datetime instanceof DrupalDateTime)) {
      $datetime = $simplify_service->toDrupalDateTime($datetime);
    }
    return $simplify_service->datetime($datetime, $dateformat, $timeformat, $lang);
  }

  /**
   * Return the two-letter current locale identifier.
   */
  public function currentLanguageId($from_url = TRUE) {
    $language_type = $from_url ? LanguageInterface::TYPE_URL : LanguageInterface::TYPE_INTERFACE;
    return \Drupal::languageManager()->getCurrentLanguage($language_type)->getId();
  }

}
