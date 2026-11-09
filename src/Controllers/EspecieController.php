<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Especie;
use App\Models\Mascota;
use App\Validators\EspecieValidator;

class EspecieController extends Controller
{
    private Especie $especies;
    private Mascota $mascotas;

    public function __construct()
    {
        $db = Database::connection();
        $this->especies = new Especie($db);
        $this->mascotas = new Mascota($db);
    }

    /** GET /especies (sin paginar: son pocas y se usan para los selects) */
    public function index(Request $request, array $params): void
    {
        Response::success('Listado de especies.', $this->especies->all());
    }

    public function show(Request $request, array $params): void
    {
        Response::success('Especie obtenida.', $this->findOrFail((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $data = $this->validated($request);

        $id = $this->especies->create($data);

        Response::success('Especie creada correctamente.', $this->especies->find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $data = $this->validated($request, $id);

        $this->especies->update($id, $data);

        Response::success('Especie actualizada correctamente.', $this->especies->find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $enUso = $this->mascotas->countActiveBy('especie', $id);
        if ($enUso > 0) {
            Response::error("No se puede eliminar: hay {$enUso} mascota(s) activa(s) de esta especie.", 409);
        }

        $this->especies->delete($id);

        Response::success('Especie eliminada correctamente.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $input = $request->body();

        $errors = EspecieValidator::validate($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $data = EspecieValidator::clean($input);

        if ($this->especies->nameTaken($data['nombre'], $id)) {
            Response::error('Ya existe una especie con ese nombre.', 409);
        }

        return $data;
    }

    private function findOrFail(int $id): array
    {
        $especie = $this->especies->find($id);

        if ($especie === null) {
            Response::error('Especie no encontrada.', 404);
        }

        return $especie;
    }
}
