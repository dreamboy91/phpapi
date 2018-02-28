<?php

namespace Helper;

class DateTimeUtil
{
    /**
     * @param        $time
     * @param string $timeZone
     * @param string $format
     *
     * @return null|string
     */
    public static function parseTime($time, $timeZone = 'UTC', $format = "H:i:s")
    {
        if(empty($time)){
            return null;
        }

        $date = new \DateTime($time);
        $date->setTimezone(new \DateTimeZone($timeZone));

        return $date->format($format);
    }
}