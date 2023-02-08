# Well documented Makefiles
DEFAULT_GOAL := help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-40s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

build: ## Build all docker images. Specify the command e.g. via make build ARGS="--build-arg PHP=8.2"
	docker compose build $(ARGS)

##@ [Application]
install: ## Install the composer dependencies
	docker compose run --rm composer install

test: ## Run the tests
	docker compose run --rm composer test
