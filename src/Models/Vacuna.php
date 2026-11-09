<?php

namespace App\Models;

/**
 * Acceso a datos de la tabla "vacunas" (fecha de aplicación y de vencimiento por mascota).
 */
class Vacuna extends Model
{
    private const SELECT = 'SELECT v.VacunaId, v.VacunaFchIngreso, v.VacunaFchVenc, v.VacunaFchModificacion,
                                   v.MascotaId, m.MascotaNombre,
                                   m.PropietarioId, p.PropietarioNombre, p.PropietarioApellido, p.PropietarioTelefono';

    private const FROM = 'FROM vacunas v
                          LEFT JOIN mascotas m ON m.MascotaId = v.MascotaId
                          LEFT JOIN propietarios p ON p.PropietarioId = m.PropietarioId';

    /**
     * Listado paginado, de la aplicación más reciente a la más antigua.
     *
     * @return array{items: array, total: int}
     */
    public function paginate(int $page, int $perPage, ?int $mascotaId = null, ?string $search = null): array
    {
        $where = ['v.VacunaEstado = 1'];
        $params = [];

        if ($mascotaId !== null) {
            $where[] = 'v.MascotaId = :mascota_id';
            $params[':mascota_id'] = $mascotaId;
        }

        self::addSearch($search, ['m.MascotaNombre', 'p.PropietarioNombre', 'p.PropietarioApellido'], $where, $params);

        return $this->paginateQuery(self::SELECT, self::FROM, $where, $params, 'v.VacunaFchIngreso DESC, v.VacunaId DESC', $page, $perPage);
    }

    /**
     * Vencimientos de vacunas de mascotas activas, del más próximo (o más atrasado) al más lejano.
     *
     * @param string $filtro "todas", "vencidas" (antes de hoy) o "proximas" (de hoy a $dias días)
     * @param bool $historico false = solo la última vacuna de cada mascota; true = todas las registradas
     * @return array{items: array, total: int}
     */
    public function vencimientos(int $page, int $perPage, string $filtro, int $dias, bool $historico): array
    {
        $hoy = date('Y-m-d');
        $where = ['v.VacunaEstado = 1', 'm.MascotaEstado = 1', 'v.VacunaFchVenc IS NOT NULL'];
        $params = [];

        if (!$historico) {
            // Si una mascota ya se revacunó, la vacuna anterior vencida no cuenta.
            $where[] = 'v.VacunaFchVenc = (SELECT MAX(v2.VacunaFchVenc) FROM vacunas v2
                                            WHERE v2.MascotaId = v.MascotaId AND v2.VacunaEstado = 1)';
        }

        if ($filtro === 'vencidas') {
            $where[] = 'v.VacunaFchVenc < :hoy';
            $params[':hoy'] = $hoy;
        } elseif ($filtro === 'proximas') {
            $where[] = 'v.VacunaFchVenc >= :hoy AND v.VacunaFchVenc <= :limite';
            $params[':hoy'] = $hoy;
            $params[':limite'] = date('Y-m-d', strtotime("+{$dias} days"));
        }

        return $this->paginateQuery(self::SELECT, self::FROM, $where, $params, 'v.VacunaFchVenc ASC, v.VacunaId ASC', $page, $perPage);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' ' . self::FROM . ' WHERE v.VacunaId = :id AND v.VacunaEstado = 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function create(array $data): int
    {
        // La tabla no tiene columna de fecha de creación (igual que en el sistema anterior).
        $stmt = $this->db->prepare(
            'INSERT INTO vacunas (VacunaFchIngreso, MascotaId, VacunaFchVenc, VacunaEstado)
             VALUES (:fecha_ingreso, :mascota_id, :fecha_vencimiento, 1)'
        );
        $stmt->execute($this->bindings($data));

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE vacunas
             SET VacunaFchIngreso = :fecha_ingreso, MascotaId = :mascota_id, VacunaFchVenc = :fecha_vencimiento,
                 VacunaFchModificacion = :modificacion
             WHERE VacunaId = :id'
        );
        $stmt->execute($this->bindings($data) + [':modificacion' => self::now(), ':id' => $id]);
    }

    /** Baja lógica. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE vacunas SET VacunaEstado = 0, VacunaFchModificacion = :modificacion WHERE VacunaId = :id'
        );
        $stmt->execute([':modificacion' => self::now(), ':id' => $id]);
    }

    private function bindings(array $data): array
    {
        return [
            ':fecha_ingreso'     => $data['fecha_ingreso'],
            ':mascota_id'        => $data['mascota_id'],
            ':fecha_vencimiento' => $data['fecha_vencimiento'],
        ];
    }

    protected function format(array $row): array
    {
        $vencimiento = $row['VacunaFchVenc'];
        $dias = null;

        if ($vencimiento !== null && $vencimiento !== '') {
            $dias = (int) (new \DateTime('today'))->diff(new \DateTime($vencimiento))->format('%r%a');
        }

        return [
            'id'                   => (int) $row['VacunaId'],
            'fecha_ingreso'        => $row['VacunaFchIngreso'],
            'fecha_vencimiento'    => $vencimiento,
            'vencida'              => $dias !== null && $dias < 0,
            'dias_para_vencer'     => $dias,
            'mascota_id'           => self::intOrNull($row['MascotaId']),
            'mascota_nombre'       => $row['MascotaNombre'],
            'propietario_id'       => self::intOrNull($row['PropietarioId']),
            'propietario_nombre'   => $row['PropietarioNombre'],
            'propietario_apellido' => $row['PropietarioApellido'],
            'propietario_telefono' => $row['PropietarioTelefono'],
            'modificado_en'        => $row['VacunaFchModificacion'],
        ];
    }
}
