.PHONY: help init setup build up down shell composer test logs clean

help: ## Show this help message
	@echo 'TrackTik Employee Integration API'
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

init: ## Initialize Symfony project from scratch
	@echo "🚀 Creating Symfony project..."
	@if [ ! -f "composer.json" ]; then \
		echo "Creating new Symfony project in temp directory..."; \
		mkdir -p temp-symfony; \
		docker run --rm -v $(PWD)/temp-symfony:/app composer:latest create-project symfony/skeleton:7.0.* /app --no-interaction; \
		echo "Moving files to current directory..."; \
		cp -r temp-symfony/* .; \
		cp -r temp-symfony/.[^.]* . 2>/dev/null || true; \
		rm -rf temp-symfony; \
		echo "📦 Installing additional packages..."; \
		docker run --rm -v $(PWD):/app -w /app composer:latest require --no-interaction \
			symfony/orm-pack \
			symfony/maker-bundle \
			symfony/validator \
			symfony/serializer \
			symfony/http-client \
			nelmio/api-doc-bundle \
			symfony/monolog-bundle \
			predis/predis; \
		docker run --rm -v $(PWD):/app -w /app composer:latest require --dev --no-interaction \
			symfony/test-pack \
			symfony/web-profiler-bundle \
			symfony/debug-bundle; \
		echo "✅ Symfony project created!"; \
	else \
		echo "⚠️ Symfony project already exists (composer.json found)"; \
	fi

docker-config: ## Create Docker configuration files
	@echo "🐳 Creating Docker configuration..."
	mkdir -p docker/nginx docker/php
	@echo 'server { \
		listen 80; \
		root /app/public; \
		index index.php; \
		location / { try_files $$uri $$uri/ /index.php?$$query_string; } \
		location ~ \.php$$ { \
			fastcgi_pass php:9000; \
			fastcgi_index index.php; \
			fastcgi_param SCRIPT_FILENAME $$document_root$$fastcgi_script_name; \
			include fastcgi_params; \
		} \
	}' > docker/nginx/default.conf
	@echo 'memory_limit=512M' > docker/php/php.ini
	@echo "✅ Docker configuration created!"

env-setup: ## Setup environment file
	@echo "⚙️ Creating environment file..."
	@if [ ! -f .env.local ]; then \
		cp .env .env.local; \
		echo "" >> .env.local; \
		echo "# TrackTik API Configuration" >> .env.local; \
		echo "APP_ENV=dev" >> .env.local; \
		echo "TRACKTIK_CLIENT_ID=7d0bebe19b005eeb7cc3cfdb" >> .env.local; \
		echo "TRACKTIK_CLIENT_SECRET=8c2d987ddf8cb248297ea2735890de17e316e03b972c4ca2021886b914b92b2d" >> .env.local; \
		echo "TRACKTIK_BASE_URL=https://smoke.staffr.net" >> .env.local; \
		echo "TRACKTIK_REFRESH_TOKEN=def50200752d94c3cfa71be94594d3f929c44ca467f53b4682c0e7456fe4f119aa45c158669f12871a049082e2eae8cc2d97b7168df7215da0ada54bcfb5f7f9259f1858074921c1be12168c361c2eaa87ba94df1fabcc511669f3452382b14721386d0bff2c55fa43a521ba1ddbe33e7b74b24ab7ae30d184a0c57f93a1323e4d7a9e939d8352741381c23f31488b72a46cd6123e22d42aefe8c18b168fae313f0f98eec130457b71a31197bdab6752bae6cac7fb482f5bbdac1acacc98fe803e5fbf397b8f4abbd7bfb4f9728f89a6f004c82f66e1a8494c4696db411cd1544a9d0a8d5a0f9573196e3fc3f7706adbed68bcc247b233049aa19262f74f1ba8d621a42812b60384e9516d69897259abd1e61549751c717a4c01c6f38f1bd57b79897799a3fb0a8d947ee858a120c60c67baa00606a4b3604d70eeb70251322ab83e06ba08df18c813c3a1f9a7b808292ebcbc40f536d63667e0e49d4a08ccf588930dddaf55ff0274c04116a8d866c1112fa0129b19df673dadf375ed3c9a5c69c373b3361803dea5beb86b65b6a738afdddd7720a1f6b98071302dbc99d7825df8d1985d041069e58e2a2def4f2ce114bf857914fdef5f6fa31e2710f310ba039359625481dacb973a7001d126" >> .env.local; \
		echo "DEFAULT_URI=http://localhost:8080" >> .env.local; \
		echo "APP_SECRET=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6" >> .env.local; \
		echo "DATABASE_URL=postgresql://tracktik:password@postgres:5432/tracktik_db" >> .env.local; \
		echo "✅ Environment file created at .env.local"; \
	else \
		echo "⚠️ .env.local already exists, skipping..."; \
	fi

setup: docker-config init env-setup build ## Complete project setup
	@echo "🎉 Setup complete!"
	@echo "📝 Next steps:"
	@echo "   1. Update TRACKTIK_PASSWORD in .env.local"
	@echo "   2. Run: make up"
	@echo "   3. Run: make create-app"
	@echo "   4. Access API at http://localhost:8080"

build: ## Build Docker containers (after project is created)
	@if [ ! -f "composer.json" ]; then \
		echo "❌ No composer.json found. Run 'make init' first."; \
		exit 1; \
	fi
	docker compose build

up: ## Start all services
	docker compose up -d
	@echo "✅ Services started!"
	@echo "🌐 API will be available at http://localhost:8080"

down: ## Stop all services
	docker compose down

shell: ## Access PHP container shell
	docker compose exec php bash

composer: ## Run composer install
	docker compose exec php composer install

create-app: ## Create the TrackTik integration application
	@echo "🏗️ Setting up TrackTik integration application..."
	@echo "Installing dependencies..."
	docker compose exec php composer install --no-interaction
	@echo "Creating database..."
	docker compose exec php php bin/console doctrine:database:create --if-not-exists || true
	@echo "✅ Application ready for development!"
	@echo ""
	@echo "🎯 Next steps:"
	@echo "   • Use 'make shell' to access the container"
	@echo "   • Run 'php bin/console make:entity Employee' to create entities"
	@echo "   • Run 'php bin/console make:controller EmployeeController' to create controllers"
	@echo "   • Access the app at http://localhost:8080"

migrate: ## Run database migrations
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

test: ## Run tests
	docker compose exec -e APP_ENV=test -e APP_DEBUG=1 php php bin/phpunit

logs: ## Show application logs
	docker compose logs -f

clean: ## Clean up containers and volumes
	docker compose down -v
	docker system prune -f

clean-files: ## Clean up generated project files
	@echo "🧹 Cleaning up generated project files..."
	@# Change ownership back to current user first
	@if [ -d "vendor" ] || [ -d "var" ]; then \
		echo "Fixing file permissions..."; \
		docker run --rm -v $(PWD):/app alpine:latest chown -R $(shell id -u):$(shell id -g) /app || true; \
	fi
	@# Now remove files
	rm -rf vendor/ composer.json composer.lock
	rm -rf var/ .env .env.local .env.test
	rm -rf bin/ config/ migrations/ public/ src/ tests/
	rm -rf temp-symfony/ symfony.lock
	rm -rf .gitignore README.md
	@echo "✅ Project files cleaned!"

reset: clean clean-files ## Reset everything (Docker + project files)
	@echo "🔄 Complete reset finished!"
	@echo "📝 Ready for fresh setup with 'make setup'"

fix-permissions: ## Fix file permissions (if you have permission issues)
	@echo "🔧 Fixing file permissions..."
	docker run --rm -v $(PWD):/app alpine:latest chown -R $(shell id -u):$(shell id -g) /app
	@# Create necessary directories and set permissions
	mkdir -p var/cache var/log var/sessions
	chmod -R 777 var/
	@echo "✅ Permissions fixed!"

fresh: reset setup ## Fresh installation
	@echo "🎉 Fresh installation complete!"

start: up composer migrate
