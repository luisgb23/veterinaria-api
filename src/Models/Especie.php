<?php

namespace App\Models;

/**
 * Acceso a datos de la tabla "especies".
 */
class Especie extends Model
{
    private const SELECT = 'SELECT EspecieId, EspecieNombre, EspecieFchCreacion, EspecieFchModificacion FROM especies';

    /** Listado completo (son pocas, se usa para los selects del frontend). */
    public function all(): array
    {
        $stmt = $this->db->query(self::SELECT . ' WHERE EspecieEstado = 1 ORDER BY EspecieNombre');

        return array_map([$this, 'format'], $stmt->fetchAll());
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE EspecieId = :id AND EspecieEstado = 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM especies WHERE EspecieId = :id AND EspecieEstado = 1');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    /** true si ya hay otra especie activa con ese nombre (sin distinguir mayúsculas). */
    public function nameTaken(string $nombre, ?int $exceptId = null): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM especies WHERE LOWER(EspecieNombre) = LOWER(:nombre) AND EspecieEstado = 1 AND EspecieId <> :id'
        );
        $stmt->execute([':nombre' => $nombre, ':id' => $exceptId ?? 0]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO especies (EspecieNombre, EspecieFchCreacion, EspecieEstado) VALUES (:nombre, :creacion, 1)'
        );
        $stmt->execute([':nombre' => $data['nombre'], ':creacion' => self::now()]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE especies SET EspecieNombre = :nombre, EspecieFchModificacion = :modificacion WHERE EspecieId = :id'
        );
        $stmt->execute([':nombre' => $data['nombre'], ':modificacion' => self::now(), ':id' => $id]);
    }

    /** Baja lógica. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE especies SET EspecieEstado = 0, EspecieFchModificacion = :modificacion WHERE EspecieId = :id'
        );
        $stmt->execute([':modificacion' => self::now(), ':id' => $id]);
    }

    protected function format(array $row): array
    {
        return [
            'id'            => (int) $row['EspecieId'],
            'nombre'        => $row['EspecieNombre'],
            'creado_en'     => $row['EspecieFchCreacion'],
            'modificado_en' => $row['EspecieFchModificacion'],
        ];
    }
}
