<?php

namespace App\Models;

/**
 * Acceso a datos de la tabla "propietarios".
 */
class Propietario extends Model
{
    private const SELECT = 'SELECT p.PropietarioId, p.PropietarioNombre, p.PropietarioApellido, p.PropietarioTelefono,
                                   p.PropietarioDireccion, p.PropietarioEmail,
                                   p.PropietarioFchCreacion, p.PropietarioFchModificacion';

    /**
     * Listado paginado. "search" busca en nombre, apellido, teléfono y email.
     *
     * @return array{items: array, total: int}
     */
    public function paginate(int $page, int $perPage, ?string $search = null): array
    {
        $where = ['p.PropietarioEstado = 1'];
        $params = [];

        self::addSearch($search, [
            'p.PropietarioNombre', 'p.PropietarioApellido', 'p.PropietarioTelefono', 'p.PropietarioEmail',
        ], $where, $params);

        return $this->paginateQuery(
            self::SELECT,
            'FROM propietarios p',
            $where,
            $params,
            'p.PropietarioApellido, p.PropietarioNombre, p.PropietarioId',
            $page,
            $perPage
        );
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' FROM propietarios p WHERE p.PropietarioId = :id AND p.PropietarioEstado = 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM propietarios WHERE PropietarioId = :id AND PropietarioEstado = 1');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO propietarios
                (PropietarioNombre, PropietarioApellido, PropietarioTelefono, PropietarioDireccion, PropietarioEmail,
                 PropietarioFchCreacion, PropietarioEstado)
             VALUES (:nombre, :apellido, :telefono, :direccion, :email, :creacion, 1)'
        );
        $stmt->execute($this->bindings($data) + [':creacion' => self::now()]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE propietarios
             SET PropietarioNombre = :nombre, PropietarioApellido = :apellido, PropietarioTelefono = :telefono,
                 PropietarioDireccion = :direccion, PropietarioEmail = :email, PropietarioFchModificacion = :modificacion
             WHERE PropietarioId = :id'
        );
        $stmt->execute($this->bindings($data) + [':modificacion' => self::now(), ':id' => $id]);
    }

    /** Baja lógica. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE propietarios SET PropietarioEstado = 0, PropietarioFchModificacion = :modificacion WHERE PropietarioId = :id'
        );
        $stmt->execute([':modificacion' => self::now(), ':id' => $id]);
    }

    private function bindings(array $data): array
    {
        return [
            ':nombre'    => $data['nombre'],
            ':apellido'  => $data['apellido'],
            ':telefono'  => $data['telefono'],
            ':direccion' => $data['direccion'],
            ':email'     => $data['email'],
        ];
    }

    protected function format(array $row): array
    {
        return [
            'id'            => (int) $row['PropietarioId'],
            'nombre'        => $row['PropietarioNombre'],
            'apellido'      => $row['PropietarioApellido'],
            'telefono'      => $row['PropietarioTelefono'],
            'direccion'     => $row['PropietarioDireccion'],
            'email'         => $row['PropietarioEmail'],
            'creado_en'     => $row['PropietarioFchCreacion'],
            'modificado_en' => $row['PropietarioFchModificacion'],
        ];
    }
}
