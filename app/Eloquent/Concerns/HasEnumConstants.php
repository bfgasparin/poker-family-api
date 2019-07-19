<?php

namespace App\Eloquent\Concerns;

/**
 * Eloquent Model helper for Models that contains Enum Constants
 */
trait HasEnumConstants
{
    /**
     * Return the constant enum title in the given field of the Eloquent model
     *
     * @param string $field the name of the field
     * @return string
     */
    public function getEnumTitleOf(string $field)
    {
        return static::enumValueToHumanReadable(
            $this->$field ?? ''
        );
    }

    /**
     * Return all enum constant titles for the given field
     *
     * @param string $field
     * @return array
     */
    public static function getAllEnumValuesOf(string $field)
    {
        $prefix = strtoupper($field) . '_';
        $r = new \ReflectionClass(static::class);
        $result = [];
        foreach($r->getConstants() as $name => $value) {
            if (substr($name, 0, strlen($prefix)) === $prefix) {
                $result[] = $value;
            }
        }
        return $result;
    }

    /**
     * Return and array of [value => title] of all constants for the given field
     *
     * @param string $field
     * @return array
     */
    public static function getAllEnumValuesAndTitlesOf(string $field)
    {
        $prefix = strtoupper($field) . '_';
        $r = new \ReflectionClass(static::class);
        $result = [];

        foreach($r->getConstants() as $name => $value) {
            if (substr($name, 0, strlen($prefix)) === $prefix) {
                $result[$value] = static::enumValueToHumanReadable($value);
            }
        }

        return $result;
    }

    /**
     * Return and array of [name => value] of all constants for the given field
     *
     * @param string $field the name of the field
     * @return string
     */
    public static function getAllEnumNamesAndValuesOf(string $field)
    {
        $prefix = strtoupper($field) . '_';
        $r = new \ReflectionClass(static::class);
        $result = [];

        foreach($r->getConstants() as $name => $value) {
            if (substr($name, 0, strlen($prefix)) === $prefix) {
                $result[str_replace($prefix, '', $name)] = $value;
            }
        }

        return $result;
    }

    /**
     * Return and array of [name => title] of all constants for the given field
     *
     * @param string $field the name of the field
     * @return string
     */
    public static function getAllEnumNamesAndTitlesOf(string $field)
    {
        $prefix = strtoupper($field) . '_';
        $r = new \ReflectionClass(static::class);
        $result = [];

        foreach($r->getConstants() as $name => $value) {
            if (substr($name, 0, strlen($prefix)) === $prefix) {
                $result[str_replace($prefix, '', $name)] = static::enumValueToHumanReadable($value);
            }
        }

        return $result;
    }

    /**
     * Convert the value to a more human readable text
     *
     * @param string $value
     * @return string
     */
    protected static function enumValueToHumanReadable(string $value) : string {
        return human_case($value);
    }
}


