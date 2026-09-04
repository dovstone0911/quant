<?php

namespace Quant\Query;

/**
 * Finders - Méthodes de recherche d'enregistrements
 * 
 * @package Quant\Query
 * 
 * @example
 * // Via le Builder
 * $user = quant('users')->find(1);
 * $user = quant('users')->findOneBy(['email' => 'john@example.com']);
 * 
 * // Via finders()
 * $user = quant('users')->finders()->find(1);
 */
class Finders
{
    /** @var Builder */
    private Builder $builder;

    /**
     * Constructeur
     * 
     * @param Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Trouve un enregistrement par son ID (uid)
     * 
     * @param int|string $id
     * @param array $select Champs à sélectionner
     * @return array|null
     * 
     * @example
     * $user = quant('users')->find(1);
     * $user = quant('users')->find(1, ['id', 'name']);
     */
    public function find(int|string $id, array $select = ['*']): ?array
    {
        return $this->builder->select($select)->where(['uid' => $id])->first();
    }

    /**
     * Trouve un enregistrement par ID ou lance une exception
     * 
     * @param int|string $id
     * @param array $select Champs à sélectionner
     * @return array
     * @throws \Exception
     * 
     * @example
     * $user = quant('users')->findOrFail(1);
     */
    public function findOrFail(int|string $id, array $select = ['*']): array
    {
        $result = $this->find($id, $select);
        if ($result === null) {
            throw new \Exception("Record with ID {$id} not found");
        }
        return $result;
    }

    /**
     * Trouve plusieurs enregistrements par leurs IDs
     * 
     * @param array $ids
     * @param array $select Champs à sélectionner
     * @return array
     * 
     * @example
     * $users = quant('users')->findMany([1, 2, 3]);
     */
    public function findMany(array $ids, array $select = ['*']): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->builder->select($select)->whereIn('uid', $ids)->fetch();
    }

    /**
     * Trouve le premier enregistrement correspondant aux conditions (AND)
     * 
     * @param array $where Conditions (AND)
     * @param array $select Champs à sélectionner
     * @param array $orderBy Tri [field => direction]
     * @return array|null
     * 
     * @example
     * $user = quant('users')->findOneBy([
     *     'mle' => '3318d',
     *     'roles' => ['contains', 'admin']
     * ]);
     */
    public function findOneBy(array $where, array $select = ['*'], array $orderBy = []): ?array
    {
        // 🔥 Construire la requête manuellement
        $query = $this->builder->select($select);

        foreach ($where as $field => $value) {
            // 🔥 Si c'est un tableau avec contains
            if (is_array($value) && isset($value[0]) && strtoupper($value[0]) === 'CONTAINS') {
                // contains avec une valeur unique
                if (isset($value[1])) {
                    $query->contains($field, $value[1]);
                }
                // contains avec plusieurs valeurs
                if (isset($value[1]) && is_array($value[1])) {
                    foreach ($value[1] as $v) {
                        $query->contains($field, $v);
                    }
                }
            } else {
                // Condition normale
                $query->where([$field => $value]);
            }
        }

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->first();
    }

    /**
     * Trouve tous les enregistrements correspondant aux conditions (AND)
     * 
     * @param array $where Conditions (AND)
     * @param array $select Champs à sélectionner
     * @param array $orderBy Tri [field => direction]
     * @param int|null $limit
     * @return array
     * 
     * @example
     * $users = quant('users')->findAllBy(['status' => 'active']);
     * $users = quant('users')->findAllBy(
     *     ['status' => 'active'],
     *     ['id', 'name'],
     *     ['created_at' => 'DESC'],
     *     10
     * );
     */
    public function findAllBy(array $where, array $select = ['*'], array $orderBy = [], ?int $limit = null): array
    {
        $query = $this->builder->select($select)->where($where);
        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query->fetch();
    }

    /**
     * Trouve par conditions ou lance une exception
     * 
     * @param array $where Conditions (AND)
     * @param array $select Champs à sélectionner
     * @return array
     * @throws \Exception
     * 
     * @example
     * $user = quant('users')->findByOrFail(['email' => 'john@example.com']);
     */
    public function findByOrFail(array $where, array $select = ['*']): array
    {
        $result = $this->findOneBy($where, $select);
        if ($result === null) {
            $conditions = json_encode($where);
            throw new \Exception("Record not found with conditions: {$conditions}");
        }
        return $result;
    }

    /**
     * Trouve le premier enregistrement ou retourne une valeur par défaut
     * 
     * @param array $where Conditions (AND)
     * @param mixed $default Valeur par défaut
     * @param array $select Champs à sélectionner
     * @return mixed
     * 
     * @example
     * $user = quant('users')->firstOr(
     *     ['email' => 'john@example.com'],
     *     ['id' => 0, 'name' => 'Guest']
     * );
     */
    public function firstOr(array $where, mixed $default = null, array $select = ['*']): mixed
    {
        $result = $this->findOneBy($where, $select);
        return $result ?? $default;
    }
}
