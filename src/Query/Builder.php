<?php

namespace Quant\Query;

use Quant\Database\Connection;
use Quant\Database\Config;
use Quant\Query\Grammar\MySQLGrammar;
use Quant\Query\Grammar\PostgreSQLGrammar;
use Quant\Query\Grammar\SQLiteGrammar;
use Quant\Query\Grammar\Grammar;
use Quant\Collection\Collection;

class Builder
{
    private string $collection;
    private array $select = ['*'];
    private array $where = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];
    private ?int $cacheTTL = null;
    private static array $cache = [];

    public function __construct(string $collection)
    {
        $this->collection = $collection;
    }

    // ==== READ ====

    public function fetch(array $params = []): array
    {
        $this->applyParams($params);

        $cacheKey = $this->generateCacheKey();
        if ($this->cacheTTL && isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $grammar = $this->getGrammar();
        $sql = $grammar->compileSelect(
            $this->collection,
            $this->select,
            $this->where,
            $this->orderBy,
            $this->limit,
            $this->offset
        );

        $result = $this->execute($sql);

        if ($this->cacheTTL) {
            self::$cache[$cacheKey] = $result;
        }

        return $result;
    }

    public function first(array $params = []): ?array
    {
        $params['limit'] = 1;
        $results = $this->fetch($params);
        return $results[0] ?? null;
    }

    public function get(array $params = []): Collection
    {
        return new Collection($this->fetch($params));
    }

    public function pluck(string $column, ?string $key = null, array $params = []): array
    {
        $params['select'] = [$column];
        if ($key) {
            $params['select'][] = $key;
        }
        $results = $this->fetch($params);

        if ($key) {
            $plucked = [];
            foreach ($results as $result) {
                $plucked[$result[$key] ?? ''] = $result[$column] ?? null;
            }
            return $plucked;
        }

        return array_column($results, $column);
    }

    public function count(array $params = []): int
    {
        $this->applyParams($params);
        $grammar = $this->getGrammar();
        $sql = $grammar->compileCount($this->collection, $this->where);
        $result = $this->execute($sql);
        return (int) ($result[0]['COUNT(*)'] ?? 0);
    }

    public function exists(array $params = []): bool
    {
        $params['limit'] = 1;
        return $this->count($params) > 0;
    }

    // ==== WRITE ====

    public function insert(array $data): int|string
    {
        $grammar = $this->getGrammar();
        $sql = $grammar->compileInsert($this->collection, $data);

        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[':' . $key] = $value;
        }

        $this->execute($sql, $bindings);
        return (int) Connection::get()->lastInsertId();
    }

    public function insertBatch(array $data): array
    {
        $ids = [];
        foreach ($data as $item) {
            $ids[] = $this->insert($item);
        }
        return $ids;
    }

    public function update(array $data, array $params = []): int
    {
        $this->applyParams($params);
        $grammar = $this->getGrammar();
        $sql = $grammar->compileUpdate($this->collection, $data, $this->where);

        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[':' . $key] = $value;
        }

        return $this->execute($sql, $bindings);
    }

    public function delete(array $params = []): int
    {
        $this->applyParams($params);
        $grammar = $this->getGrammar();
        $sql = $grammar->compileDelete($this->collection, $this->where);
        return $this->execute($sql);
    }

    public function truncate(): int
    {
        $grammar = $this->getGrammar();
        $sql = "TRUNCATE TABLE " . $grammar->quoteIdentifier($this->collection);
        return $this->execute($sql);
    }

    // ==== CONDITIONS (NoSQL-like syntax) ====

    public function where(array $filter): self
    {
        foreach ($filter as $key => $value) {
            if (is_array($value) && isset($value['operator'])) {
                $this->where[$key] = $value;
            } elseif (is_array($value) && count($value) === 2) {
                $this->where[$key] = ['operator' => $value[0], 'value' => $value[1]];
            } elseif (is_array($value) && count($value) === 3) {
                $this->where[$key] = ['operator' => $value[0], 'value' => $value[1]];
            } else {
                $this->where[$key] = ['operator' => '=', 'value' => $value];
            }
        }
        return $this;
    }

    public function whereIn(string $field, array $values): self
    {
        $this->where[$field] = ['operator' => 'IN', 'value' => $values];
        return $this;
    }

    public function whereNotIn(string $field, array $values): self
    {
        $this->where[$field] = ['operator' => 'NOT IN', 'value' => $values];
        return $this;
    }

    public function whereGt(string $field, $value): self
    {
        $this->where[$field] = ['operator' => '>', 'value' => $value];
        return $this;
    }

    public function whereGte(string $field, $value): self
    {
        $this->where[$field] = ['operator' => '>=', 'value' => $value];
        return $this;
    }

    public function whereLt(string $field, $value): self
    {
        $this->where[$field] = ['operator' => '<', 'value' => $value];
        return $this;
    }

    public function whereLte(string $field, $value): self
    {
        $this->where[$field] = ['operator' => '<=', 'value' => $value];
        return $this;
    }

    public function whereLike(string $field, string $value): self
    {
        $this->where[$field] = ['operator' => 'LIKE', 'value' => $value];
        return $this;
    }

    public function whereNotLike(string $field, string $value): self
    {
        $this->where[$field] = ['operator' => 'NOT LIKE', 'value' => $value];
        return $this;
    }

    public function whereNull(string $field): self
    {
        $this->where[$field] = ['operator' => 'IS NULL', 'value' => null];
        return $this;
    }

    public function whereNotNull(string $field): self
    {
        $this->where[$field] = ['operator' => 'IS NOT NULL', 'value' => null];
        return $this;
    }

    public function whereBetween(string $field, array $values): self
    {
        $this->where[$field] = ['operator' => 'BETWEEN', 'value' => $values];
        return $this;
    }

    // ==== SORT ====

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[] = $field . ' ' . strtoupper($direction);
        return $this;
    }

    public function orderByDesc(string $field): self
    {
        return $this->orderBy($field, 'DESC');
    }

    public function orderByRaw(string $sql): self
    {
        $this->orderBy[] = $sql;
        return $this;
    }

    public function inRandomOrder(): self
    {
        $driver = Config::getDriver();
        $random = match ($driver) {
            'mysql' => 'RAND()',
            'pgsql', 'postgresql' => 'RANDOM()',
            'sqlite' => 'RANDOM()',
            default => 'RAND()'
        };
        $this->orderBy[] = $random;
        return $this;
    }

    // ==== LIMIT ====

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function page(int $page, int $perPage = 15): self
    {
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        return $this;
    }

    // ==== SELECT ====

    public function select(array $fields): self
    {
        $this->select = $fields;
        return $this;
    }

    public function addSelect(array $fields): self
    {
        $this->select = array_merge($this->select, $fields);
        return $this;
    }

    public function distinct(bool $distinct = true): self
    {
        if ($distinct) {
            $this->select = ['DISTINCT ' . implode(', ', $this->select)];
        }
        return $this;
    }

    // ==== CACHE ====

    public function cache(int $ttl = 3600): self
    {
        $this->cacheTTL = $ttl;
        return $this;
    }

    public function clearCache(): self
    {
        self::$cache = [];
        return $this;
    }

    // ==== HELPERS ====

    public function toSql(): string
    {
        $grammar = $this->getGrammar();
        return $grammar->compileSelect(
            $this->collection,
            $this->select,
            $this->where,
            $this->orderBy,
            $this->limit,
            $this->offset
        );
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    // ==== PRIVATE ====

    private function getGrammar(): Grammar
    {
        $driver = Config::getDriver();

        return match ($driver) {
            'mysql' => new MySQLGrammar(),
            'pgsql', 'postgresql' => new PostgreSQLGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default => new MySQLGrammar()
        };
    }

    private function execute(string $sql, array $bindings = []): int|array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare($sql);

        // Merge bindings
        $allBindings = array_merge($this->bindings, $bindings);

        foreach ($allBindings as $key => $value) {
            if (str_starts_with($key, ':')) {
                $stmt->bindValue($key, $value);
            } else {
                $stmt->bindValue(':' . $key, $value);
            }
        }

        $stmt->execute();
        $this->bindings = [];

        if (str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
            return $stmt->fetchAll();
        }

        return $stmt->rowCount();
    }

    private function applyParams(array $params): void
    {
        if (isset($params['select'])) $this->select = $params['select'];
        if (isset($params['where'])) $this->where($params['where']);
        if (isset($params['limit'])) $this->limit = $params['limit'];
        if (isset($params['offset'])) $this->offset = $params['offset'];
        if (isset($params['orderBy'])) {
            $direction = $params['orderBy'][1] ?? 'ASC';
            $this->orderBy[] = $params['orderBy'][0] . ' ' . strtoupper($direction);
        }
        if (isset($params['page']) && isset($params['perPage'])) {
            $this->page($params['page'], $params['perPage']);
        }
    }

    private function generateCacheKey(): string
    {
        return md5(json_encode([
            'collection' => $this->collection,
            'select' => $this->select,
            'where' => $this->where,
            'orderBy' => $this->orderBy,
            'limit' => $this->limit,
            'offset' => $this->offset
        ]));
    }
}
