<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

/**
 * Creates the Event Ids needed for deduping conversion events from
 * tags and API
 */
class EventIdGenerator
{
    /**
     * Creates a guidv4 id
     *
     * @return string A 36 character string containing dashes.
     */
    public static function guidv4()
    {
        $data = openssl_random_pseudo_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
