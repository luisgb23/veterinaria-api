<?php

namespace App\Models;

/**
 * Acceso a datos de la tabla "consultas" (historia clínica), con la mascota y su propietario.
 * Cada consulta tiene hasta 3 archivos adjuntos (columnas ConsultaArchivo1..3).
 */
class Consulta extends Model
{
    public const SLOTS = [1, 2, 3];

    private const SELECT = 'SELECT c.ConsultaId, c.ConsultaFecha, c.ConsultaMotivo, c.ConsultaDiagnostico, c.ConsultaTratamiento,
                                   c.ConsultaArchivo1, c.ConsultaArchivo2, c.ConsultaArchivo3,
                                   c.MascotaId, m.MascotaNombre, m.MascotaFchNac, e.EspecieNombre,
                                   m.PropietarioId, p.PropietarioNombre, p.PropietarioApellido, p.PropietarioTelefono,
                                   c.ConsultaFchCreacion, c.ConsultaFchModificacion';

    private const FROM = 'FROM consultas c
                          LEFT JOIN mascotas m ON m.MascotaId = c.MascotaId
                          LEFT JOIN especies e ON e.EspecieId = m.EspecieId
                          LEFT JOIN propietarios p ON p.PropietarioId = m.PropietarioId';

    /**
     * Listado paginado, de la más reciente a la más antigua.
     * "search" busca en motivo, diagnóstico y nombre de la mascota.
     *
     * @return array{items: array, total: int}
     */
    public function paginate(int $page, int $perPage, ?int $mascotaId = null, ?string $desde = null, ?string $hasta = null, ?string $search = null): array
    {
        $where = ['c.ConsultaEstado = 1'];
        $params = [];

        if ($mascotaId !== null) {
            $where[] = 'c.MascotaId = :mascota_id';
            $params[':mascota_id'] = $mascotaId;
        }

        if ($desde !== null) {
            $where[] = 'c.ConsultaFecha >= :desde';
            $params[':desde'] = $desde;
        }

        if ($hasta !== null) {
            $where[] = 'c.ConsultaFecha <= :hasta';
            $params[':hasta'] = $hasta;
        }

        self::addSearch($search, ['c.ConsultaMotivo', 'c.ConsultaDiagnostico', 'm.MascotaNombre'], $where, $params);

        return $this->paginateQuery(self::SELECT, self::FROM, $where, $params, 'c.ConsultaFecha DESC, c.ConsultaId DESC', $page, $perPage);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' ' . self::FROM . ' WHERE c.ConsultaId = :id AND c.ConsultaEstado = 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO consultas
                (ConsultaFecha, MascotaId, ConsultaMotivo, ConsultaDiagnostico, ConsultaTratamiento, ConsultaFchCreacion, ConsultaEstado)
             VALUES (:fecha, :mascota_id, :motivo, :diagnostico, :tratamiento, :creacion, 1)'
        );
        $stmt->execute($this->bindings($data) + [':creacion' => self::now()]);

        return (int) $this->db->lastInsertId();
    }

    /** Actualiza los datos clínicos. Los archivos se manejan aparte (setArchivo). */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE consultas
             SET ConsultaFecha = :fecha, MascotaId = :mascota_id, ConsultaMotivo = :motivo,
                 ConsultaDiagnostico = :diagnostico, ConsultaTratamiento = :tratamiento,
                 ConsultaFchModificacion = :modificacion
             WHERE ConsultaId = :id'
        );
        $stmt->execute($this->bindings($data) + [':modificacion' => self::now(), ':id' => $id]);
    }

    /** Baja lógica (los archivos se conservan). */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE consultas SET ConsultaEstado = 0, ConsultaFchModificacion = :modificacion WHERE ConsultaId = :id'
        );
        $stmt->execute([':modificacion' => self::now(), ':id' => $id]);
    }

    /** Nombre del archivo guardado en el lugar 1, 2 o 3, o null si está vacío. */
    public function archivo(int $id, int $slot): ?string
    {
        $columna = self::columnaArchivo($slot);

        $stmt = $this->db->prepare("SELECT {$columna} FROM consultas WHERE ConsultaId = :id AND ConsultaEstado = 1");
        $stmt->execute([':id' => $id]);

        $nombre = $stmt->fetchColumn();

        return is_string($nombre) && $nombre !== '' ? $nombre : null;
    }

    /** Guarda (o borra, con null) el nombre del archivo del lugar indicado. */
    public function setArchivo(int $id, int $slot, ?string $nombre): void
    {
        $columna = self::columnaArchivo($slot);

        $stmt = $this->db->prepare(
            "UPDATE consultas SET {$columna} = :nombre, ConsultaFchModificacion = :modificacion WHERE ConsultaId = :id"
        );
        $stmt->execute([':nombre' => $nombre, ':modificacion' => self::now(), ':id' => $id]);
    }

    private static function columnaArchivo(int $slot): string
    {
        if (!in_array($slot, self::SLOTS, true)) {
            throw new \InvalidArgumentException("Lugar de archivo inválido: {$slot}");
        }

        return 'ConsultaArchivo' . $slot;
    }

    private function bindings(array $data): array
    {
        return [
            ':fecha'       => $data['fecha'],
            ':mascota_id'  => $data['mascota_id'],
            ':motivo'      => $data['motivo'],
            ':diagnostico' => $data['diagnostico'],
            ':tratamiento' => $data['tratamiento'],
        ];
    }

    protected function format(array $row): array
    {
        $id = (int) $row['ConsultaId'];
        $archivos = [];

        foreach (self::SLOTS as $slot) {
            $nombre = $row['ConsultaArchivo' . $slot];

            if ($nombre !== null && $nombre !== '') {
                $archivos[] = [
                    'numero' => $slot,
                    'nombre' => $nombre,
                    'url'    => "/consultas/{$id}/archivos/{$slot}",
                ];
            }
        }

        return [
            'id'                   => $id,
            'fecha'                => $row['ConsultaFecha'],
            'mascota_id'           => self::intOrNull($row['MascotaId']),
            'mascota_nombre'       => $row['MascotaNombre'],
            'mascota_edad'         => Mascota::edad($row['MascotaFchNac'])[1],
            'especie_nombre'       => $row['EspecieNombre'],
            'propietario_id'       => self::intOrNull($row['PropietarioId']),
            'propietario_nombre'   => $row['PropietarioNombre'],
            'propietario_apellido' => $row['PropietarioApellido'],
            'propietario_telefono' => $row['PropietarioTelefono'],
            'motivo'               => $row['ConsultaMotivo'],
            'diagnostico'          => $row['ConsultaDiagnostico'],
            'tratamiento'          => $row['ConsultaTratamiento'],
            'archivos'             => $archivos,
            'pdf_url'              => "/consultas/{$id}/pdf",
            'creado_en'            => $row['ConsultaFchCreacion'],
            'modificado_en'        => $row['ConsultaFchModificacion'],
        ];
    }
}
