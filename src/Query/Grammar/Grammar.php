<?php

namespace Quant\Query\Grammar;

abstract class Grammar
{
    abstract public function compileSelect(string $table, array $select, array $where, array $orderBy, ?int $limit, ?int $offset): string;
    abstract public function compileInsert(string $table, array $data): string;
    abstract public function compileUpdate(string $table, array $data, array $where): string;
    abstract public function compileDelete(string $table, array $where): string;
    abstract public function compileCount(string $table, array $where): string;
    abstract public function quoteIdentifier(string $identifier): string;

    protected function compileWhere(array $where): array
    {
        $conditions = [];
        $bindings = [];

        foreach ($where as $key => $value) {
            $field = $this->quoteIdentifier($key);

            if (is_array($value) && isset($value['operator'])) {
                $operator = $value['operator'];
                $val = $value['value'];
            } elseif (is_array($value) && count($value) === 2) {
                [$operator, $val] = $value;
            } else {
                $operator = '=';
                $val = $value;
            }

            $operator = strtoupper($operator);

            if ($operator === 'IN' && is_array($val)) {
                $placeholders = [];
                foreach ($val as $i => $v) {
                    $param = ':w_' . count($bindings);
                    $bindings[$param] = $v;
                    $placeholders[] = $param;
                }
                $conditions[] = $field . " IN (" . implode(', ', $placeholders) . ")";
                continue;
            }

            if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                $conditions[] = $field . " " . $operator;
                continue;
            }

            $param = ':w_' . count($bindings);
            $conditions[] = $field . " " . $operator . " " . $param;
            $bindings[$param] = $val;
        }

        return [$conditions, $bindings];
    }

    protected function getBindingsFromData(array $data): array
    {
        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[':' . $key] = $value;
        }
        return $bindings;
    }
}
