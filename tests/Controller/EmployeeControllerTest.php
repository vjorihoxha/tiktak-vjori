<?php

namespace App\Tests\Controller;

use App\Entity\Employee;
use App\Service\EmployeeService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EmployeeControllerTest extends WebTestCase
{
    public function testReceiveFromProvider1WithValidData(): void
    {
        $client = static::createClient();

        $employeeData = [
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

        // Mock the EmployeeService
        $employee = new Employee();
        $employee->setFirstName('John');
        $employee->setLastName('Doe');
        $employee->setEmail('john.doe@example.com');
        $employee->setProvider('provider1');
        $employee->setExternalId('12345');

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService
            ->expects($this->once())
            ->method('processEmployeeData')
            ->with('provider1', $employeeData)
            ->willReturn($employee);

        $client->getContainer()->set(EmployeeService::class, $employeeService);

        $client->request(
            'POST',
            '/api/employees/provider1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($employeeData)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Employee processed successfully', $responseData['message']);
    }

    public function testReceiveFromProvider1WithInvalidJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/employees/provider1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Invalid JSON data', $responseData['error']);
    }

    public function testReceiveFromProvider2WithValidData(): void
    {
        $client = static::createClient();

        $employeeData = [
            'employee_id' => 'EMP-001',
            'name' => [
                'given' => 'Jane',
                'family' => 'Smith',
            ],
            'contact' => [
                'email' => 'jane.smith@company.com',
                'mobile' => '555.987.6543',
            ],
            'profile' => [
                'dob' => '1990-03-22',
                'start_date' => '2022-08-10',
                'division' => 'Operations',
                'role' => 'Operations Manager',
            ],
        ];

        // Mock the EmployeeService
        $employee = new Employee();
        $employee->setFirstName('Jane');
        $employee->setLastName('Smith');
        $employee->setEmail('jane.smith@company.com');
        $employee->setProvider('provider2');
        $employee->setExternalId('EMP-001');

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService
            ->expects($this->once())
            ->method('processEmployeeData')
            ->with('provider2', $employeeData)
            ->willReturn($employee);

        $client->getContainer()->set(EmployeeService::class, $employeeService);

        $client->request(
            'POST',
            '/api/employees/provider2',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($employeeData)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Employee processed successfully', $responseData['message']);
    }

    public function testListEmployees(): void
    {
        $client = static::createClient();

        $employees = [
            $this->createEmployeeForTesting('John', 'Doe', 'provider1'),
            $this->createEmployeeForTesting('Jane', 'Smith', 'provider2'),
        ];

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService
            ->expects($this->once())
            ->method('getAllEmployees')
            ->willReturn($employees);

        $client->getContainer()->set(EmployeeService::class, $employeeService);

        $client->request('GET', '/api/employees');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertCount(2, $responseData);
    }

    public function testSyncAllEmployees(): void
    {
        $client = static::createClient();

        $employeeService = $this->createMock(EmployeeService::class);
        $employeeService
            ->expects($this->once())
            ->method('syncAllPendingEmployees')
            ->willReturn(3);

        $client->getContainer()->set(EmployeeService::class, $employeeService);

        $client->request('POST', '/api/employees/sync');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Sync completed', $responseData['message']);
        $this->assertEquals(3, $responseData['synced_count']);
    }

    private function createEmployeeForTesting(string $firstName, string $lastName, string $provider): Employee
    {
        $employee = new Employee();
        $employee->setFirstName($firstName);
        $employee->setLastName($lastName);
        $employee->setEmail(strtolower($firstName . '.' . $lastName . '@example.com'));
        $employee->setProvider($provider);
        $employee->setExternalId(uniqid());

        return $employee;
    }
}