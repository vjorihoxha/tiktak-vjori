<?php

namespace App\Tests\Service;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use App\Service\EmployeeMapper\Provider1EmployeeMapper;
use App\Service\EmployeeService;
use App\Service\TrackTikApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EmployeeServiceTest extends TestCase
{
    private EmployeeService $employeeService;
    private EmployeeRepository $employeeRepository;
    private TrackTikApiService $trackTikApiService;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);
        $this->trackTikApiService = $this->createMock(TrackTikApiService::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $provider1Mapper = new Provider1EmployeeMapper();
        $mappers = [$provider1Mapper];

        $this->employeeService = new EmployeeService(
            $this->employeeRepository,
            $this->trackTikApiService,
            $this->validator,
            $this->logger,
            $mappers
        );
    }

    public function testProcessEmployeeDataCreatesNewEmployee(): void
    {
        $providerData = [
            'id' => '12345',
            'personal_info' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email_address' => 'john.doe@example.com',
                'phone' => '+1-555-123-4567',
                'birth_date' => '1985-06-15',
            ],
            'employment' => [
                'hire_date' => '2023-01-15',
                'department_name' => 'Security',
                'job_title' => 'Security Guard',
            ],
        ];

        $this->employeeRepository
            ->expects($this->once())
            ->method('findByProviderAndExternalId')
            ->with('provider1', '12345')
            ->willReturn(null);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->trackTikApiService
            ->expects($this->once())
            ->method('createEmployee')
            ->willReturn(['data' => ['id' => 123]]);

        $employee = $this->employeeService->processEmployeeData('provider1', $providerData);

        $this->assertInstanceOf(Employee::class, $employee);
        $this->assertEquals('John', $employee->getFirstName());
        $this->assertEquals('Doe', $employee->getLastName());
        $this->assertEquals('john.doe@example.com', $employee->getEmail());
        $this->assertEquals('provider1', $employee->getProvider());
        $this->assertEquals('12345', $employee->getExternalId());
    }

    public function testProcessEmployeeDataUpdatesExistingEmployee(): void
    {
        $providerData = [
            'id' => '12345',
            'personal_info' => [
                'first_name' => 'John',
                'last_name' => 'Smith', // Updated last name
                'email_address' => 'john.smith@example.com', // Updated email
                'phone' => '+1-555-123-4567',
                'birth_date' => '1985-06-15',
            ],
            'employment' => [
                'hire_date' => '2023-01-15',
                'department_name' => 'Security',
                'job_title' => 'Security Guard',
            ],
        ];

        $existingEmployee = new Employee();
        $existingEmployee->setProvider('provider1');
        $existingEmployee->setExternalId('12345');
        $existingEmployee->setFirstName('John');
        $existingEmployee->setLastName('Doe');
        $existingEmployee->setEmail('john.doe@example.com');
        $existingEmployee->setTrackTikId(123);

        $this->employeeRepository
            ->expects($this->once())
            ->method('findByProviderAndExternalId')
            ->with('provider1', '12345')
            ->willReturn($existingEmployee);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->employeeRepository
            ->expects($this->once())
            ->method('save')
            ->with($existingEmployee, true);

        $this->trackTikApiService
            ->expects($this->once())
            ->method('updateEmployee')
            ->with(123, $this->isType('array'))
            ->willReturn(['data' => ['id' => 123]]);

        $employee = $this->employeeService->processEmployeeData('provider1', $providerData);

        $this->assertEquals('Smith', $employee->getLastName());
        $this->assertEquals('john.smith@example.com', $employee->getEmail());
    }

    public function testProcessEmployeeDataThrowsExceptionForUnsupportedProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported provider: unknown_provider');

        $this->employeeService->processEmployeeData('unknown_provider', []);
    }

    public function testProcessEmployeeDataThrowsExceptionForInvalidData(): void
    {
        $invalidData = [
            'id' => '12345',
            // Missing required personal_info
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid employee data format for provider: provider1');

        $this->employeeService->processEmployeeData('provider1', $invalidData);
    }

    public function testSyncToTrackTikReturnsTrue(): void
    {
        $employee = new Employee();
        $employee->setProvider('provider1');
        $employee->setFirstName('John');
        $employee->setLastName('Doe');
        $employee->setEmail('john.doe@example.com');

        $this->trackTikApiService
            ->expects($this->once())
            ->method('createEmployee')
            ->willReturn(['data' => ['id' => 456]]);

        $this->employeeRepository
            ->expects($this->once())
            ->method('save')
            ->with($employee, true);

        $result = $this->employeeService->syncToTrackTik($employee);

        $this->assertTrue($result);
        $this->assertEquals(456, $employee->getTrackTikId());
    }

    public function testSyncToTrackTikReturnsFalseOnError(): void
    {
        $employee = new Employee();
        $employee->setProvider('provider1');
        $employee->setFirstName('John');
        $employee->setLastName('Doe');
        $employee->setEmail('john.doe@example.com');

        $this->trackTikApiService
            ->expects($this->once())
            ->method('createEmployee')
            ->willThrowException(new \RuntimeException('API Error'));

        $this->logger
            ->expects($this->once())
            ->method('error');

        $result = $this->employeeService->syncToTrackTik($employee);

        $this->assertFalse($result);
    }
}