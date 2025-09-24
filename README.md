# TrackTik Employee Integration API

A Symfony-based API that receives employee data from multiple identity providers and automatically syncs it to TrackTik's REST API.

## Notes from Vjori
- Syncing with TrackTik API in the real world I'd do it with RabbitMQ or another message broker to emit an event so the user doesn't have to wait for the API response and we can sync it on the background.

## Features

- **Multi-Provider Support**: Handles employee data from 2 different identity providers with unique schemas
- **Automatic Mapping**: Maps provider schemas to TrackTik's employee format
- **Duplicate Detection**: Prevents duplicate employees by tracking external IDs
- **Automatic Sync**: Syncs employee data to TrackTik API in real-time
- **Docker Ready**: Complete Docker setup with no local dependencies
- **API Documentation**: Interactive Swagger/OpenAPI documentation
- **Comprehensive Testing**: Unit and integration tests included
- **Monitoring**: Structured logging and error handling

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Make (for using the Makefile commands)

### Setup

1. **Clone and setup the project:**
   ```bash
   make start
   ```

   This will:
    - Build Docker containers
    - Start all services
    - Install dependencies
    - Run database migrations

2. **Access the application:**
    - API: http://localhost:8080
    - API Documentation: http://localhost:8080/api/doc

## Architecture

```
┌─────────────────┐    ┌─────────────────┐
│   Provider 1    │    │   Provider 2    │
│   (Schema A)    │    │   (Schema B)    │
└─────────┬───────┘    └─────────┬───────┘
          │                      │
          │  POST employee data  │
          │                      │
          ▼                      ▼
    ┌─────────────────────────────────────┐
    │          Your API                   │
    │  ┌─────────────────────────────┐   │
    │  │     Employee Mappers        │   │
    │  │  ┌──────────┐ ┌──────────┐  │   │
    │  │  │Provider1 │ │Provider2 │  │   │
    │  │  │ Mapper   │ │ Mapper   │  │   │
    │  │  └──────────┘ └──────────┘  │   │
    │  └─────────────────────────────┘   │
    │  ┌─────────────────────────────┐   │
    │  │     Employee Service        │   │
    │  └─────────────────────────────┘   │
    │  ┌─────────────────────────────┐   │
    │  │     Database Storage        │   │
    │  └─────────────────────────────┘   │
    └─────────────┬───────────────────────┘
                  │
                  │ Sync employee data
                  │
                  ▼
          ┌───────────────┐
          │   TrackTik    │
          │   REST API    │
          └───────────────┘
```

## API Endpoints

### Provider 1 Webhook
```http
POST /api/employees/provider1
Content-Type: application/json

{
  "id": "12345",
  "personal_info": {
    "first_name": "John",
    "last_name": "Doe",
    "email_address": "john.doe@example.com",
    "phone": "+1-555-123-4567",
    "birth_date": "1985-06-15"
  },
  "employment": {
    "hire_date": "2023-01-15",
    "department_name": "Security",
    "job_title": "Security Guard"
  }
}
```

### Provider 2 Webhook
```http
POST /api/employees/provider2
Content-Type: application/json

{
  "employee_id": "EMP-001",
  "name": {
    "given": "Jane",
    "family": "Smith"
  },
  "contact": {
    "email": "jane.smith@company.com",
    "mobile": "555.987.6543"
  },
  "profile": {
    "dob": "1990-03-22",
    "start_date": "2022-08-10",
    "division": "Operations",
    "role": "Operations Manager"
  }
}
```

### List Employees
```http
GET /api/employees?provider=provider1
```

### Sync Pending Employees
```http
POST /api/employees/sync
```

## Configuration

Update the `.env` file with your TrackTik API credentials:

```bash
# TrackTik API Configuration
TRACKTIK_CLIENT_ID=your-client-id
TRACKTIK_CLIENT_SECRET=your-client-secret
TRACKTIK_BASE_URL=https://smoke.staffr.net
TRACKTIK_USERNAME=your-username
TRACKTIK_PASSWORD=your-password
```

## Available Make Commands

```bash
make help              # Show available commands
make setup             # Complete setup from scratch
make build             # Build Docker containers
make up                # Start all services
make down              # Stop all services
make shell             # Access app container shell
make composer-install  # Install composer dependencies
make migrate           # Run database migrations
make test              # Run tests
make logs              # Show application logs
make clean             # Clean up containers and volumes
make api-docs          # Generate API documentation
make validate          # Validate the application
```

## Development Workflow

### 1. Making Changes
```bash
# Access the container
make shell

# Install new dependencies
composer require new/package

# Create new migration
php bin/console make:migration

# Run migrations
make migrate
```

### 2. Testing
```bash
# Run all tests
make test

# Run specific test
docker-compose exec app php bin/phpunit tests/Service/EmployeeServiceTest.php
```

### 3. Console Commands
```bash
# Sync employees manually
docker-compose exec app php bin/console app:sync-employees

# Clear cache
docker-compose exec app php bin/console cache:clear
```

## Employee Data Flow

1. **Provider sends employee data** → API endpoint (`/api/employees/provider1` or `/api/employees/provider2`)
2. **Data validation** → Ensures required fields are present and valid
3. **Schema mapping** → Converts provider schema to internal Employee entity
4. **Database storage** → Saves/updates employee in PostgreSQL
5. **TrackTik sync** → Maps internal schema to TrackTik format and sends via API
6. **Response** → Returns success/error status to provider

## TrackTik Integration

The system maps internal employee data to TrackTik's format:

```php
// Internal Employee → TrackTik Format
[
    'firstName' => $employee->getFirstName(),
    'lastName' => $employee->getLastName(),
    'email' => $employee->getEmail(),
    'primaryPhone' => $employee->getPhoneNumber(),
    'startDate' => $employee->getHireDate()->format('Y-m-d'),
    'birthdate' => $employee->getDateOfBirth()->format('Y-m-d'),
    'department' => $employee->getDepartment(),
    'jobTitle' => $employee->getPosition(),
    'customFields' => [
        'source_provider' => $employee->getProvider(),
        'external_id' => $employee->getExternalId()
    ]
]
```

## Error Handling

- **Invalid JSON**: Returns 400 with error message
- **Missing required fields**: Returns 400 with validation details
- **Duplicate email**: Handled by updating existing employee
- **TrackTik API errors**: Logged but don't block employee storage
- **Database errors**: Returns 500 with generic error message

## Logging

All operations are logged with structured data:

```bash
# View logs
make logs

# Follow logs in real-time
docker-compose logs -f app
```

## Security Considerations

- Input validation on all employee data
- SQL injection protection via Doctrine ORM
- Error messages don't expose internal details
- TrackTik credentials stored in environment variables
- Rate limiting can be added via reverse proxy

## Extending the System

### Adding New Providers

1. **Create new mapper**:
   ```php
   // src/Service/EmployeeMapper/Provider3EmployeeMapper.php
   class Provider3EmployeeMapper extends BaseEmployeeMapper
   {
       public function getProvider(): string { return 'provider3'; }
       // Implement required methods...
   }
   ```

2. **Add route**:
   ```php
   #[Route('/provider3', name: 'provider3_webhook', methods: ['POST'])]
   public function receiveFromProvider3(Request $request): JsonResponse
   {
       return $this->processEmployeeData('provider3', $request);
   }
   ```

3. **Register in services.yaml** (auto-registered via tags)

### Custom Field Mapping

Extend the mappers to handle additional fields by modifying the `mapToTrackTik()` method.

## Testing Providers

Use curl to test the endpoints:

```bash
# Test Provider 1
curl -X POST http://localhost:8080/api/employees/provider1 \
  -H "Content-Type: application/json" \
  -d '{
    "id": "12345",
    "personal_info": {
      "first_name": "John",
      "last_name": "Doe",
      "email_address": "john.doe@example.com"
    }
  }'

# Test Provider 2  
curl -X POST http://localhost:8080/api/employees/provider2 \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": "EMP-001",
    "name": {
      "given": "Jane",
      "family": "Smith"
    },
    "contact": {
      "email": "jane.smith@company.com"
    }
  }'
```

## Troubleshooting

### Common Issues

1. **Port 8080 already in use**:
   ```bash
   # Change port in docker-compose.yml
   ports:
     - "8081:80"
   ```

2. **Database connection issues**:
   ```bash
   # Check if PostgreSQL is running
   docker-compose ps
   
   # Restart database
   docker-compose restart database
   ```

3. **TrackTik authentication fails**:
    - Verify credentials in `.env`
    - Check TrackTik API status
    - Review logs for detailed error messages

4. **Memory issues**:
   ```bash
   # Increase PHP memory limit
   # In Dockerfile, add:
   RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini
   ```

## Support

- Check the interactive API documentation at `/api/doc`
- Review application logs with `make logs`
- Run validation with `make validate`
- Use `make test` to ensure everything is working

This solution demonstrates best practices in PHP/Symfony development including proper architecture, testing, documentation, and Docker containerization.