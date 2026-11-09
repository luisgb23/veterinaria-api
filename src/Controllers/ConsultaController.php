<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Storage;
use App\Models\Consulta;
use App\Models\Mascota;
use App\Reports\ConsultaPdf;
use App\Validators\ConsultaValidator;

class ConsultaController extends Controller
{
    private Consulta $consultas;
    private Mascota $mascotas;

    public function __construct()
    {
        $db = Database::connection();
        $this->consultas = new Consulta($db);
        $this->mascotas = new Mascota($db);
    }

    /** GET /consultas?page=1&per_page=20&mascota_id=25&desde=2026-01-01&hasta=2026-12-31&search=texto */
    public function index(Request $request, array $params): void
    {
        [$page, $perPage] = $this->pagination($request);

        $result = $this->consultas->paginate(
            $page,
            $perPage,
            $this->idFilter($request, 'mascota_id'),
            $this->dateFilter($request, 'desde'),
            $this->dateFilter($request, 'hasta'),
            $this->searchFilter($request)
        );

        $this->paginated('Listado de consultas.', $result, $page, $perPage);
    }

    public function show(Request $request, array $params): void
    {
        Response::success('Consulta obtenida.', $this->findOrFail((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $id = $this->consultas->create($this->validated($request));

        Response::success('Consulta creada correctamente.', $this->consultas->find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->consultas->update($id, $this->validated($request));

        Response::success('Consulta actualizada correctamente.', $this->consultas->find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $this->findOrFail($id);

        $this->consultas->delete($id);

        Response::success('Consulta eliminada correctamente.');
    }

    /**
     * POST /consultas/{id}/archivos/{numero}  (multipart/form-data, campo "archivo")
     * Sube o reemplaza el archivo 1, 2 o 3 de la consulta.
     */
    public function subirArchivo(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $numero = $this->numeroArchivo($params);
        $this->findOrFail($id);

        // Si el archivo supera post_max_size, PHP descarta todo el cuerpo y $_FILES llega vacío.
        if (empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Response::error('El archivo supera el tamaño máximo que acepta el servidor (post_max_size).', 413);
        }

        $file = $request->file('archivo');
        if ($file === null) {
            Response::error('Datos inválidos.', 422, ['Enviá el archivo en el campo "archivo" con multipart/form-data.']);
        }

        $error = Storage::validar($file);
        if ($error !== null) {
            Response::error('Archivo inválido.', 422, [$error]);
        }

        $anterior = $this->consultas->archivo($id, $numero);
        $nombre = Storage::guardar($file, "c{$id}_{$numero}");
        $this->consultas->setArchivo($id, $numero, $nombre);

        // Solo se borran del disco los archivos que subió esta API para esta consulta;
        // los del sistema anterior podrían estar compartidos por varias consultas.
        if ($anterior !== null && str_starts_with($anterior, "c{$id}_")) {
            Storage::borrar($anterior);
        }

        Response::success('Archivo guardado correctamente.', $this->consultas->find($id), 201);
    }

    /** GET /consultas/{id}/archivos/{numero}  (agregá ?descargar=1 para forzar la descarga) */
    public function descargarArchivo(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $numero = $this->numeroArchivo($params);
        $this->findOrFail($id);

        $nombre = $this->consultas->archivo($id, $numero);
        $ruta = $nombre !== null ? Storage::ruta($nombre) : null;

        if ($ruta === null) {
            Response::error('Archivo no encontrado.', 404);
        }

        Storage::enviar($ruta, $nombre, $request->query('descargar') === '1');
    }

    /** DELETE /consultas/{id}/archivos/{numero} */
    public function eliminarArchivo(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $numero = $this->numeroArchivo($params);
        $this->findOrFail($id);

        $nombre = $this->consultas->archivo($id, $numero);
        if ($nombre === null) {
            Response::error('Archivo no encontrado.', 404);
        }

        $this->consultas->setArchivo($id, $numero, null);

        if (str_starts_with($nombre, "c{$id}_")) {
            Storage::borrar($nombre);
        }

        Response::success('Archivo eliminado correctamente.', $this->consultas->find($id));
    }

    /** GET /consultas/{id}/pdf — resumen de la historia clínica (reemplaza a reportePDF.php) */
    public function pdf(Request $request, array $params): void
    {
        $consulta = $this->findOrFail((int) $params['id']);

        ConsultaPdf::enviar($consulta, $request->query('descargar') === '1');
    }

    private function numeroArchivo(array $params): int
    {
        $numero = (int) $params['numero'];

        if (!in_array($numero, Consulta::SLOTS, true)) {
            Response::error('Cada consulta tiene hasta 3 archivos: el número debe ser 1, 2 o 3.', 404);
        }

        return $numero;
    }

    /** Valida el cuerpo, comprueba que la mascota exista y devuelve los datos limpios. */
    private function validated(Request $request): array
    {
        $input = $request->body();

        $errors = ConsultaValidator::validate($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $data = ConsultaValidator::clean($input);

        if (!$this->mascotas->exists($data['mascota_id'])) {
            Response::error('Datos inválidos.', 422, ['La "mascota_id" indicada no existe.']);
        }

        return $data;
    }

    private function findOrFail(int $id): array
    {
        $consulta = $this->consultas->find($id);

        if ($consulta === null) {
            Response::error('Consulta no encontrada.', 404);
        }

        return $consulta;
    }
}
