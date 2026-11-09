<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Especie;
use App\Models\Mascota;
use App\Models\Propietario;
use App\Validators\MascotaValidator;

class MascotaController extends Controller
{
    private Mascota $mascotas;
    private Especie $especies;
    private Propietario $propietarios;

    public function __construct()
    {
        $db = Database::connection();
        $this->mascotas = new Mascota($db);
        $this->especies = new Especie($db);
        $this->propietarios = new Propietario($db);
    }

    /** GET /mascotas?page=1&per_page=20&especie_id=5&propietario_id=6&search=texto */
    public function index(Request $request, array $params): void
    {
        [$page, $perPage] = $this->pagination($request);

        $result = $this->mascotas->paginate(
            $page,
            $perPage,
            $this->idFilter($request, 'especie_id'),
            $this->idFilter($request, 'propietario_id'),
            $this->searchFilter($request)
        );

        $this->paginated('Listado de mascotas.', $result, $page, $perPage);
    }

    public function show(Request $request, array $params): void
    {
        Response::success('Mascota obtenida.', $this->findOrFail((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $id = $this->mascotas->create($this->validated($request));

        Response::success('Mascota creada correctamente.', $this->mascotas->find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->mascotas->update($id, $this->validated($request));

        Response::success('Mascota actualizada correctamente.', $this->mascotas->find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->mascotas->delete($id);

        Response::success('Mascota eliminada correctamente.');
    }

    /** Valida el cuerpo, comprueba que la especie y el propietario existan y devuelve los datos limpios. */
    private function validated(Request $request): array
    {
        $input = $request->body();

        $errors = MascotaValidator::validate($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $data = MascotaValidator::clean($input);
        $errors = [];

        if (!$this->especies->exists($data['especie_id'])) {
            $errors[] = 'La "especie_id" indicada no existe.';
        }

        if (!$this->propietarios->exists($data['propietario_id'])) {
            $errors[] = 'El "propietario_id" indicado no existe.';
        }

        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        return $data;
    }

    private function findOrFail(int $id): array
    {
        $mascota = $this->mascotas->find($id);

        if ($mascota === null) {
            Response::error('Mascota no encontrada.', 404);
        }

        return $mascota;
    }
}
