<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Mascota;
use App\Models\Propietario;
use App\Validators\PropietarioValidator;

class PropietarioController extends Controller
{
    private Propietario $propietarios;
    private Mascota $mascotas;

    public function __construct()
    {
        $db = Database::connection();
        $this->propietarios = new Propietario($db);
        $this->mascotas = new Mascota($db);
    }

    /** GET /propietarios?page=1&per_page=20&search=texto */
    public function index(Request $request, array $params): void
    {
        [$page, $perPage] = $this->pagination($request);

        $result = $this->propietarios->paginate($page, $perPage, $this->searchFilter($request));

        $this->paginated('Listado de propietarios.', $result, $page, $perPage);
    }

    public function show(Request $request, array $params): void
    {
        Response::success('Propietario obtenido.', $this->findOrFail((int) $params['id']));
    }

    /** GET /propietarios/{id}/mascotas */
    public function mascotas(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $result = $this->mascotas->paginate(1, 100, null, $id);

        Response::success('Mascotas del propietario.', $result['items']);
    }

    public function store(Request $request, array $params): void
    {
        $data = $this->validated($request);

        $id = $this->propietarios->create($data);

        Response::success('Propietario creado correctamente.', $this->propietarios->find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->propietarios->update($id, $this->validated($request));

        Response::success('Propietario actualizado correctamente.', $this->propietarios->find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $mascotas = $this->mascotas->countActiveBy('propietario', $id);
        if ($mascotas > 0) {
            Response::error("No se puede eliminar: el propietario tiene {$mascotas} mascota(s) activa(s). Eliminalas o asignalas a otro propietario primero.", 409);
        }

        $this->propietarios->delete($id);

        Response::success('Propietario eliminado correctamente.');
    }

    private function validated(Request $request): array
    {
        $input = $request->body();

        $errors = PropietarioValidator::validate($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        return PropietarioValidator::clean($input);
    }

    private function findOrFail(int $id): array
    {
        $propietario = $this->propietarios->find($id);

        if ($propietario === null) {
            Response::error('Propietario no encontrado.', 404);
        }

        return $propietario;
    }
}
