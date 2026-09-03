<?php

namespace Quant\Collection;

class Collection implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    // ==== BASIC ====

    public function all(): array
    {
        return $this->items;
    }

    public function first()
    {
        return $this->items[0] ?? null;
    }

    public function last()
    {
        return $this->items[count($this->items) - 1] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    // ==== PLUCK ====

    public function pluck(string $key): array
    {
        return array_column($this->items, $key);
    }

    public function pluckWithKey(string $value, string $key): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item[$key] ?? null] = $item[$value] ?? null;
        }
        return $result;
    }

    // ==== FILTER ====

    public function where(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) == $value));
    }

    public function whereIn(string $key, array $values): self
    {
        return new self(array_filter($this->items, fn($item) => in_array($item[$key] ?? null, $values)));
    }

    public function whereNotIn(string $key, array $values): self
    {
        return new self(array_filter($this->items, fn($item) => !in_array($item[$key] ?? null, $values)));
    }

    public function whereStrict(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) === $value));
    }

    public function whereGt(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) > $value));
    }

    public function whereLt(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) < $value));
    }

    public function whereGte(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) >= $value));
    }

    public function whereLte(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) <= $value));
    }

    public function whereNull(string $key): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) === null));
    }

    public function whereNotNull(string $key): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) !== null));
    }

    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback));
    }

    // ==== TRANSFORM ====

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }
        return $this;
    }

    public function groupBy(string $key): array
    {
        $groups = [];
        foreach ($this->items as $item) {
            $groupKey = $item[$key] ?? 'null';
            $groups[$groupKey][] = $item;
        }
        return $groups;
    }

    public function keyBy(string $key): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item[$key] ?? null] = $item;
        }
        return $result;
    }

    // ==== AGGREGATE ====

    public function sum(string $key): float
    {
        return array_sum(array_column($this->items, $key));
    }

    public function avg(string $key): float
    {
        $values = array_column($this->items, $key);
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }

    public function min(string $key)
    {
        $values = array_column($this->items, $key);
        return count($values) > 0 ? min($values) : null;
    }

    public function max(string $key)
    {
        $values = array_column($this->items, $key);
        return count($values) > 0 ? max($values) : null;
    }

    // ==== SORT ====

    public function sortBy(string $key, bool $ascending = true): self
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $ascending) {
            $aVal = $a[$key] ?? null;
            $bVal = $b[$key] ?? null;

            if ($aVal == $bVal) return 0;

            $result = $aVal < $bVal ? -1 : 1;
            return $ascending ? $result : -$result;
        });
        return new self($items);
    }

    public function sortByDesc(string $key): self
    {
        return $this->sortBy($key, false);
    }

    // ==== SLICE ====

    public function slice(int $offset, ?int $length = null): self
    {
        return new self(array_slice($this->items, $offset, $length));
    }

    public function take(int $limit): self
    {
        return new self(array_slice($this->items, 0, $limit));
    }

    // ==== CONVERT ====

    public function toArray(): array
    {
        return $this->items;
    }

    public function toJson(): string
    {
        return json_encode($this->items);
    }

    public function jsonSerialize(): array
    {
        return $this->items;
    }

    // ==== INTERFACE ====

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
        $this->items = array_values($this->items);
    }
}
