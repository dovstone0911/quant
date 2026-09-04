<?php

namespace Quant\Collection;

/**
 * Collection - Data manipulation utility for query results
 * 
 * Provides a fluent interface for filtering, sorting, and transforming data
 */
class Collection implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
    private array $items;

    /**
     * Constructeur
     *
     * @param array $items Tableau initial
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Retourne tous les éléments
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Retourne le premier élément
     *
     * @return mixed|null
     */
    public function first()
    {
        return $this->items[0] ?? null;
    }

    /**
     * Retourne le dernier élément
     *
     * @return mixed|null
     */
    public function last()
    {
        return $this->items[count($this->items) - 1] ?? null;
    }

    /**
     * Retourne le nombre d'éléments
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Vérifie si la collection est vide
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Vérifie si la collection n'est pas vide
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Récupère les valeurs d'une colonne
     *
     * @param string $key Nom de la colonne
     * @return array
     *
     * @example
     * $emails = $collection->pluck('email');
     * // ['john@example.com', 'jane@example.com']
     */
    public function pluck(string $key): array
    {
        return array_column($this->items, $key);
    }

    /**
     * Récupère les valeurs d'une colonne avec une clé personnalisée
     *
     * @param string $value Colonne pour les valeurs
     * @param string $key Colonne pour les clés
     * @return array
     *
     * @example
     * $names = $collection->pluckWithKey('name', 'id');
     * // [1 => 'John', 2 => 'Jane']
     */
    public function pluckWithKey(string $value, string $key): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item[$key] ?? null] = $item[$value] ?? null;
        }
        return $result;
    }

    /**
     * Filtre les éléments où la clé est égale à la valeur (comparaison non stricte)
     *
     * @param string $key Nom du champ
     * @param mixed $value Valeur
     * @return self
     *
     * @example
     * $admins = $collection->where('role', 'admin');
     */
    public function where(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) == $value));
    }

    /**
     * Filtre les éléments où la clé est dans le tableau de valeurs
     *
     * @param string $key Nom du champ
     * @param array $values Tableau de valeurs
     * @return self
     *
     * @example
     * $users = $collection->whereIn('role', ['admin', 'moderator']);
     */
    public function whereIn(string $key, array $values): self
    {
        return new self(array_filter($this->items, fn($item) => in_array($item[$key] ?? null, $values)));
    }

    /**
     * Filtre les éléments où la clé n'est pas dans le tableau de valeurs
     *
     * @param string $key Nom du champ
     * @param array $values Tableau de valeurs
     * @return self
     *
     * @example
     * $users = $collection->whereNotIn('role', ['admin', 'moderator']);
     */
    public function whereNotIn(string $key, array $values): self
    {
        return new self(array_filter($this->items, fn($item) => !in_array($item[$key] ?? null, $values)));
    }

    /**
     * Filtre les éléments où la clé est égale à la valeur (comparaison stricte)
     *
     * @param string $key Nom du champ
     * @param mixed $value Valeur
     * @return self
     *
     * @example
     * $users = $collection->whereStrict('id', 1);
     */
    public function whereStrict(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) === $value));
    }

    /**
     * Filtre les éléments où la clé est supérieure à la valeur
     *
     * @param string $key Nom du champ
     * @param mixed $value Valeur
     * @return self
     *
     * @example
     * $adults = $collection->whereGt('age', 18);
     */
    public function whereGt(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) > $value));
    }

    /**
     * Filtre les éléments où la clé est inférieure à la valeur
     *
     * @param string $key Nom du champ
     * @param mixed $value Valeur
     * @return self
     *
     * @example
     * $young = $collection->whereLt('age', 25);
     */
    public function whereLt(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) < $value));
    }

    /**
     * Filtre les éléments où la clé est supérieure ou égale à la valeur
     *
     * @param string $key Nom du champ
     * @param mixed $value Valeur
     * @return self
     *
     * @example
     * $adults = $collection->whereGte('age', 18);
     */
    public function whereGte(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) >= $value));
    }

    /**
     * Filtre les éléments où la clé est inférieure ou égale à la valeur
     *
     * @param string $key Nom du champ
     * @param mixed $value Valeur
     * @return self
     *
     * @example
     * $young = $collection->whereLte('age', 25);
     */
    public function whereLte(string $key, $value): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? 0) <= $value));
    }

    /**
     * Filtre les éléments où la clé est null
     *
     * @param string $key Nom du champ
     * @return self
     *
     * @example
     * $users = $collection->whereNull('deleted_at');
     */
    public function whereNull(string $key): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) === null));
    }

    /**
     * Filtre les éléments où la clé n'est pas null
     *
     * @param string $key Nom du champ
     * @return self
     *
     * @example
     * $users = $collection->whereNotNull('email_verified_at');
     */
    public function whereNotNull(string $key): self
    {
        return new self(array_filter($this->items, fn($item) => ($item[$key] ?? null) !== null));
    }

    /**
     * Filtre les éléments avec une fonction personnalisée
     *
     * @param callable $callback Fonction de filtrage
     * @return self
     *
     * @example
     * $users = $collection->filter(fn($user) => $user['age'] > 18);
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback));
    }

    /**
     * Applique une fonction à chaque élément et retourne une nouvelle collection
     *
     * @param callable $callback Fonction de transformation
     * @return self
     *
     * @example
     * $names = $collection->map(fn($user) => strtoupper($user['name']));
     */
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    /**
     * Applique une fonction à chaque élément (sans retour)
     *
     * @param callable $callback Fonction à exécuter
     * @return self
     *
     * @example
     * $collection->each(fn($user) => echo $user['name']);
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }
        return $this;
    }

    /**
     * Groupe les éléments par une clé
     *
     * @param string $key Nom du champ de regroupement
     * @return array
     *
     * @example
     * $grouped = $collection->groupBy('role');
     * // ['admin' => [...], 'user' => [...]]
     */
    public function groupBy(string $key): array
    {
        $groups = [];
        foreach ($this->items as $item) {
            $groupKey = $item[$key] ?? 'null';
            $groups[$groupKey][] = $item;
        }
        return $groups;
    }

    /**
     * Indexe la collection par une clé
     *
     * @param string $key Nom du champ d'indexation
     * @return array
     *
     * @example
     * $byId = $collection->keyBy('id');
     * // [1 => [...], 2 => [...]]
     */
    public function keyBy(string $key): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item[$key] ?? null] = $item;
        }
        return $result;
    }

    /**
     * Calcule la somme des valeurs d'une colonne
     *
     * @param string $key Nom du champ
     * @return float
     *
     * @example
     * $totalAge = $collection->sum('age');
     */
    public function sum(string $key): float
    {
        return array_sum(array_column($this->items, $key));
    }

    /**
     * Calcule la moyenne des valeurs d'une colonne
     *
     * @param string $key Nom du champ
     * @return float
     *
     * @example
     * $averageAge = $collection->avg('age');
     */
    public function avg(string $key): float
    {
        $values = array_column($this->items, $key);
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }

    /**
     * Retourne la valeur minimale d'une colonne
     *
     * @param string $key Nom du champ
     * @return mixed|null
     *
     * @example
     * $minAge = $collection->min('age');
     */
    public function min(string $key)
    {
        $values = array_column($this->items, $key);
        return count($values) > 0 ? min($values) : null;
    }

    /**
     * Retourne la valeur maximale d'une colonne
     *
     * @param string $key Nom du champ
     * @return mixed|null
     *
     * @example
     * $maxAge = $collection->max('age');
     */
    public function max(string $key)
    {
        $values = array_column($this->items, $key);
        return count($values) > 0 ? max($values) : null;
    }

    /**
     * Trie la collection par une clé
     *
     * @param string $key Nom du champ de tri
     * @param bool $ascending Tri ascendant (true) ou descendant (false)
     * @return self
     *
     * @example
     * $sorted = $collection->sortBy('name');
     * $sorted = $collection->sortBy('age', false);
     */
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

    /**
     * Trie la collection par une clé en ordre décroissant
     *
     * @param string $key Nom du champ de tri
     * @return self
     *
     * @example
     * $sorted = $collection->sortByDesc('age');
     */
    public function sortByDesc(string $key): self
    {
        return $this->sortBy($key, false);
    }

    /**
     * Extrait une tranche de la collection
     *
     * @param int $offset Position de début
     * @param int|null $length Nombre d'éléments
     * @return self
     *
     * @example
     * $page = $collection->slice(0, 10);
     */
    public function slice(int $offset, ?int $length = null): self
    {
        return new self(array_slice($this->items, $offset, $length));
    }

    /**
     * Prend les N premiers éléments
     *
     * @param int $limit Nombre d'éléments
     * @return self
     *
     * @example
     * $top5 = $collection->take(5);
     */
    public function take(int $limit): self
    {
        return new self(array_slice($this->items, 0, $limit));
    }

    /**
     * Convertit la collection en tableau
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Convertit la collection en JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->items);
    }

    /**
     * Sérialise en JSON
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * Retourne un itérateur pour foreach
     *
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Vérifie si un offset existe (ArrayAccess)
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Récupère un élément par offset (ArrayAccess)
     *
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Définit un élément par offset (ArrayAccess)
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Supprime un élément par offset (ArrayAccess)
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
        $this->items = array_values($this->items);
    }
}
