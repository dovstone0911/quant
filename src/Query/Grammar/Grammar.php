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

    public function compileSelectWithBindings(string $table, array $select, array $where, array $orderBy, ?int $limit, ?int $offset): array
    {
        $sql = $this->compileSelect($table, $select, $where, $orderBy, $limit, $offset);
        [, $bindings] = $this->compileWhere($where);
        return [$sql, $bindings];
    }

    public function compileCountWithBindings(string $table, array $where): array
    {
        $sql = $this->compileCount($table, $where);
        [, $bindings] = $this->compileWhere($where);
        return [$sql, $bindings];
    }

    public function compileUpdateWithBindings(string $table, array $data, array $where): array
    {
        $sql = $this->compileUpdate($table, $data, $where);
        [, $whereBindings] = $this->compileWhere($where);

        $dataBindings = [];
        foreach ($data as $key => $value) {
            $dataBindings[$key] = $value;
        }

        return [$sql, array_merge($dataBindings, $whereBindings)];
    }

    public function compileDeleteWithBindings(string $table, array $where): array
    {
        $sql = $this->compileDelete($table, $where);
        [, $bindings] = $this->compileWhere($where);
        return [$sql, $bindings];
    }

    /**
     * Compile WHERE clause with support for:
     * - Simple conditions: ['field' => ['operator' => '=', 'value' => 'something']]
     * - OR conditions: ['type' => 'or', 'field' => 'field', 'operator' => '=', 'value' => 'something']
     * - CONTAINS: ['operator' => 'CONTAINS', 'value' => 'admin']
     * - Group conditions: ['type' => 'group', 'conditions' => [...]]
     * - OR Group conditions: ['type' => 'or_group', 'conditions' => [...]]
     * - contains_or: ['type' => 'contains_or', 'field' => 'roles', 'values' => ['admin', 'moderator']]
     * - or_contains: ['type' => 'or_contains', 'field' => 'roles', 'value' => 'admin']
     * - or_contains_multi: ['type' => 'or_contains_multi', 'field' => 'roles', 'values' => ['admin', 'moderator']]
     * - not_contains_or: ['type' => 'not_contains_or', 'field' => 'roles', 'values' => ['admin', 'moderator']]
     * - or_not_contains: ['type' => 'or_not_contains', 'field' => 'roles', 'value' => 'admin']
     * - or_not_contains_multi: ['type' => 'or_not_contains_multi', 'field' => 'roles', 'values' => ['admin', 'moderator']]
     */
    protected function compileWhere(array $where): array
    {
        $conditions = [];
        $bindings = [];
        $hasWhere = false;

        foreach ($where as $field => $condition) {
            // ============================================
            // GROUPE (issu d'une closure) — récursif
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'group') {
                [$groupSql, $groupBindings] = $this->compileWhere($condition['conditions']);

                // Suffixe DÉTERMINISTE basé sur la position du groupe dans $where
                // (pas de uniqid/random : doit être identique à chaque appel de compileWhere)
                $suffix = '_g' . (is_int($field) ? $field : md5((string) $field));

                foreach ($groupBindings as $k => $v) {
                    $newKey = $k . $suffix;
                    $bindings[$newKey] = $v;
                    $groupSql = str_replace(':' . $k, ':' . $newKey, $groupSql);
                }

                $boolean = strtoupper($condition['boolean'] ?? 'AND');
                $clause = "(" . $groupSql . ")";
                $conditions[] = $hasWhere ? "$boolean $clause" : $clause;
                $hasWhere = true;
                continue;
            }

            // ============================================
            // AND CONTAINS (élément d'un AND multiple, généré par where())
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'and_contains') {
                $param = 'and_contains_' . $condition['field'] . '_' . count($bindings);
                $jsonValue = is_string($condition['value']) ? '"' . $condition['value'] . '"' : json_encode($condition['value']);
                $bindings[$param] = $jsonValue;
                $clause = "JSON_CONTAINS(" . $condition['field'] . ", :" . $param . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            // ============================================
            // AND NOT CONTAINS
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'and_not_contains') {
                $param = 'and_not_contains_' . $condition['field'] . '_' . count($bindings);
                $jsonValue = is_string($condition['value']) ? '"' . $condition['value'] . '"' : json_encode($condition['value']);
                $bindings[$param] = $jsonValue;
                $clause = "NOT JSON_CONTAINS(" . $condition['field'] . ", :" . $param . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            // ============================================
            // CONTAINS_OR (au moins une valeur)
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'contains_or') {
                $orConditions = [];
                foreach ($condition['values'] as $v) {
                    $param = 'contains_or_' . $condition['field'] . '_' . count($bindings);
                    $jsonValue = is_string($v) ? '"' . $v . '"' : json_encode($v);
                    $bindings[$param] = $jsonValue;
                    $orConditions[] = "JSON_CONTAINS(" . $condition['field'] . ", :" . $param . ")";
                }
                $clause = "(" . implode(' OR ', $orConditions) . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            // ============================================
            // NOT_CONTAINS_OR
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'not_contains_or') {
                $orConditions = [];
                foreach ($condition['values'] as $v) {
                    $param = 'not_contains_or_' . $condition['field'] . '_' . count($bindings);
                    $jsonValue = is_string($v) ? '"' . $v . '"' : json_encode($v);
                    $bindings[$param] = $jsonValue;
                    $orConditions[] = "NOT JSON_CONTAINS(" . $condition['field'] . ", :" . $param . ")";
                }
                $clause = "(" . implode(' OR ', $orConditions) . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            // ============================================
            // OR avec contains simple — orWhere()
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'or_contains') {
                $param = 'or_contains_' . $condition['field'] . '_' . count($bindings);
                $jsonValue = is_string($condition['value']) ? '"' . $condition['value'] . '"' : json_encode($condition['value']);
                $bindings[$param] = $jsonValue;

                if (!$hasWhere) {
                    $conditions[] = "JSON_CONTAINS(" . $condition['field'] . ", :" . $param . ")";
                    $hasWhere = true;
                } else {
                    $conditions[] = "OR JSON_CONTAINS(" . $condition['field'] . ", :" . $param . ")";
                }
                continue;
            }

            // ============================================
            // OR avec contains multiple — orWhere()
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'or_contains_multi') {
                $orConditions = [];
                foreach ($condition['values'] as $v) {
                    $param = 'or_contains_multi_' . $condition['field'] . '_' . count($bindings);
                    $jsonValue = is_string($v) ? '"' . $v . '"' : json_encode($v);
                    $bindings[$param] = $jsonValue;
                    $orConditions[] = "JSON_CONTAINS(" . $condition['field'] . ", :" . $param . ")";
                }

                if (!$hasWhere) {
                    $conditions[] = "(" . implode(' OR ', $orConditions) . ")";
                    $hasWhere = true;
                } else {
                    $conditions[] = "OR (" . implode(' OR ', $orConditions) . ")";
                }
                continue;
            }

            // ============================================
            // OR standard — orWhere()
            // ============================================
            if (isset($condition['type']) && $condition['type'] === 'or') {
                $param = 'or_' . $condition['field'] . '_' . count($bindings);
                $bindings[$param] = $condition['value'];

                if (!$hasWhere) {
                    $conditions[] = $condition['field'] . " " . $condition['operator'] . " :" . $param;
                    $hasWhere = true;
                } else {
                    $conditions[] = "OR " . $condition['field'] . " " . $condition['operator'] . " :" . $param;
                }
                continue;
            }

            // ============================================
            // CONDITIONS NORMALES (clé associative = nom du champ)
            // ============================================
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            $operator = strtoupper($operator);

            if ($operator === 'CONTAINS') {
                $param = 'contains_' . $field . '_' . count($bindings);
                $jsonValue = is_string($value) ? '"' . $value . '"' : json_encode($value);
                $bindings[$param] = $jsonValue;
                $clause = "JSON_CONTAINS(" . $field . ", :" . $param . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            if ($operator === 'NOT CONTAINS') {
                $param = 'not_contains_' . $field . '_' . count($bindings);
                $jsonValue = is_string($value) ? '"' . $value . '"' : json_encode($value);
                $bindings[$param] = $jsonValue;
                $clause = "NOT JSON_CONTAINS(" . $field . ", :" . $param . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            if ($operator === 'IN' && is_array($value)) {
                $placeholders = [];
                foreach ($value as $i => $v) {
                    $param = 'in_' . $field . '_' . $i . '_' . count($bindings);
                    $placeholders[] = ':' . $param;
                    $bindings[$param] = $v;
                }
                $clause = $field . " IN (" . implode(', ', $placeholders) . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            if ($operator === 'NOT IN' && is_array($value)) {
                $placeholders = [];
                foreach ($value as $i => $v) {
                    $param = 'nin_' . $field . '_' . $i . '_' . count($bindings);
                    $placeholders[] = ':' . $param;
                    $bindings[$param] = $v;
                }
                $clause = $field . " NOT IN (" . implode(', ', $placeholders) . ")";
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            if ($operator === 'BETWEEN' && is_array($value) && count($value) === 2) {
                $p1 = 'between_' . $field . '_1_' . count($bindings);
                $p2 = 'between_' . $field . '_2_' . count($bindings);
                $bindings[$p1] = $value[0];
                $bindings[$p2] = $value[1];
                $clause = $field . " BETWEEN :" . $p1 . " AND :" . $p2;
                $conditions[] = $hasWhere ? "AND " . $clause : $clause;
                $hasWhere = true;
                continue;
            }

            if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                $conditions[] = $hasWhere ? "AND " . $field . " " . $operator : $field . " " . $operator;
                $hasWhere = true;
                continue;
            }

            $param = 'w_' . $field . '_' . count($bindings);
            $bindings[$param] = $value;
            $clause = $field . " " . $operator . " :" . $param;
            $conditions[] = $hasWhere ? "AND " . $clause : $clause;
            $hasWhere = true;
        }

        return [implode(' ', $conditions), $bindings];
    }

    protected function getBindingsFromData(array $data): array
    {
        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[$key] = $value;
        }
        return $bindings;
    }
}
