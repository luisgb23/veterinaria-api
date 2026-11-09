<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Mascota;
use App\Models\Vacuna;
use App\Validators\VacunaValidator;

class VacunaController extends Controller
{
    private Vacuna $vacunas;
    private Mascota $mascotas;

    public function __construct()
    {
        $db = Database::connection();
        $this->vacunas = new Vacuna($db);
        $this->mascotas = new Mascota($db);
    }

    /** GET /vacunas?page=1&per_page=20&mascota_id=25&search=texto */
    public function index(Request $request, array $params): void
    {
        [$page, $perPage] = $this->pagination($request);

        $result = $this->vacunas->paginate(
            $page,
            $perPage,
            $this->idFilter($request, 'mascota_id'),
            $this->searchFilter($request)
        );

        $this->paginated('Listado de vacunas.', $result, $page, $perPage);
    }

    /**
     * GET /vacunas/vencimientos?filtro=todas|vencidas|proximas&dias=30&historico=0
     * (reemplaza a vencimientos.php)
     */
    public function vencimientos(Request $request, array $params): void
    {
        [$page, $perPage] = $this->pagination($request);

        $filtro = $request->query('filtro', 'todas');
        if (!in_array($filtro, ['todas', 'vencidas', 'proximas'], true)) {
            Response::error('Parámetros inválidos.', 422, ['El parámetro "filtro" debe ser "todas", "vencidas" o "proximas".']);
        }

        $dias = filter_var($request->query('dias', 30), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3650]]);
        if ($dias === false) {
            Response::error('Parámetros inválidos.', 422, ['El parámetro "dias" debe ser un entero entre 0 y 3650.']);
        }

        $historico = $request->query('historico') === '1';

        $result = $this->vacunas->vencimientos($page, $perPage, $filtro, $dias, $historico);

        $this->paginated('Vencimientos de vacunas.', $result, $page, $perPage, [
            'filtros' => ['filtro' => $filtro, 'dias' => $dias, 'historico' => $historico],
        ]);
    }

    public function show(Request $request, array $params): void
    {
        Response::success('Vacuna obtenida.', $this->findOrFail((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $id = $this->vacunas->create($this->validated($request));

        Response::success('Vacuna registrada correctamente.', $this->vacunas->find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->vacunas->update($id, $this->validated($request));

        Response::success('Vacuna actualizada correctamente.', $this->vacunas->find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->vacunas->delete($id);

        Response::success('Vacuna eliminada correctamente.');
    }

    private function validated(Request $request): array
    {
        $input = $request->body();

        $errors = VacunaValidator::validate($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $data = VacunaValidator::clean($input);

        if (!$this->mascotas->exists($data['mascota_id'])) {
            Response::error('Datos inválidos.', 422, ['La "mascota_id" indicada no existe.']);
        }

        return $data;
    }

    private function findOrFail(int $id): array
    {
        $vacuna = $this->vacunas->find($id);

        if ($vacuna === null) {
            Response::error('Vacuna no encontrada.', 404);
        }

        return $vacuna;
    }
}
