<?php

namespace App\Service\EmployeeMapper;

use App\Entity\Employee;

/**
 * Employee mapper for Provider 1
 *
 * Expected Provider 1 Schema:
 * {
 *   "id": "12345",
 *   "personal_info": {
 *     "first_name": "John",
 *     "last_name": "Doe",
 *     "email_address": "john.doe@example.com",
 *     "phone": "+1-555-123-4567",
 *     "birth_date": "1985-06-15"
 *   },
 *   "employment": {
 *     "hire_date": "2023-01-15",
 *     "department_name": "Security",
 *     "job_title": "Security Guard"
 *   }
 * }
 */
class Provider1EmployeeMapper extends BaseEmployeeMapper
{
    public function getProvider(): string
    {
        return 'provider1';
    }

    public function mapToEmployee(array $providerData): Employee
    {
        $personalInfo = $this->getValue($providerData, 'personal_info', []);
        $employment = $this->getValue($providerData, 'employment', []);

        $employee = new Employee();
        $employee->setProvider($this->getProvider());
        $employee->setExternalId($this->getExternalId($providerData));
        $employee->setFirstName($this->getValue($personalInfo, 'first_name', ''));
        $employee->setLastName($this->getValue($personalInfo, 'last_name', ''));
        $employee->setEmail($this->getValue($personalInfo, 'email_address', ''));
        $employee->setPhoneNumber($this->formatPhoneNumber($this->getValue($personalInfo, 'phone')));
        $employee->setDateOfBirth($this->parseDate($this->getValue($personalInfo, 'birth_date')));
        $employee->setHireDate($this->parseDate($this->getValue($employment, 'hire_date')));
        $employee->setDepartment($this->getValue($employment, 'department_name'));
        $employee->setPosition($this->getValue($employment, 'job_title'));
        $employee->setRawData($providerData);

        return $employee;
    }

    public function mapToTrackTik(Employee $employee): array
    {
        $data = [
            'firstName' => $employee->getFirstName(),
            'lastName' => $employee->getLastName(),
            'email' => $employee->getEmail(),
        ];

        if ($employee->getPhoneNumber()) {
            $data['primaryPhone'] = $employee->getPhoneNumber();
        }

        if ($employee->getHireDate()) {
            $data['startDate'] = $employee->getHireDate()->format('Y-m-d');
        }

        if ($employee->getDateOfBirth()) {
            $data['birthdate'] = $employee->getDateOfBirth()->format('Y-m-d');
        }

        if ($employee->getDepartment()) {
            $data['department'] = $employee->getDepartment();
        }

        if ($employee->getPosition()) {
            $data['jobTitle'] = $employee->getPosition();
        }

        $data['customFields'] = [
            'source_provider' => $this->getProvider(),
            'external_id' => $employee->getExternalId(),
        ];

        return $data;
    }

    public function validateProviderData(array $data): bool
    {
        if (!isset($data['id']) || !isset($data['personal_info'])) {
            return false;
        }

        $personalInfo = $data['personal_info'];

        $requiredFields = ['first_name', 'last_name', 'email_address'];
        foreach ($requiredFields as $field) {
            if (empty($personalInfo[$field])) {
                return false;
            }
        }

        if (!filter_var($personalInfo['email_address'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    public function getExternalId(array $providerData): string
    {
        return (string) $this->getValue($providerData, 'id', '');
    }
}