<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ServiceResourceGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

class ModuleResourceApiController extends Controller
{
    public function __construct(private ServiceResourceGateway $gateway)
    {
    }

    private function moduleConfig(string $module): array
    {
        $config = config("laravel_resource_modules.{$module}");
        if (!is_array($config)) {
            throw new InvalidArgumentException('Module not found.');
        }

        return $config;
    }

    private function serviceAndResource(string $module): array
    {
        $moduleConfig = $this->moduleConfig($module);
        $service = (string) ($moduleConfig['service'] ?? '');
        $resource = (string) ($moduleConfig['resource'] ?? '');

        if ($service === '' || $resource === '') {
            throw new InvalidArgumentException('Invalid module mapping.');
        }

        return [$moduleConfig, $service, $resource];
    }

    public function meta(string $module): JsonResponse
    {
        try {
            [$moduleConfig, $service, $resource] = $this->serviceAndResource($module);
            $resourceConfig = $this->gateway->describeResource($service, $resource);
            $resourceConfig['read_only'] = (bool) (($moduleConfig['read_only'] ?? false) || ($resourceConfig['read_only'] ?? false));

            return response()->json([
                'ok' => true,
                'module' => $module,
                'data' => $resourceConfig,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function index(Request $request, string $module): JsonResponse
    {
        try {
            [, $service, $resource] = $this->serviceAndResource($module);
            $page = max(1, (int) $request->query('page', 1));
            $limit = max(1, min((int) $request->query('limit', 12), 100));
            $keyword = trim((string) $request->query('q', ''));
            $maNv = $request->query('ma_nv');
            $extraFilters = [];

            if ($maNv !== null && $maNv !== '') {
                $extraFilters['ma_nv'] = (int) $maNv;
            }

            $result = $this->gateway->listRecords($service, $resource, $page, $limit, $keyword, $extraFilters);

            return response()->json(array_merge(['ok' => true, 'module' => $module], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function show(string $module, string $id): JsonResponse
    {
        try {
            [, $service, $resource] = $this->serviceAndResource($module);
            $result = $this->gateway->getRecord($service, $resource, $id);

            return response()->json(array_merge(['ok' => true, 'module' => $module], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request, string $module): JsonResponse
    {
        try {
            [, $service, $resource] = $this->serviceAndResource($module);
            $result = $this->gateway->createRecord($service, $resource, (array) $request->json()->all());

            return response()->json(array_merge(['ok' => true, 'module' => $module], $result), 201);
        } catch (LogicException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 405);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, string $module, string $id): JsonResponse
    {
        try {
            [, $service, $resource] = $this->serviceAndResource($module);
            $result = $this->gateway->updateRecord($service, $resource, $id, (array) $request->json()->all());

            return response()->json(array_merge(['ok' => true, 'module' => $module], $result));
        } catch (LogicException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 405);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function destroy(string $module, string $id): JsonResponse
    {
        try {
            [, $service, $resource] = $this->serviceAndResource($module);
            $result = $this->gateway->deleteRecord($service, $resource, $id);

            return response()->json(array_merge(['ok' => true, 'module' => $module], $result));
        } catch (LogicException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 405);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function export(Request $request, string $module): JsonResponse
    {
        try {
            [, $service, $resource] = $this->serviceAndResource($module);
            $keyword = trim((string) $request->query('q', ''));
            $result = $this->gateway->exportRecords($service, $resource, $keyword);

            return response()->json(array_merge(['ok' => true, 'module' => $module], $result));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 404);
        }
    }
}