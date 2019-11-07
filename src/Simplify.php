<?php
namespace Drupal\daterange_simplify;

// this is a hack -- the module should be installed by composer with dependencies
// but if these are not loaded, they're included in the module distribution
if (!class_exists('OpenPsa\Ranger\Ranger')) {
  require_once __DIR__ . '/../vendor/autoload.php';
}

use OpenPsa\Ranger\Ranger;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Daterange simplification tasks.
 */
class Simplify {

  /**
   * Return allowed formats.
   *
   * See http://php.net/manual/en/class.intldateformatter.php.
   *
   * @param bool $restrict_intl
   *   Limit options available in the absence of intl support.
   *
   * @return array
   *   Possible options.
   */
  public static function getAllowedFormats($restrict_intl = FALSE) {
    if ($restrict_intl) {
      return ['none', 'short'];
    }
    return ['none', 'full', 'long', 'medium', 'short'];
  }

  /**
   * Helper function: return an enumerated constant for the format.
   */
  protected static function getDateFormat($format) {
    switch ($format) {
      case 'none':
        return \IntlDateFormatter::NONE;

      case 'full':
        return \IntlDateFormatter::FULL;

      case 'long':
        return \IntlDateFormatter::LONG;

      case 'medium':
        return \IntlDateFormatter::MEDIUM;

      case 'short':
        return \IntlDateFormatter::SHORT;
    }
    return \IntlDateFormatter::MEDIUM;
  }

  /**
   * Simplify a date range.
   */
  public static function daterange(DrupalDateTime $start, DrupalDateTime $end, $date_format = 'medium', $time_format = 'short', $range_separator = null, $date_time_separator = null, $locale = 'en') {
    $date_format = Simplify::getDateFormat($date_format);
    $time_format = Simplify::getDateFormat($time_format);

    $ranger = new Ranger($locale);
    $ranger
      ->setDateType($date_format)
      ->setTimeType($time_format);
    if (!is_null($date_time_separator)) {
      $ranger->setDateTimeSeparator($date_time_separator);
    }
    if (!is_null($range_separator)) {
      $ranger->setRangeSeparator($range_separator);
    }
    $start = $start->format('c');
    if (empty($end)) {
      $end = $start;
    }
    else {
      $end = $end->format('c');
    }
    return $ranger->format($start, $end);
  }

  /**
   * Correct for user timezone, convert to DrupalateTime.
   */
  public function toDrupalDateTime($datetime) {
    // Assuming times are always stored as UTC...
    $ddt = new DrupalDateTime($datetime, 'UTC');

    // From DateTimeFormatterBase: convert to user TZ.
    $timezone = drupal_get_user_timezone();
    $ddt->setTimeZone(timezone_open($timezone));

    return $ddt;
  }

}
