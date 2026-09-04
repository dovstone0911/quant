<?php

namespace Quant\Query;

use Quant\Database\Connection;
use Quant\Database\Config;
use Quant\Query\Grammar\MySQLGrammar;
use Quant\Query\Grammar\PostgreSQLGrammar;
use Quant\Query\Grammar\SQLiteGrammar;
use Quant\Query\Grammar\Grammar;
use Quant\Collection\Collection;

/**
 * Query Builder - Construit et exécute des requêtes SQL avec syntaxe NoSQL-like
 */
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
    private array $joins = [];
    private ?Finders $finders = null;

    public function __construct(string $collection)
    {
        static $loaded = false;
        if (!$loaded) {
            Config::fromEnv();
            $loaded = true;
        }
        $this->collection = $collection;
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    public function finders(): Finders
    {
        if ($this->finders === null) {
            $this->finders = new Finders($this);
        }
        return $this->finders;
    }

    // ============================================
    // FIND METHODS
    // ============================================

    public function find(int|string $id, array $select = ['*']): ?array
    {
        return $this->finders()->find($id, $select);
    }

    public function findOrFail(int|string $id, array $select = ['*']): array
    {
        return $this->finders()->findOrFail($id, $select);
    }

    public function findMany(array $ids, array $select = ['*']): array
    {
        return $this->finders()->findMany($ids, $select);
    }

    public function findOneBy(array $where, array $select = ['*'], array $orderBy = []): ?array
    {
        return $this->finders()->findOneBy($where, $select, $orderBy);
    }

    public function findAllBy(array $where, array $select = ['*'], array $orderBy = [], ?int $limit = null): array
    {
        return $this->finders()->findAllBy($where, $select, $orderBy, $limit);
    }

    public function findByOrFail(array $where, array $select = ['*']): array
    {
        return $this->finders()->findByOrFail($where, $select);
    }

    public function firstOr(array $where, mixed $default = null, array $select = ['*']): mixed
    {
        return $this->finders()->firstOr($where, $default, $select);
    }

    // ============================================
    // CACHE
    // ============================================

    public function cache(string|int $time): self
    {
        if (is_int($time)) {
            $this->cacheTTL = $time;
            return $this;
        }
        $this->cacheTTL = self::parseTime($time);
        return $this;
    }

    private static function parseTime(string $time): int
    {
        $time = strtolower(trim($time));
        $units = [
            'second' => 1,
            'seconds' => 1,
            'minute' => 60,
            'minutes' => 60,
            'hour' => 3600,
            'hours' => 3600,
            'day' => 86400,
            'days' => 86400,
            'week' => 604800,
            'weeks' => 604800,
        ];

        foreach ($units as $unit => $seconds) {
            if (str_contains($time, $unit)) {
                $number = (int) filter_var($time, FILTER_SANITIZE_NUMBER_INT);
                if ($number === 0) $number = 1;
                return $number * $seconds;
            }
        }
        $number = (int) filter_var($time, FILTER_SANITIZE_NUMBER_INT);
        return $number > 0 ? $number : 3600;
    }

    public function clearCache(): self
    {
        self::$cache = [];
        return $this;
    }

    // ============================================
    // READ (SELECT)
    // ============================================

    public function fetch(array $params = []): array
    {
        $this->applyParams($params);

        $cacheKey = $this->generateCacheKey();
        if ($this->cacheTTL && isset(self::$cache[$cacheKey])) {
            $cached = self::$cache[$cacheKey];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
            unset(self::$cache[$cacheKey]);
        }

        $grammar = $this->getGrammar();
        [$sql, $whereBindings] = $grammar->compileSelectWithBindings(
            $this->collection,
            $this->select,
            $this->where,
            $this->orderBy,
            $this->limit,
            $this->offset
        );

        $this->bindings = $whereBindings;
        $result = $this->execute($sql, $this->bindings);
        $result = $this->applyCasts($result);

        if ($this->cacheTTL) {
            self::$cache[$cacheKey] = [
                'data' => $result,
                'expires' => time() + $this->cacheTTL,
                'created' => time()
            ];
        }

        return $result;
    }

    public function first(array $params = []): ?array
    {
        $params['limit'] = 1;
        $results = $this->fetch($params);
        return $results[0] ?? null;
    }

    public function last(array $params = []): ?array
    {
        if (empty($this->orderBy)) {
            $this->orderBy('created_at', 'DESC');
        }
        $params['limit'] = 1;
        $results = $this->fetch($params);
        return $results[0] ?? null;
    }

    public function get(array $params = []): Collection
    {
        $this->bindings = [];
        $results = $this->fetch($params);
        return new Collection($results);
    }

    public function pluck(string $column, ?string $key = null, array $params = []): array
    {
        $params['select'] = [$column];
        if ($key) $params['select'][] = $key;
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
        [$sql, $whereBindings] = $grammar->compileCountWithBindings($this->collection, $this->where);
        $result = $this->execute($sql, $whereBindings);
        return (int) ($result[0]['COUNT(*)'] ?? 0);
    }

    public function exists(array $params = []): bool
    {
        return $this->count($params) > 0;
    }

    // ============================================
    // WRITE (INSERT, UPDATE, DELETE)
    // ============================================

    public function insert(array $data): int
    {
        $this->clearCache();
        $grammar = $this->getGrammar();
        $sql = $grammar->compileInsert($this->collection, $data);

        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[$key] = $value;
        }

        $this->execute($sql, $bindings);
        return (int) Connection::get()->lastInsertId();
    }

    public function insertBatch(array $data): array
    {
        $this->clearCache();
        $ids = [];
        foreach ($data as $item) {
            $ids[] = $this->insert($item);
        }
        return $ids;
    }

    public function update(array $data, array $params = []): int
    {
        $this->clearCache();
        $this->applyParams($params);
        $grammar = $this->getGrammar();
        [$sql, $whereBindings] = $grammar->compileUpdateWithBindings($this->collection, $data, $this->where);

        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[$key] = $value;
        }

        return $this->execute($sql, array_merge($bindings, $whereBindings));
    }

    public function delete(array $params = []): int
    {
        $this->clearCache();
        $this->applyParams($params);
        $grammar = $this->getGrammar();
        [$sql, $whereBindings] = $grammar->compileDeleteWithBindings($this->collection, $this->where);
        return $this->execute($sql, $whereBindings);
    }

    public function truncate(): int
    {
        $this->clearCache();
        $grammar = $this->getGrammar();
        $sql = "TRUNCATE TABLE " . $grammar->quoteIdentifier($this->collection);
        return $this->execute($sql);
    }

    // ============================================
    // CONDITIONS - WHERE
    // ============================================

    /**
     * Ajoute une ou plusieurs conditions WHERE
     * 
     * @param string|array|callable $filter Nom du champ, tableau de conditions, ou closure
     * @param string|null $operator Opérateur
     * @param mixed|null $value Valeur
     * @return self
     * 
     * @example
     * // Syntaxe avec 2 paramètres
     * $users = quant('users')->where('status', 'active');
     * 
     * // Syntaxe avec 3 paramètres
     * $users = quant('users')->where('status', '=', 'active');
     * 
     * // Syntaxe tableau
     * $users = quant('users')->where(['status' => 'active']);
     * $users = quant('users')->where(['roles' => ['contains', 'admin']]);
     * $users = quant('users')->where(['roles' => ['contains', ['family', 'admin']]]);
     * $users = quant('users')->where(['roles' => ['contains_any', ['family', 'admin']]]);
     * $users = quant('users')->where(['age' => ['>=', 18]]);
     * 
     * // WHERE avec closure
     * $users = quant('users')->where(function ($q) {
     *     $q->where('status', 'active')
     *       ->orWhere('role', 'admin');
     * });
     */
    public function where($filter, $operator = null, $value = null): self
    {
        // CAS 1: CLOSURE (sous-requête / groupe)
        if ($filter instanceof \Closure) {
            $subQuery = new self($this->collection);
            $filter($subQuery);
            $subWhere = $subQuery->getWhere();
            if (!empty($subWhere)) {
                $this->where[] = [
                    'type' => 'group',
                    'boolean' => 'AND',
                    'conditions' => $subWhere
                ];
            }
            return $this;
        }

        // CAS 2: where('field', 'value')
        if (!is_array($filter) && $operator !== null && $value === null) {
            $this->where[$filter] = ['operator' => '=', 'value' => $operator];
            return $this;
        }

        // CAS 3: where('field', 'operator', 'value')
        if (!is_array($filter) && $operator !== null && $value !== null) {
            $this->where[$filter] = ['operator' => strtoupper($operator), 'value' => $value];
            return $this;
        }

        // CAS 4: where(['field' => ...])
        foreach ($filter as $key => $val) {
            $op = (is_array($val) && isset($val[0]) && is_string($val[0])) ? strtoupper($val[0]) : null;

            // 4.1 CONTAINS — toutes les valeurs (AND)
            if ($op === 'CONTAINS' && count($val) === 2) {
                $val1 = $val[1];

                if (is_array($val1) && count($val1) > 1) {
                    // plusieurs valeurs → autant de conditions AND JSON_CONTAINS à plat
                    foreach ($val1 as $v) {
                        $this->where[] = ['type' => 'and_contains', 'field' => $key, 'value' => $v];
                    }
                } elseif (is_array($val1) && count($val1) === 1) {
                    $this->where[$key] = ['operator' => 'CONTAINS', 'value' => $val1[0]];
                } else {
                    $this->where[$key] = ['operator' => 'CONTAINS', 'value' => $val1];
                }
                continue;
            }

            // 4.2 CONTAINS_ANY — au moins une valeur (OR)
            if ($op === 'CONTAINS_ANY' && count($val) === 2) {
                $val1 = $val[1];

                if (is_array($val1) && count($val1) > 1) {
                    $this->where[] = ['type' => 'contains_or', 'field' => $key, 'values' => $val1];
                } elseif (is_array($val1) && count($val1) === 1) {
                    $this->where[$key] = ['operator' => 'CONTAINS', 'value' => $val1[0]];
                } else {
                    $this->where[$key] = ['operator' => 'CONTAINS', 'value' => $val1];
                }
                continue;
            }

            // 4.3 NOT CONTAINS — aucune des valeurs (AND)
            if ($op === 'NOT CONTAINS' && count($val) === 2) {
                $val1 = $val[1];

                if (is_array($val1) && count($val1) > 1) {
                    foreach ($val1 as $v) {
                        $this->where[] = ['type' => 'and_not_contains', 'field' => $key, 'value' => $v];
                    }
                } elseif (is_array($val1) && count($val1) === 1) {
                    $this->where[$key] = ['operator' => 'NOT CONTAINS', 'value' => $val1[0]];
                } else {
                    $this->where[$key] = ['operator' => 'NOT CONTAINS', 'value' => $val1];
                }
                continue;
            }

            // 4.4 NOT_CONTAINS_ANY — exclut si au moins une valeur présente (OR de NOT)
            if ($op === 'NOT_CONTAINS_ANY' && count($val) === 2 && is_array($val[1]) && count($val[1]) > 1) {
                $this->where[] = ['type' => 'not_contains_or', 'field' => $key, 'values' => $val[1]];
                continue;
            }

            // 4.5 operator explicite
            if (is_array($val) && isset($val['operator'])) {
                $this->where[$key] = $val;
                continue;
            }

            // 4.6 ['operator', 'value'] ou 3 éléments
            if (is_array($val) && (count($val) === 2 || count($val) === 3)) {
                $this->where[$key] = ['operator' => strtoupper($val[0]), 'value' => $val[1]];
                continue;
            }

            // 4.7 where simple
            $this->where[$key] = ['operator' => '=', 'value' => $val];
        }

        return $this;
    }

    /**
     * Ajoute une condition OR WHERE
     * 
     * @param string|array|callable $field Nom du champ, tableau de conditions, ou closure
     * @param string|null $operator Opérateur
     * @param mixed|null $value Valeur
     * @return self
     * 
     * @example
     * // OR simple
     * $users = quant('users')
     *     ->where('status', 'active')
     *     ->orWhere('role', 'admin')
     *     ->get();
     * 
     * // OR avec contains
     * $users = quant('users')
     *     ->where('mle', '3318d')
     *     ->orWhere(['roles' => ['contains', 'admin']])
     *     ->get();
     * 
     * // OR avec closure
     * $users = quant('users')
     *     ->where('mle', '3318d')
     *     ->orWhere(function ($q) {
     *         $q->where(['roles' => ['contains', ['family', 'admin']]]);
     *     })
     *     ->get();
     */
    public function orWhere($field, $operator = null, $value = null): self
    {
        // ============================================
        // OR avec closure
        // ============================================
        if ($field instanceof \Closure) {
            $subQuery = new self($this->collection);
            $field($subQuery);
            $subWhere = $subQuery->getWhere();
            if (!empty($subWhere)) {
                $this->where[] = [
                    'type' => 'or_group',
                    'conditions' => $subWhere
                ];
            }
            return $this;
        }

        // ============================================
        // OR avec tableau
        // ============================================
        if (is_array($field)) {
            foreach ($field as $key => $val) {
                // OR avec contains plusieurs valeurs
                if (is_array($val) && count($val) === 2 && strtoupper($val[0]) === 'CONTAINS' && is_array($val[1]) && count($val[1]) > 1) {
                    $this->where[] = ['type' => 'or_contains_multi', 'field' => $key, 'values' => $val[1]];
                    continue;
                }
                // OR avec contains simple
                if (is_array($val) && count($val) === 2 && strtoupper($val[0]) === 'CONTAINS') {
                    $this->where[] = ['type' => 'or_contains', 'field' => $key, 'value' => $val[1]];
                    continue;
                }
                // OR avec not contains
                if (is_array($val) && count($val) === 2 && strtoupper($val[0]) === 'NOT CONTAINS' && is_array($val[1]) && count($val[1]) > 1) {
                    $this->where[] = ['type' => 'or_not_contains_multi', 'field' => $key, 'values' => $val[1]];
                    continue;
                }
                if (is_array($val) && count($val) === 2 && strtoupper($val[0]) === 'NOT CONTAINS') {
                    $this->where[] = ['type' => 'or_not_contains', 'field' => $key, 'value' => $val[1]];
                    continue;
                }
                // OR avec ['operator', 'value']
                if (is_array($val) && count($val) === 2) {
                    $this->where[] = ['type' => 'or', 'field' => $key, 'operator' => $val[0], 'value' => $val[1]];
                    continue;
                }
                // OR simple
                $this->where[] = ['type' => 'or', 'field' => $key, 'operator' => '=', 'value' => $val];
            }
            return $this;
        }

        // ============================================
        // OR standard: orWhere('field', 'operator', 'value')
        // ============================================
        $this->where[] = ['type' => 'or', 'field' => $field, 'operator' => $operator ?? '=', 'value' => $value];
        return $this;
    }

    public function getWhere(): array
    {
        return $this->where;
    }

    // ============================================
    // CONDITIONS SPÉCIFIQUES
    // ============================================

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

    // ============================================
    // JSON CONDITIONS
    // ============================================

    public function contains(string $field, $value): self
    {
        $this->where[$field] = ['operator' => 'CONTAINS', 'value' => $value];
        return $this;
    }

    public function notContains(string $field, $value): self
    {
        $this->where[$field] = ['operator' => 'NOT CONTAINS', 'value' => $value];
        return $this;
    }

    // ============================================
    // SORT
    // ============================================

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

    // ============================================
    // LIMIT & OFFSET
    // ============================================

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

    // ============================================
    // PAGINATION
    // ============================================

    public function paginate(int $perPage = 15, int $page = 1, array $params = []): array
    {
        $this->applyParams($params);

        $whereBackup = $this->where;
        $total = $this->count();
        $this->where = $whereBackup;

        $offset = ($page - 1) * $perPage;
        $this->limit($perPage)->offset($offset);
        $data = $this->fetch();

        $lastPage = (int) ceil($total / $perPage);
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'has_previous' => $page > 1,
                'has_next' => $page < $lastPage,
                'previous_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $lastPage ? $page + 1 : null,
            ]
        ];
    }

    public function simplePaginate(int $perPage = 15, int $page = 1, array $params = []): array
    {
        $this->applyParams($params);

        $whereBackup = $this->where;
        $total = $this->count();
        $this->where = $whereBackup;

        $offset = ($page - 1) * $perPage;
        $this->limit($perPage)->offset($offset);
        $data = $this->fetch();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage)
        ];
    }

    // ============================================
    // SELECT
    // ============================================

    public function select(array $fields): self
    {
        $this->select = array_map(function ($field) {
            return $field === 'id' ? 'uid' : $field;
        }, $fields);
        return $this;
    }

    public function addSelect(array $fields): self
    {
        $fields = array_map(function ($field) {
            return $field === 'id' ? 'uid' : $field;
        }, $fields);
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

    // ============================================
    // JOIN
    // ============================================

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    // ============================================
    // HELPERS
    // ============================================

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

    // ============================================
    // PRIVATE
    // ============================================

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

        if (empty($bindings)) {
            $bindings = $this->extractBindingsFromWhere();
        }

        foreach ($bindings as $key => $value) {
            $param = str_starts_with($key, ':') ? $key : ':' . $key;
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();

        if (str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
            return $stmt->fetchAll();
        }

        return $stmt->rowCount();
    }

    private function extractBindingsFromWhere(): array
    {
        $bindings = [];
        $counter = 0;

        foreach ($this->where as $field => $condition) {
            // Gérer les groupes (sous-requêtes)
            if (isset($condition['type']) && ($condition['type'] === 'group' || $condition['type'] === 'or_group')) {
                foreach ($condition['conditions'] as $subField => $subCondition) {
                    $operator = $subCondition['operator'] ?? '=';
                    $value = $subCondition['value'] ?? null;
                    $operator = strtoupper($operator);

                    if ($operator === 'CONTAINS' || $operator === 'NOT CONTAINS') {
                        $param = 'contains_' . $subField . '_' . $counter++;
                        if (is_array($value)) {
                            $value = $value[0] ?? null;
                        }
                        $jsonValue = is_string($value) ? '"' . $value . '"' : json_encode($value);
                        $bindings[':' . $param] = $jsonValue;
                    } elseif ($operator === '=' || $operator === '>' || $operator === '>=' || $operator === '<' || $operator === '<=' || $operator === 'LIKE') {
                        $param = 'w_' . $subField . '_' . $counter++;
                        $bindings[':' . $param] = $value;
                    }
                }
                continue;
            }

            // contains_or
            if (isset($condition['type']) && $condition['type'] === 'contains_or') {
                foreach ($condition['values'] as $i => $v) {
                    $param = 'contains_or_' . $condition['field'] . '_' . $counter++;
                    $jsonValue = is_string($v) ? '"' . $v . '"' : json_encode($v);
                    $bindings[':' . $param] = $jsonValue;
                }
                continue;
            }

            // or_contains
            if (isset($condition['type']) && $condition['type'] === 'or_contains') {
                $param = 'or_contains_' . $condition['field'] . '_' . $counter++;
                $jsonValue = is_string($condition['value']) ? '"' . $condition['value'] . '"' : json_encode($condition['value']);
                $bindings[':' . $param] = $jsonValue;
                continue;
            }

            // or_contains_multi
            if (isset($condition['type']) && $condition['type'] === 'or_contains_multi') {
                foreach ($condition['values'] as $i => $v) {
                    $param = 'or_contains_multi_' . $condition['field'] . '_' . $counter++;
                    $jsonValue = is_string($v) ? '"' . $v . '"' : json_encode($v);
                    $bindings[':' . $param] = $jsonValue;
                }
                continue;
            }

            // not_contains_or
            if (isset($condition['type']) && $condition['type'] === 'not_contains_or') {
                foreach ($condition['values'] as $i => $v) {
                    $param = 'not_contains_or_' . $condition['field'] . '_' . $counter++;
                    $jsonValue = is_string($v) ? '"' . $v . '"' : json_encode($v);
                    $bindings[':' . $param] = $jsonValue;
                }
                continue;
            }

            // or_not_contains
            if (isset($condition['type']) && $condition['type'] === 'or_not_contains') {
                $param = 'or_not_contains_' . $condition['field'] . '_' . $counter++;
                $jsonValue = is_string($condition['value']) ? '"' . $condition['value'] . '"' : json_encode($condition['value']);
                $bindings[':' . $param] = $jsonValue;
                continue;
            }

            // or_not_contains_multi
            if (isset($condition['type']) && $condition['type'] === 'or_not_contains_multi') {
                foreach ($condition['values'] as $i => $v) {
                    $param = 'or_not_contains_multi_' . $condition['field'] . '_' . $counter++;
                    $jsonValue = is_string($v) ? '"' . $v . '"' : json_encode($v);
                    $bindings[':' . $param] = $jsonValue;
                }
                continue;
            }

            // or standard
            if (isset($condition['type']) && $condition['type'] === 'or') {
                $param = 'or_' . $condition['field'] . '_' . $counter++;
                $bindings[':' . $param] = $condition['value'];
                continue;
            }

            // Condition normale
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            $operator = strtoupper($operator);

            if ($operator === 'CONTAINS' || $operator === 'NOT CONTAINS') {
                $param = 'contains_' . $field . '_' . $counter++;
                if (is_array($value)) {
                    $value = $value[0] ?? null;
                }
                $jsonValue = is_string($value) ? '"' . $value . '"' : json_encode($value);
                $bindings[':' . $param] = $jsonValue;
            } elseif ($operator === '=' || $operator === '>' || $operator === '>=' || $operator === '<' || $operator === '<=' || $operator === 'LIKE') {
                $param = 'w_' . $field . '_' . $counter++;
                $bindings[':' . $param] = $value;
            }
        }

        return $bindings;
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

    private function applyCasts(array $results): array
    {
        if (empty($results)) {
            return $results;
        }

        $firstRow = $results[0];
        $casts = [];

        foreach ($firstRow as $column => $value) {
            $casts[$column] = $this->detectType($value);
        }

        foreach ($results as $key => $row) {
            foreach ($casts as $column => $type) {
                if (!isset($row[$column])) {
                    continue;
                }

                $value = $row[$column];

                if ($value === null) {
                    continue;
                }

                $row[$column] = match ($type) {
                    'json' => json_decode($value, true) ?? $value,
                    'int' => (int) $value,
                    'float' => (float) $value,
                    'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'date' => $this->castDate($value),
                    'string' => (string) $value,
                    default => $value
                };
            }

            if (isset($row['uid'])) {
                $row['id'] = (int) $row['uid'];
                unset($row['uid']);
            }

            $results[$key] = $row;
        }

        return $results;
    }

    private function detectType(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value) && $this->isJson($value)) {
            return 'json';
        }

        if (is_string($value) && $this->isDate($value)) {
            return 'date';
        }

        if (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0', 'on', 'off'], true)) {
            return 'bool';
        }

        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value))) {
            return 'int';
        }

        if (is_float($value) || (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value))) {
            return 'float';
        }

        return 'string';
    }

    private function isDate(string $value): bool
    {
        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s.u',
            'Y-m-d\TH:i:s.uP',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    private function castDate(string $value): \DateTime
    {
        return new \DateTime($value);
    }

    private function isJson(string $value): bool
    {
        if (!in_array($value[0] ?? '', ['{', '['], true)) {
            return false;
        }

        json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function generateCacheKey(): string
    {
        return md5(json_encode([
            'collection' => $this->collection,
            'select' => $this->select,
            'where' => $this->where,
            'orderBy' => $this->orderBy,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'joins' => $this->joins
        ]));
    }
}
