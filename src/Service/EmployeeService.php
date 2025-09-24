<?php

namespace App\Service;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use App\Service\EmployeeMapper\BaseEmployeeMapper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EmployeeService
{
    /** @var BaseEmployeeMapper[] */
    private array $mappers = [];

    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly TrackTikApiService $trackTikApiService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        iterable $mappers
    ) {
        foreach ($mappers as $mapper) {
            $this->addMapper($mapper);
        }
    }

    private function addMapper(BaseEmployeeMapper $mapper): void
    {
        $this->mappers[$mapper->getProvider()] = $mapper;
    }

    /**
     * Process employee data from a provider
     */
    public function processEmployeeData(string $provider, array $employeeData): Employee
    {
        if (!isset($this->mappers[$provider])) {
            throw new \InvalidArgumentException("Unsupported provider: $provider");
        }

        $mapper = $this->mappers[$provider];

        // Validate provider data
        if (!$mapper->validateProviderData($employeeData)) {
            throw new \InvalidArgumentException("Invalid employee data format for provider: $provider");
        }

        // Check if employee already exists
        $externalId = $mapper->getExternalId($employeeData);
        $existingEmployee = $this->employeeRepository->findByProviderAndExternalId($provider, $externalId);

        if ($existingEmployee) {
            // Update existing employee
            $employee = $this->updateEmployeeFromProviderData($existingEmployee, $mapper, $employeeData);
            $this->logger->info('Updated existing employee', [
                'provider' => $provider,
                'external_id' => $externalId,
                'employee_id' => $employee->getId()
            ]);
        } else {
            // Create new employee
            $employee = $this->createEmployeeFromProviderData($mapper, $employeeData);
            $this->logger->info('Created new employee', [
                'provider' => $provider,
                'external_id' => $externalId,
                'employee_id' => $employee->getId()
            ]);
        }

        /*
         * Vjori: In these situations I'd emit an event preferrably RabbitMQ but for the sake of this exercise I left it simply like this*/
        $this->syncToTrackTik($employee);

        return $employee;
    }

    /**
     * Create a new employee from provider data
     */
    private function createEmployeeFromProviderData(BaseEmployeeMapper $mapper, array $providerData): Employee
    {
        $employee = $mapper->mapToEmployee($providerData);

        // Validate the mapped employee
        $violations = $this->validator->validate($employee);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            throw new \InvalidArgumentException('Employee validation failed: ' . implode(', ', $errors));
        }

        $this->employeeRepository->save($employee, true);

        return $employee;
    }

    /**
     * Update existing employee from provider data
     */
    private function updateEmployeeFromProviderData(Employee $existingEmployee, BaseEmployeeMapper $mapper, array $providerData): Employee
    {
        $updatedEmployee = $mapper->mapToEmployee($providerData);

        // Update fields
        $existingEmployee->setFirstName($updatedEmployee->getFirstName());
        $existingEmployee->setLastName($updatedEmployee->getLastName());
        $existingEmployee->setEmail($updatedEmployee->getEmail());
        $existingEmployee->setPhoneNumber($updatedEmployee->getPhoneNumber());
        $existingEmployee->setDateOfBirth($updatedEmployee->getDateOfBirth());
        $existingEmployee->setHireDate($updatedEmployee->getHireDate());
        $existingEmployee->setDepartment($updatedEmployee->getDepartment());
        $existingEmployee->setPosition($updatedEmployee->getPosition());
        $existingEmployee->setRawData($updatedEmployee->getRawData());

        // Validate the updated employee
        $violations = $this->validator->validate($existingEmployee);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            throw new \InvalidArgumentException('Employee validation failed: ' . implode(', ', $errors));
        }

        $this->employeeRepository->save($existingEmployee, true);

        return $existingEmployee;
    }

    /**
     * Sync employee to TrackTik API
     */
    public function syncToTrackTik(Employee $employee): bool
    {
        try {
            $mapper = $this->mappers[$employee->getProvider()];
            $trackTikData = $mapper->mapToTrackTik($employee);

            //These would be events with RabbitMQ or something else, user doesn't need to wait for this call
            if ($employee->getTrackTikId()) {
                $this->trackTikApiService->updateEmployee($employee->getTrackTikId(), $trackTikData);
            } else {
                $result = $this->trackTikApiService->createEmployee($trackTikData);
                if (isset($result['data']['id'])) {
                    $employee->setTrackTikId($result['data']['id']);
                    $this->employeeRepository->save($employee, true);
                }
            }

            $this->logger->info('Employee synced to TrackTik successfully', [
                'employee_id' => $employee->getId(),
                'tracktik_id' => $employee->getTrackTikId(),
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to sync employee to TrackTik', [
                'employee_id' => $employee->getId(),
                'error' => $e->getMessage(),
            ]);
            return false; // <-- tell the caller it failed
        }
    }


    /**
     * Sync all pending employees to TrackTik
     */
    public function syncAllPendingEmployees(): int
    {
        $pendingEmployees = $this->employeeRepository->findPendingSync();
        $syncedCount = 0;

        foreach ($pendingEmployees as $employee) {
            if ($this->syncToTrackTik($employee)) {
                $syncedCount++;
            }
        }

        $this->logger->info("Batch sync completed", [
            'total_pending' => count($pendingEmployees),
            'synced' => $syncedCount,
            'failed' => count($pendingEmployees) - $syncedCount,
        ]);

        return $syncedCount;
    }


    /**
     * Get all employees
     */
    public function getAllEmployees(): array
    {
        return $this->employeeRepository->findAll();
    }

    /**
     * Get employees by provider
     */
    public function getEmployeesByProvider(string $provider): array
    {
        return $this->employeeRepository->findByProvider($provider);
    }
}