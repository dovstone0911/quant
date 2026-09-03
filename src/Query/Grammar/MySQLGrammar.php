<?php

namespace Quant\Query\Grammar;

class MySQLGrammar extends Grammar
{
    public function compileSelect(string $table, array $select, array $where, array $orderBy, ?int $limit, ?int $offset): string
    {
        $sql = "SELECT " . implode(', ', $select) . " FROM " . $this->quoteIdentifier($table);

        [$whereSql, $bindings] = $this->compileWhere($where);
        if (!empty($whereSql)) {
            $sql .= " WHERE " . implode(' AND ', $whereSql);
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $orderBy);
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
            if ($offset !== null) {
                $sql .= " OFFSET {$offset}";
            }
        }

        return $sql;
    }

    public function compileInsert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->quoteIdentifier($table),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    public function compileUpdate(string $table, array $data, array $where): string
    {
        $sets = [];
        foreach ($data as $key => $value) {
            $sets[] = $this->quoteIdentifier($key) . " = :{$key}";
        }

        $sql = "UPDATE " . $this->quoteIdentifier($table) . " SET " . implode(', ', $sets);

        [$whereSql, $bindings] = $this->compileWhere($where);
        if (!empty($whereSql)) {
            $sql .= " WHERE " . implode(' AND ', $whereSql);
        }

        return $sql;
    }

    public function compileDelete(string $table, array $where): string
    {
        $sql = "DELETE FROM " . $this->quoteIdentifier($table);

        [$whereSql, $bindings] = $this->compileWhere($where);
        if (!empty($whereSql)) {
            $sql .= " WHERE " . implode(' AND ', $whereSql);
        }

        return $sql;
    }

    public function compileCount(string $table, array $where): string
    {
        $sql = "SELECT COUNT(*) FROM " . $this->quoteIdentifier($table);

        [$whereSql, $bindings] = $this->compileWhere($where);
        if (!empty($whereSql)) {
            $sql .= " WHERE " . implode(' AND ', $whereSql);
        }

        return $sql;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return "`{$identifier}`";
    }
}
