<?php

namespace App\Models;

use DateTime;

/**
 * Acceso a datos de la tabla "mascotas" (con su especie y su propietario).
 */
class Mascota extends Model
{
    private const SELECT = 'SELECT m.MascotaId, m.MascotaNombre, m.MascotaFchNac,
                                   m.EspecieId, e.EspecieNombre,
                                   m.PropietarioId, p.PropietarioNombre, p.PropietarioApellido, p.PropietarioTelefono,
                                   m.MascotaFchCreacion, m.MascotaFchModificacion';

    private const FROM = 'FROM mascotas m
                          LEFT JOIN especies e ON e.EspecieId = m.EspecieId
                          LEFT JOIN propietarios p ON p.PropietarioId = m.PropietarioId';

    /**
     * Listado paginado con filtros opcionales.
     * "search" busca en el nombre de la mascota y en el nombre o apellido del propietario.
     *
     * @return array{items: array, total: int}
     */
    public function paginate(int $page, int $perPage, ?int $especieId = null, ?int $propietarioId = null, ?string $search = null): array
    {
        $where = ['m.MascotaEstado = 1'];
        $params = [];

        if ($especieId !== null) {
            $where[] = 'm.EspecieId = :especie_id';
            $params[':especie_id'] = $especieId;
        }

        if ($propietarioId !== null) {
            $where[] = 'm.PropietarioId = :propietario_id';
            $params[':propietario_id'] = $propietarioId;
        }

        self::addSearch($search, ['m.MascotaNombre', 'p.PropietarioNombre', 'p.PropietarioApellido'], $where, $params);

        return $this->paginateQuery(self::SELECT, self::FROM, $where, $params, 'm.MascotaNombre, m.MascotaId', $page, $perPage);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' ' . self::FROM . ' WHERE m.MascotaId = :id AND m.MascotaEstado = 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM mascotas WHERE MascotaId = :id AND MascotaEstado = 1');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    /** Cantidad de mascotas activas de un propietario o de una especie. */
    public function countActiveBy(string $campo, int $id): int
    {
        $columna = ['propietario' => 'PropietarioId', 'especie' => 'EspecieId'][$campo];

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM mascotas WHERE {$columna} = :id AND MascotaEstado = 1");
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO mascotas (MascotaNombre, EspecieId, PropietarioId, MascotaFchNac, MascotaFchCreacion, MascotaEstado)
             VALUES (:nombre, :especie_id, :propietario_id, :fecha_nacimiento, :creacion, 1)'
        );
        $stmt->execute($this->bindings($data) + [':creacion' => self::now()]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE mascotas
             SET MascotaNombre = :nombre, EspecieId = :especie_id, PropietarioId = :propietario_id,
                 MascotaFchNac = :fecha_nacimiento, MascotaFchModificacion = :modificacion
             WHERE MascotaId = :id'
        );
        $stmt->execute($this->bindings($data) + [':modificacion' => self::now(), ':id' => $id]);
    }

    /** Baja lógica. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE mascotas SET MascotaEstado = 0, MascotaFchModificacion = :modificacion WHERE MascotaId = :id'
        );
        $stmt->execute([':modificacion' => self::now(), ':id' => $id]);
    }

    private function bindings(array $data): array
    {
        return [
            ':nombre'           => $data['nombre'],
            ':especie_id'       => $data['especie_id'],
            ':propietario_id'   => $data['propietario_id'],
            ':fecha_nacimiento' => $data['fecha_nacimiento'],
        ];
    }

    protected function format(array $row): array
    {
        [$edad, $edadTexto] = self::edad($row['MascotaFchNac']);

        return [
            'id'                   => (int) $row['MascotaId'],
            'nombre'               => $row['MascotaNombre'],
            'fecha_nacimiento'     => $row['MascotaFchNac'],
            'edad'                 => $edad,
            'edad_texto'           => $edadTexto,
            'especie_id'           => self::intOrNull($row['EspecieId']),
            'especie_nombre'       => $row['EspecieNombre'],
            'propietario_id'       => self::intOrNull($row['PropietarioId']),
            'propietario_nombre'   => $row['PropietarioNombre'],
            'propietario_apellido' => $row['PropietarioApellido'],
            'propietario_telefono' => $row['PropietarioTelefono'],
            'creado_en'            => $row['MascotaFchCreacion'],
            'modificado_en'        => $row['MascotaFchModificacion'],
        ];
    }

    /**
     * Edad en años cumplidos (como obtenerEdad() del sistema anterior) y un texto legible.
     * A los menores de un año se les muestra la edad en meses.
     *
     * @return array{0: ?int, 1: ?string}
     */
    public static function edad(?string $fechaNacimiento): array
    {
        if ($fechaNacimiento === null || $fechaNacimiento === '' || $fechaNacimiento === '0000-00-00') {
            return [null, null];
        }

        $diff = (new DateTime($fechaNacimiento))->diff(new DateTime('today'));

        if ($diff->invert === 1) {
            return [0, '0 meses'];
        }

        if ($diff->y === 0) {
            return [0, $diff->m === 1 ? '1 mes' : "{$diff->m} meses"];
        }

        return [$diff->y, $diff->y === 1 ? '1 año' : "{$diff->y} años"];
    }
}
