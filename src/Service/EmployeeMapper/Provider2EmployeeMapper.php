<?php

namespace App\Service\EmployeeMapper;

use App\Entity\Employee;

/**
 * Employee mapper for Provider 2
 *
 * Expected Provider 2 Schema:
 * {
 *   "employee_id": "EMP-001",
 *   "name": {
 *     "given": "Jane",
 *     "family": "Smith"
 *   },
 *   "contact": {
 *     "email": "jane.smith@company.com",
 *     "mobile": "555.987.6543"
 *   },
 *   "profile": {
 *     "dob": "1990-03-22",
 *     "start_date": "2022-08-10",
 *     "division": "Operations",
 *     "role": "Operations Manager"
 *   }
 * }
 */
class Provider2EmployeeMapper extends BaseEmployeeMapper
{
    public function getProvider(): string
    {
        return 'provider2';
    }

    public function mapToEmployee(array $providerData): Employee
    {
        $name = $this->getValue($providerData, 'name', []);
        $contact = $this->getValue($providerData, 'contact', []);
        $profile = $this->getValue($providerData, 'profile', []);

        $employee = new Employee();
        $employee->setProvider($this->getProvider());
        $employee->setExternalId($this->getExternalId($providerData));
        $employee->setFirstName($this->getValue($name, 'given', ''));
        $employee->setLastName($this->getValue($name, 'family', ''));
        $employee->setEmail($this->getValue($contact, 'email', ''));
        $employee->setPhoneNumber($this->formatPhoneNumber($this->getValue($contact, 'mobile')));
        $employee->setDateOfBirth($this->parseDate($this->getValue($profile, 'dob')));
        $employee->setHireDate($this->parseDate($this->getValue($profile, 'start_date')));
        $employee->setDepartment($this->getValue($profile, 'division'));
        $employee->setPosition($this->getValue($profile, 'role'));
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
        if (!isset($data['employee_id']) || !isset($data['name']) || !isset($data['contact'])) {
            return false;
        }

        $name = $data['name'];
        $contact = $data['contact'];

        if (empty($name['given']) || empty($name['family'])) {
            return false;
        }

        if (empty($contact['email'])) {
            return false;
        }

        if (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    public function getExternalId(array $providerData): string
    {
        return (string) $this->getValue($providerData, 'employee_id', '');
    }
}