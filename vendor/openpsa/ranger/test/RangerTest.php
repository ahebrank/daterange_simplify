<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 * @author CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace OpenPsa\Ranger;

use PHPUnit_Framework_TestCase;
use IntlDateFormatter;
use DateTime;

class RangerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider providerDateRange
     */
    public function testDateRange($language, $start, $end, $expected)
    {
        $formatter = new Ranger($language);
        $this->assertEquals($expected, $formatter->format($start, $end));
    }

    public function providerDateRange()
    {
        return [
            ['en', '2013-10-05', '2013-10-20', 'Oct 5–20, 2013'],
            ['en', '2013-10-05', '2013-11-20', 'Oct 5 – Nov 20, 2013'],
            ['en', '2012-10-05', '2013-10-20', 'Oct 5, 2012 – Oct 20, 2013'],
            ['de', '2012-10-05', '2012-10-20', '05.–20.10.2012'],
            ['de', '2012-10-05', '2012-11-20', '05.10.–20.11.2012'],
            ['de', '2012-10-05', '2013-10-20', '05.10.2012 – 20.10.2013']
        ];
    }

    /**
     * @dataProvider providerDateTimeRange
     */
    public function testDateTimeRange($language, $start, $end, $expected)
    {
        $formatter = new Ranger($language);
        $formatter->setTimeType(IntlDateFormatter::SHORT);
        $this->assertEquals($expected, $formatter->format($start, $end));
    }

    public function providerDateTimeRange()
    {
        return [
            ['en', '2013-10-05 01:01:01', '2013-10-20 00:00:00', 'Oct 5, 2013, 1:01 AM – Oct 20, 2013, 12:00 AM'],
            ['en', '2013-10-05 10:00:01', '2013-10-05 13:30:00', 'Oct 5, 2013, 10:00 AM – 1:30 PM'],
            ['de', '2013-10-05 01:01:01', '2013-10-20 00:00:00', '05.10.2013, 01:01 – 20.10.2013, 00:00'],
            ['de', '2013-10-05 10:00:01', '2013-10-05 13:30:00', '05.10.2013, 10:00 – 13:30'],
        ];
    }

    /**
     * @dataProvider providerFullDateRange
     */
    public function testFullDateRange($language, $start, $end, $expected)
    {
        $formatter = new Ranger($language);
        $formatter->setDateType(IntlDateFormatter::FULL);
        $this->assertEquals($expected, $formatter->format($start, $end));
    }

    public function providerFullDateRange()
    {
        return [
            ['en', '2013-10-05', '2013-10-20', 'Saturday, October 5 – Sunday, October 20, 2013'],
            ['en', '2013-10-05', '2013-11-20', 'Saturday, October 5 – Wednesday, November 20, 2013'],
            ['en', '2012-10-05', '2013-10-20', 'Friday, October 5, 2012 – Sunday, October 20, 2013'],
            ['de', '2012-10-05', '2012-10-20', 'Freitag, 5. – Samstag, 20. Oktober 2012'],
            ['de', '2012-10-05', '2012-11-20', 'Freitag, 5. Oktober – Dienstag, 20. November 2012'],
            ['de', '2012-10-05', '2013-10-20', 'Freitag, 5. Oktober 2012 – Sonntag, 20. Oktober 2013']
        ];
    }

    /**
     * @dataProvider providerShortDateRange
     */
    public function testShortDateRange($language, $start, $end, $expected)
    {
        $formatter = new Ranger($language);
        $formatter->setDateType(IntlDateFormatter::SHORT);
        $this->assertEquals($expected, $formatter->format($start, $end));
    }

    public function providerShortDateRange()
    {
        return [
            ['en', '2012-10-05', '2013-10-20', '10/5/12 – 10/20/13'],
            ['en', '2012-10-05', '2012-10-05', '10/5/12'],
            ['de', '2012-10-05', '2012-10-20', '05.–20.10.12'],
            ['de', '2012-10-05', '2012-11-20', '05.10.–20.11.12'],
            ['de', '2012-10-05', '2012-10-05', '05.10.12'],
            ['de', '2012-10-05 00:00:01', '2012-10-05 23:59:59', '05.10.12'],
            ['de', '2012-10-05', '2013-10-20', '05.10.12 – 20.10.13']
        ];
    }

    public function testCustomOptions()
    {
        $ranger = new Ranger('en');
        $ranger
            ->setRangeSeparator(' -- ')
            ->setDateTimeSeparator(': ')
            ->setDateType(IntlDateFormatter::LONG)
            ->setTimeType(IntlDateFormatter::SHORT);

        $formatted = $ranger->format('2013-10-05 10:00:01', '2013-10-05 13:30:00');
        $this->assertEquals('October 5, 2013: 10:00 AM -- 1:30 PM', $formatted);
    }

    public function testEscapeCharParsing()
    {
        $ranger = new Ranger('en');
        $ranger
            ->setRangeSeparator(' and ')
            ->setDateTimeSeparator(', between ')
            ->setDateType(IntlDateFormatter::LONG)
            ->setTimeType(IntlDateFormatter::SHORT);

        $formatted = $ranger->format('2013-10-05 10:00:01', '2013-10-05 13:30:00');
        $this->assertEquals('October 5, 2013, between 10:00 AM and 1:30 PM', $formatted);
    }

    public function testDateTime()
    {
        $ranger = new Ranger('en');
        $start = new DateTime('2013-10-05');
        $end = new DateTime('2013-10-20');

        $formatted = $ranger->format($start, $end);
        $this->assertEquals('Oct 5–20, 2013', $formatted);
    }

    public function testTimestamp()
    {
        $ranger = new Ranger('en');
        $formatted = $ranger->format(1380931200, 1382227200);
        $this->assertEquals('Oct 5–20, 2013', $formatted);
    }

    public function testTimestampTimezone()
    {
        $backup = date_default_timezone_get();
        if (!date_default_timezone_set('Europe/Berlin')) {
            $this->markTestSkipped("Couldn't set timezone");
        }
        $ranger = new Ranger('de');
        $ranger->setTimeType(IntlDateFormatter::SHORT);
        $formatted = $ranger->format(1457478001, 1457481600);
        date_default_timezone_set($backup);
        $this->assertEquals('09.03.2016, 00:00 – 01:00', $formatted);
    }
}
