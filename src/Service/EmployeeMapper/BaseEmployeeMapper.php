<?php

namespace App\Service\EmployeeMapper;

use App\Entity\Employee;

abstract class BaseEmployeeMapper
{
    /**
     * Get the provider name this mapper handles
     */
    abstract public function getProvider(): string;

    /**
     * Map provider-specific employee data to our internal Employee entity
     */
    abstract public function mapToEmployee(array $providerData): Employee;

    /**
     * Map our internal Employee entity to TrackTik format
     */
    abstract public function mapToTrackTik(Employee $employee): array;

    /**
     * Validate provider-specific data format
     */
    abstract public function validateProviderData(array $data): bool;

    /**
     * Extract external ID from provider data
     */
    abstract public function getExternalId(array $providerData): string;

    /**
     * Helper method to safely get array value with default
     */
    protected function getValue(array $data, string $key, mixed $default = null): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * Helper method to parse date strings safely
     */
    protected function parseDate(?string $dateString): ?\DateTime
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return new \DateTime($dateString);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Helper method to format phone number
     */
    protected function formatPhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Limit to 20 characters as per entity constraint
        return substr($cleaned, 0, 20);
    }
}