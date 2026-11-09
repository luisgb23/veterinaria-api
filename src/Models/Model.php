<?php

namespace App\Models;

use PDO;

/**
 * Base de los modelos de la veterinaria.
 *
 * Todas las tablas usan baja lógica: la columna "...Estado" vale 1 si el registro está
 * activo y 0 si fue eliminado. Los listados y búsquedas solo devuelven los activos.
 */
abstract class Model
{
    public function __construct(protected PDO $db)
    {
    }

    /** Fecha y hora actual para las columnas FchCreacion / FchModificacion. */
    protected static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Ejecuta un listado paginado.
     *
     * @param string $select columnas, ej: "SELECT m.MascotaId, ..."
     * @param string $from   tabla y joins, ej: "FROM mascotas m JOIN especies e ON ..."
     * @param string[] $where condiciones que se unen con AND
     * @return array{items: array, total: int}
     */
    protected function paginateQuery(string $select, string $from, array $where, array $params, string $orderBy, int $page, int $perPage): array
    {
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $count = $this->db->prepare('SELECT COUNT(*) ' . $from . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $limit = max(1, $perPage);
        $offset = max(0, ($page - 1) * $limit);

        // $limit y $offset ya son enteros validados, es seguro concatenarlos.
        $stmt = $this->db->prepare("{$select} {$from}{$whereSql} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);

        return [
            'items' => array_map([$this, 'format'], $stmt->fetchAll()),
            'total' => $total,
        ];
    }

    /** Agrega a $where/$params un filtro LIKE sobre varias columnas (unidas con OR). */
    protected static function addSearch(?string $search, array $columns, array &$where, array &$params): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $parts = [];
        // Con prepares nativos no se puede repetir un mismo marcador nombrado.
        foreach ($columns as $i => $column) {
            $parts[] = "{$column} LIKE :search{$i}";
            $params[":search{$i}"] = '%' . $search . '%';
        }

        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    protected static function intOrNull($value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    /** Convierte una fila de la base al formato de la API. */
    abstract protected function format(array $row): array;
}
