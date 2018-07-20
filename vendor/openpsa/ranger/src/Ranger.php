<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 * @author CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace OpenPsa\Ranger;

use IntlDateFormatter;
use DateTime;
use RuntimeException;
use InvalidArgumentException;

class Ranger
{
    const ERA = 0;
    const YEAR = 1;
    const QUARTER = 2;
    const MONTH = 3;
    const WEEK = 4;
    const DAY = 5;
    const AM = 6;
    const HOUR = 7;
    const MINUTE = 8;
    const SECOND = 9;
    const TIMEZONE = -1;

    private $pattern_characters = [
        'G' => self::ERA,
        'y' => self::YEAR,
        'Y' => self::YEAR,
        'u' => self::YEAR,
        'U' => self::YEAR,
        'r' => self::YEAR,
        'Q' => self::QUARTER,
        'q' => self::QUARTER,
        'M' => self::MONTH,
        'L' => self::MONTH,
        'w' => self::WEEK,
        'W' => self::WEEK,
        'd' => self::DAY,
        'D' => self::DAY,
        'F' => self::DAY,
        'g' => self::DAY,
        'E' => self::DAY,
        'e' => self::DAY,
        'c' => self::DAY,
        'a' => self::AM,
        'h' => self::HOUR,
        'H' => self::HOUR,
        'k' => self::HOUR,
        'K' => self::HOUR,
        'm' => self::MINUTE,
        's' => self::SECOND,
        'S' => self::SECOND,
        'A' => self::SECOND,
        'z' => self::TIMEZONE,
        'Z' => self::TIMEZONE,
        'O' => self::TIMEZONE,
        'v' => self::TIMEZONE,
        'V' => self::TIMEZONE,
        'X' => self::TIMEZONE,
        'x' => self::TIMEZONE
    ];

    /**
     * @var string
     */
    private $escape_character = "'";

    /**
     * @var array
     */
    private $pattern_mask;

    /**
     * @var int
     */
    private $precision;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $range_separator = '–';

    /**
     * @var string
     */
    private $date_time_separator = ', ';

    /**
     * @var int
     */
    private $date_type = IntlDateFormatter::MEDIUM;

    /**
     * @var int
     */
    private $time_type = IntlDateFormatter::NONE;

    /**
     *
     * @param string $locale
     */
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @param int $type
     * @return self
     */
    public function setDateType($type)
    {
        if ($type !== $this->date_type) {
            $this->date_type = $type;
            $this->pattern_mask = [];
            $this->precision = 0;
        }
        return $this;
    }

    /**
     * @param int $type
     * @return self
     */
    public function setTimeType($type)
    {
        if ($type !== $this->time_type) {
            $this->time_type = $type;
            $this->pattern_mask = [];
            $this->precision = 0;
        }
        return $this;
    }

    /**
     * @param string $separator
     * @return self
     */
    public function setRangeSeparator($separator)
    {
        $this->range_separator = $separator;
        return $this;
    }

    /**
     * @param string $separator
     * @return self
     */
    public function setDateTimeSeparator($separator)
    {
        $this->date_time_separator = $separator;
        return $this;
    }

    /**
     *
     * @param mixed $start
     * @param mixed $end
     * @return string
     */
    public function format($start, $end)
    {
        $start = $this->prepare_date($start);
        $end = $this->prepare_date($end);

        $best_match = $this->find_best_match($start, $end);

        $this->parse_pattern();

        $start_tokens = $this->tokenize($start);
        $end_tokens = $this->tokenize($end);

        $left = '';
        foreach ($this->pattern_mask as $i => $part) {
            if ($part['delimiter']) {
                $left .= $part['content'];
            } else {
                if ($part['content'] > $best_match) {
                    break;
                }
                $left .= $start_tokens[$i]['content'];
            }
        }

        if ($best_match >= $this->precision) {
            // the given dates are identical for the requested rendering
            return $left;
        }

        $right = '';
        for ($j = count($this->pattern_mask) - 1; $j + 1 > $i; $j--) {
            $part = $end_tokens[$j];
            if ($part['type'] == 'delimiter') {
                $right = $part['content'] . $right;
            } else {
                if ($part['type'] > $best_match) {
                    break;
                }
                $right = $part['content'] . $right;
            }
        }

        $left_middle = '';
        $right_middle = '';
        for ($k = $i; $k <= $j; $k++) {
            $left_middle .= $start_tokens[$k]['content'];
            $right_middle .= $end_tokens[$k]['content'];
        }

        return $left . $left_middle . $this->get_range_separator($best_match) . $right_middle . $right;
    }

    /**
     * @param mixed $input
     * @throws InvalidArgumentException
     * @return \DateTime
     */
    private function prepare_date($input)
    {
        if ($input instanceof DateTime) {
            return $input;
        }
        if (is_string($input)) {
            return new Datetime($input);
        }
        if (is_int($input)) {
            $date = new Datetime;
            $date->setTimestamp($input);
            return $date;
        }
        if ($input === null) {
            return new Datetime;
        }
        throw new InvalidArgumentException("Don't know how to handle " . gettype($input));
    }

    /**
     * @param int $best_match
     */
    private function get_range_separator($best_match)
    {
        $intl = new IntlDateFormatter($this->locale, $this->date_type, $this->time_type);

        $provider_class = 'OpenPsa\\Ranger\\Provider\\' . ucfirst(substr($intl->getLocale(), 0, 2)) . 'Provider';

        if (!class_exists($provider_class)) {
            $provider_class = 'OpenPsa\\Ranger\\Provider\\DefaultProvider';
        }
        $provider = new $provider_class();

        return $provider->modifySeparator($intl, $best_match, $this->range_separator);
    }

    /**
     * @param DateTime $date
     * @return array
     */
    private function tokenize(DateTime $date)
    {
        $tokens = [];

        $intl = new IntlDateFormatter($this->locale, $this->date_type, IntlDateFormatter::NONE, $date->getTimezone()->getName());
        $formatted = $intl->format((int) $date->format('U'));

        if ($this->time_type !== IntlDateFormatter::NONE) {
            $intl = new IntlDateFormatter($this->locale, IntlDateFormatter::NONE, $this->time_type, $date->getTimezone()->getName());
            $formatted .= $this->date_time_separator . $intl->format((int) $date->format('U'));
        }

        $type = null;
        foreach ($this->pattern_mask as $i => $part) {
            if ($part['delimiter']) {
                $parts = explode($part['content'], $formatted, 2);

                if (count($parts) == 2) {
                    $tokens[] = ['type' => $type, 'content' => $parts[0]];
                    $formatted = $parts[1];
                }
                $tokens[] = ['type' => 'delimiter', 'content' => $part['content']];
            } else {
                $type = $part['content'];
            }
        }
        if (!$part['delimiter']) {
            $tokens[] =  ['type' => $type, 'content' => $formatted];
        }
        return $tokens;
    }

    /**
     *
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    private function find_best_match(DateTime $start, DateTime $end)
    {
        $best_match = -2;
        if ($start->format('Y') !== $end->format('Y')) {
            $best_match = self::TIMEZONE;
        } elseif ($start->format('m') !== $end->format('m')) {
            $best_match = self::YEAR;
        } elseif ($start->format('d') !== $end->format('d')) {
            $best_match = self::MONTH;
        } elseif ($start->format('a') !== $end->format('a')) {
            $best_match = self::DAY;
        } elseif ($start->format('H') !== $end->format('H')) {
            $best_match = self::AM;
        } elseif ($start->format('i') !== $end->format('i')) {
            //it makes no sense to display something like 10:00:00 - 30:00...
            $best_match = self::AM;
        } elseif ($start->format('s') !== $end->format('s')) {
            //it makes no sense to display something like 10:00:00 - 30:00...
            $best_match = self::AM;
        } else {
            $best_match = self::SECOND;
        }

        //set to same time to avoid DST problems
        $tz_end = clone $end;
        $tz_end->setTimestamp((int) $start->format('U'));
        if (   $start->format('T') !== $tz_end->format('T')
            || (   $this->time_type !== IntlDateFormatter::NONE
                && $best_match < self::DAY)) {
            $best_match = -2;
        }
        return $best_match;
    }

    private function parse_pattern()
    {
        if (!empty($this->pattern_mask)) {
            return;
        }

        $intl = new IntlDateFormatter($this->locale, $this->date_type, IntlDateFormatter::NONE);
        $pattern = $intl->getPattern();

        if ($this->time_type !== IntlDateFormatter::NONE) {
            $intl = new IntlDateFormatter($this->locale, IntlDateFormatter::NONE, $this->time_type);
            $pattern .= "'" . $this->date_time_separator . "'" . $intl->getPattern();
        }

        $esc_active = false;
        $part = ['content' => '', 'delimiter' => false];
        foreach (str_split($pattern) as $char) {
            //@todo the esc char handling is untested
            if ($char == $this->escape_character) {
                if ($esc_active) {
                    $esc_active = false;
                    if ($part['content'] === '') {
                        //Literal '
                        $part['content'] = $char;
                    }

                    $this->push_to_mask($part);
                    $part = ['content' => '', 'delimiter' => false];
                } else {
                    $esc_active = true;
                    $this->push_to_mask($part);
                    $part = ['content' => '', 'delimiter' => true];
                }
            } elseif ($esc_active) {
                $part['content'] .= $char;
            } elseif (!array_key_exists($char, $this->pattern_characters)) {
                if ($part['delimiter'] === false) {
                    $this->push_to_mask($part);
                    $part = ['content' => $char, 'delimiter' => true];
                } else {
                    $part['content'] .= $char;
                }
            } else {
                if ($part['delimiter'] === true) {
                    $this->push_to_mask($part);
                    $part = ['content' => $this->pattern_characters[$char], 'delimiter' => false];
                } else {
                    if (   $part['content'] !== ''
                        && $part['content'] !== $this->pattern_characters[$char]) {
                        throw new RuntimeException('missing separator between date parts');
                    }
                    $part['content'] = $this->pattern_characters[$char];
                }
            }
        }
        $this->push_to_mask($part);
    }

    /**
     * @param array $part
     */
    private function push_to_mask(array $part)
    {
        if ($part['content'] !== '') {
            $this->pattern_mask[] = $part;
            $this->precision = max($this->precision, $part['content']);
        }
    }
}