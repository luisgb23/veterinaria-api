<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Utilidades comunes de los controladores: paginación y lectura de filtros de la URL.
 */
abstract class Controller
{
    /** @return array{0: int, 1: int} [page, per_page] */
    protected function pagination(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        return [$page, $perPage];
    }

    /** Responde un listado paginado con el formato estándar. */
    protected function paginated(string $message, array $result, int $page, int $perPage, array $extraMeta = []): void
    {
        Response::success($message, $result['items'], 200, [
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $result['total'],
                'total_pages' => (int) ceil($result['total'] / $perPage),
            ],
        ] + $extraMeta);
    }

    /** Filtro de id en la URL (?mascota_id=3). null si no vino; 422 si no es un entero positivo. */
    protected function idFilter(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            Response::error('Parámetros inválidos.', 422, ["El parámetro \"{$key}\" debe ser un entero positivo."]);
        }

        return $id;
    }

    /** Filtro de fecha en la URL (?desde=2026-01-01). null si no vino; 422 si el formato es incorrecto. */
    protected function dateFilter(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        $date = is_string($value) ? \DateTime::createFromFormat('!Y-m-d', $value) : false;

        if ($date === false || $date->format('Y-m-d') !== $value) {
            Response::error('Parámetros inválidos.', 422, ["El parámetro \"{$key}\" debe ser una fecha con formato AAAA-MM-DD."]);
        }

        return $value;
    }

    protected function searchFilter(Request $request): ?string
    {
        $search = $request->query('search');

        return is_string($search) && trim($search) !== '' ? trim($search) : null;
    }
}
