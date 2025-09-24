<?php

namespace App\Controller;

use App\Service\EmployeeService;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/employees', name: 'api_employees_')]
#[OA\Tag(name: 'Employees')]
class EmployeeController extends AbstractController
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {}

    #[Route('/provider1', name: 'provider1_webhook', methods: ['POST'])]
    #[OA\Post(
        path: '/api/employees/provider1',
        summary: 'Receive employee data from Provider 1',
        requestBody: new OA\RequestBody(
            description: 'Employee data from Provider 1',
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    'id' => new OA\Property(property: 'id', type: 'string', example: '12345'),
                    'personal_info' => new OA\Property(
                        property: 'personal_info',
                        type: 'object',
                        properties: [
                            'first_name' => new OA\Property(property: 'first_name', type: 'string', example: 'John'),
                            'last_name' => new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
                            'email_address' => new OA\Property(property: 'email_address', type: 'string', example: 'john.doe@example.com'),
                            'phone' => new OA\Property(property: 'phone', type: 'string', example: '+1-555-123-4567'),
                            'birth_date' => new OA\Property(property: 'birth_date', type: 'string', example: '1985-06-15'),
                        ]
                    ),
                    'employment' => new OA\Property(
                        property: 'employment',
                        type: 'object',
                        properties: [
                            'hire_date' => new OA\Property(property: 'hire_date', type: 'string', example: '2023-01-15'),
                            'department_name' => new OA\Property(property: 'department_name', type: 'string', example: 'Security'),
                            'job_title' => new OA\Property(property: 'job_title', type: 'string', example: 'Security Guard'),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Employee processed successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean', example: true),
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Employee processed successfully'),
                        'employee_id' => new OA\Property(property: 'employee_id', type: 'integer', example: 123),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid employee data',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean', example: false),
                        'error' => new OA\Property(property: 'error', type: 'string', example: 'Invalid employee data format'),
                    ]
                )
            ),
        ]
    )]
    public function receiveFromProvider1(Request $request): JsonResponse
    {
        return $this->processEmployeeData('provider1', $request);
    }

    #[Route('/provider2', name: 'provider2_webhook', methods: ['POST'])]
    #[OA\Post(
        path: '/api/employees/provider2',
        summary: 'Receive employee data from Provider 2',
        requestBody: new OA\RequestBody(
            description: 'Employee data from Provider 2',
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    'employee_id' => new OA\Property(property: 'employee_id', type: 'string', example: 'EMP-001'),
                    'name' => new OA\Property(
                        property: 'name',
                        type: 'object',
                        properties: [
                            'given' => new OA\Property(property: 'given', type: 'string', example: 'Jane'),
                            'family' => new OA\Property(property: 'family', type: 'string', example: 'Smith'),
                        ]
                    ),
                    'contact' => new OA\Property(
                        property: 'contact',
                        type: 'object',
                        properties: [
                            'email' => new OA\Property(property: 'email', type: 'string', example: 'jane.smith@company.com'),
                            'mobile' => new OA\Property(property: 'mobile', type: 'string', example: '555.987.6543'),
                        ]
                    ),
                    'profile' => new OA\Property(
                        property: 'profile',
                        type: 'object',
                        properties: [
                            'dob' => new OA\Property(property: 'dob', type: 'string', example: '1990-03-22'),
                            'start_date' => new OA\Property(property: 'start_date', type: 'string', example: '2022-08-10'),
                            'division' => new OA\Property(property: 'division', type: 'string', example: 'Operations'),
                            'role' => new OA\Property(property: 'role', type: 'string', example: 'Operations Manager'),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Employee processed successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean', example: true),
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Employee processed successfully'),
                        'employee_id' => new OA\Property(property: 'employee_id', type: 'integer', example: 456),
                    ]
                )
            ),
        ]
    )]
    public function receiveFromProvider2(Request $request): JsonResponse
    {
        return $this->processEmployeeData('provider2', $request);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/employees',
        summary: 'Get all employees',
        parameters: [
            new OA\Parameter(
                name: 'provider',
                description: 'Filter by provider',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['provider1', 'provider2'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of employees',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: \App\Entity\Employee::class, groups: ['employee:read']))
                )
            ),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $provider = $request->query->get('provider');

            if ($provider) {
                $employees = $this->employeeService->getEmployeesByProvider($provider);
            } else {
                $employees = $this->employeeService->getAllEmployees();
            }

            $json = $this->serializer->serialize($employees, 'json', ['groups' => ['employee:read']]);

            return new JsonResponse($json, Response::HTTP_OK, [], true);

        } catch (\Exception $e) {
            $this->logger->error('Failed to list employees', ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve employees'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/sync', name: 'sync_all', methods: ['POST'])]
    #[OA\Post(
        path: '/api/employees/sync',
        summary: 'Sync all pending employees to TrackTik',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sync completed',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean', example: true),
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Sync completed'),
                        'synced_count' => new OA\Property(property: 'synced_count', type: 'integer', example: 5),
                    ]
                )
            ),
        ]
    )]
    public function syncAll(): JsonResponse
    {
        try {
            $syncedCount = $this->employeeService->syncAllPendingEmployees();

            return new JsonResponse([
                'success' => true,
                'message' => 'Sync completed',
                'synced_count' => $syncedCount
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to sync employees', ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to sync employees'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Common method to process employee data from providers
     */
    private function processEmployeeData(string $provider, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ], Response::HTTP_BAD_REQUEST);
            }

            $employee = $this->employeeService->processEmployeeData($provider, $data);

            $this->logger->info("Employee processed from $provider", [
                'employee_id' => $employee->getId(),
                'external_id' => $employee->getExternalId()
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Employee processed successfully',
                'employee_id' => $employee->getId()
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning("Invalid employee data from $provider", ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error("Failed to process employee from $provider", ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}