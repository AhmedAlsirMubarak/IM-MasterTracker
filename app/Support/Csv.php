<?php

namespace App\Support;

class Csv
{
    /**
     * Make a value safe for a CSV cell.
     *
     * Text starting with = + - @ (or a tab / carriage return) is run as a
     * formula when the file is opened in Excel, so it is prefixed with an
     * apostrophe. Plain numbers such as -5 are left alone.
     */
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)) {
            return "'" . $value;
        }

        return $value;
    }
}
