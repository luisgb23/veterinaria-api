<?php

namespace App\Models;

/**
 * Acceso a datos de la tabla "cuotas" (pagos asociados a una mascota).
 */
class Cuota extends Model
{
    private const SELECT = 'SELECT c.CuotaId, c.CuotaFecha, c.CuotaValor, c.CuotaObservacion,
                                   c.MascotaId, m.MascotaNombre,
                                   m.PropietarioId, p.PropietarioNombre, p.PropietarioApellido,
                                   c.CuotaFchCreacion, c.CuotaFchModificacion';

    private const FROM = 'FROM cuotas c
                          LEFT JOIN mascotas m ON m.MascotaId = c.MascotaId
                          LEFT JOIN propietarios p ON p.PropietarioId = m.PropietarioId';

    /**
     * Listado paginado, de la más reciente a la más antigua, más la suma de "valor"
     * de todas las cuotas que cumplen los filtros (no solo las de la página).
     *
     * @return array{items: array, total: int, suma: int}
     */
    public function paginate(int $page, int $perPage, ?int $mascotaId = null, ?string $desde = null, ?string $hasta = null, ?string $search = null): array
    {
        $where = ['c.CuotaEstado = 1'];
        $params = [];

        if ($mascotaId !== null) {
            $where[] = 'c.MascotaId = :mascota_id';
            $params[':mascota_id'] = $mascotaId;
        }

        if ($desde !== null) {
            $where[] = 'c.CuotaFecha >= :desde';
            $params[':desde'] = $desde;
        }

        if ($hasta !== null) {
            $where[] = 'c.CuotaFecha <= :hasta';
            $params[':hasta'] = $hasta;
        }

        self::addSearch($search, ['c.CuotaObservacion', 'm.MascotaNombre', 'p.PropietarioApellido'], $where, $params);

        $result = $this->paginateQuery(self::SELECT, self::FROM, $where, $params, 'c.CuotaFecha DESC, c.CuotaId DESC', $page, $perPage);

        $sum = $this->db->prepare('SELECT COALESCE(SUM(c.CuotaValor), 0) ' . self::FROM . ' WHERE ' . implode(' AND ', $where));
        $sum->execute($params);
        $result['suma'] = (int) $sum->fetchColumn();

        return $result;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' ' . self::FROM . ' WHERE c.CuotaId = :id AND c.CuotaEstado = 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cuotas (CuotaFecha, MascotaId, CuotaValor, CuotaObservacion, CuotaFchCreacion, CuotaEstado)
             VALUES (:fecha, :mascota_id, :valor, :observacion, :creacion, 1)'
        );
        $stmt->execute($this->bindings($data) + [':creacion' => self::now()]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cuotas
             SET CuotaFecha = :fecha, MascotaId = :mascota_id, CuotaValor = :valor,
                 CuotaObservacion = :observacion, CuotaFchModificacion = :modificacion
             WHERE CuotaId = :id'
        );
        $stmt->execute($this->bindings($data) + [':modificacion' => self::now(), ':id' => $id]);
    }

    /** Baja lógica. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cuotas SET CuotaEstado = 0, CuotaFchModificacion = :modificacion WHERE CuotaId = :id'
        );
        $stmt->execute([':modificacion' => self::now(), ':id' => $id]);
    }

    private function bindings(array $data): array
    {
        return [
            ':fecha'       => $data['fecha'],
            ':mascota_id'  => $data['mascota_id'],
            ':valor'       => $data['valor'],
            ':observacion' => $data['observacion'],
        ];
    }

    protected function format(array $row): array
    {
        return [
            'id'                   => (int) $row['CuotaId'],
            'fecha'                => $row['CuotaFecha'],
            'valor'                => self::intOrNull($row['CuotaValor']),
            'observacion'          => $row['CuotaObservacion'],
            'mascota_id'           => self::intOrNull($row['MascotaId']),
            'mascota_nombre'       => $row['MascotaNombre'],
            'propietario_id'       => self::intOrNull($row['PropietarioId']),
            'propietario_nombre'   => $row['PropietarioNombre'],
            'propietario_apellido' => $row['PropietarioApellido'],
            'creado_en'            => $row['CuotaFchCreacion'],
            'modificado_en'        => $row['CuotaFchModificacion'],
        ];
    }
}
