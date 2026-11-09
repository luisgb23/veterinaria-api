<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Cuota;
use App\Models\Mascota;
use App\Validators\CuotaValidator;

class CuotaController extends Controller
{
    private Cuota $cuotas;
    private Mascota $mascotas;

    public function __construct()
    {
        $db = Database::connection();
        $this->cuotas = new Cuota($db);
        $this->mascotas = new Mascota($db);
    }

    /** GET /cuotas?page=1&per_page=20&mascota_id=25&desde=2026-01-01&hasta=2026-12-31&search=texto */
    public function index(Request $request, array $params): void
    {
        [$page, $perPage] = $this->pagination($request);

        $result = $this->cuotas->paginate(
            $page,
            $perPage,
            $this->idFilter($request, 'mascota_id'),
            $this->dateFilter($request, 'desde'),
            $this->dateFilter($request, 'hasta'),
            $this->searchFilter($request)
        );

        $this->paginated('Listado de cuotas.', $result, $page, $perPage, ['suma_valor' => $result['suma']]);
    }

    public function show(Request $request, array $params): void
    {
        Response::success('Cuota obtenida.', $this->findOrFail((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $id = $this->cuotas->create($this->validated($request));

        Response::success('Cuota registrada correctamente.', $this->cuotas->find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->cuotas->update($id, $this->validated($request));

        Response::success('Cuota actualizada correctamente.', $this->cuotas->find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->cuotas->delete($id);

        Response::success('Cuota eliminada correctamente.');
    }

    private function validated(Request $request): array
    {
        $input = $request->body();

        $errors = CuotaValidator::validate($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $data = CuotaValidator::clean($input);

        if (!$this->mascotas->exists($data['mascota_id'])) {
            Response::error('Datos inválidos.', 422, ['La "mascota_id" indicada no existe.']);
        }

        return $data;
    }

    private function findOrFail(int $id): array
    {
        $cuota = $this->cuotas->find($id);

        if ($cuota === null) {
            Response::error('Cuota no encontrada.', 404);
        }

        return $cuota;
    }
}
