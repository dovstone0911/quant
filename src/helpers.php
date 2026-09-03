<?php

/**
 * Create a new Query Builder instance
 */
function quant(string $collection): Quant\Query\Builder
{
    return new Quant\Query\Builder($collection);
}

/**
 * Create a new Collection instance
 */
function collect(array $items = []): Quant\Collection\Collection
{
    return new Quant\Collection\Collection($items);
}

/**
 * Get current timestamp
 */
function now(string $format = 'Y-m-d H:i:s'): string
{
    return date($format);
}

/**
 * Escape string for SQL LIKE
 */
function like_escape(string $value, string $escape = '\\'): string
{
    return str_replace(
        [$escape, '%', '_'],
        [$escape . $escape, $escape . '%', $escape . '_'],
        $value
    );
}
