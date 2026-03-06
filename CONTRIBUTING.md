# Contributing to Core PHP Framework

Thank you for considering contributing to the Core PHP Framework! This document outlines the process and guidelines for contributing.

## Code of Conduct

This project adheres to a code of conduct that all contributors are expected to follow. Be respectful, professional, and inclusive in all interactions.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When creating a bug report, include:

- **Clear title and description**
- **Steps to reproduce** the behavior
- **Expected vs actual behavior**
- **PHP and Laravel versions**
- **Code samples** if applicable
- **Error messages** and stack traces

### Security Vulnerabilities

**DO NOT** open public issues for security vulnerabilities. Instead, email security concerns to: **support@host.uk.com**

We take security seriously and will respond promptly to valid security reports.

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- **Use a clear and descriptive title**
- **Provide a detailed description** of the proposed feature
- **Explain why this enhancement would be useful** to most users
- **List similar features** in other frameworks if applicable

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow the coding standards** (see below)
3. **Add tests** for any new functionality
4. **Update documentation** as needed
5. **Ensure all tests pass** before submitting
6. **Write clear commit messages** (see below)

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Laravel 11 or 12

### Setup Steps

```bash
# Clone your fork
git clone https://github.com/your-username/core-php.git
cd core-php

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Run tests
composer test
```

## Coding Standards

### PSR Standards

- Follow **PSR-12** coding style
- Use **PSR-4** autoloading

### Laravel Conventions

- Use **Laravel's naming conventions** for classes, methods, and variables
- Follow **Laravel's directory structure** patterns
- Use **Eloquent** for database interactions where appropriate

### Code Style

We use **Laravel Pint** for code formatting:

```bash
./vendor/bin/pint
```

Run this before committing to ensure consistent code style.

### PHP Standards

- Use **strict typing**: `declare(strict_types=1);`
- Add **type hints** for all method parameters and return types
- Use **short array syntax**: `[]` instead of `array()`
- Document complex logic with clear comments
- Avoid abbreviations in variable/method names

### Testing

- Write **feature tests** for new functionality
- Write **unit tests** for complex business logic
- Aim for **> 70% code coverage**
- Use **meaningful test names** that describe what is being tested

```php
public function test_user_can_create_workspace_with_valid_data(): void
{
    // Test implementation
}
```

## Commit Message Guidelines

### Format

```
type(scope): subject

body (optional)

footer (optional)
```

### Types

- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, semicolons, etc.)
- **refactor**: Code refactoring without feature changes
- **test**: Adding or updating tests
- **chore**: Maintenance tasks

### Examples

```
feat(modules): add lazy loading for API modules

Implement lazy loading system that only loads API modules
when API routes are being registered, improving performance
for web-only requests.

Closes #123
```

```
fix(auth): resolve session timeout issue

Fix session expiration not being properly handled in multi-tenant
environment.

Fixes #456
```

### Rules

- Use **present tense**: "add feature" not "added feature"
- Use **imperative mood**: "move cursor to..." not "moves cursor to..."
- Keep **subject line under 72 characters**
- Reference **issue numbers** when applicable
- **Separate subject from body** with a blank line

## Package Development

### Creating a New Package

New packages should follow this structure:

```
packages/
└── package-name/
    ├── src/
    ├── tests/
    ├── composer.json
    ├── README.md
    └── LICENSE
```

### Package Guidelines

- Each package should have a **clear, single purpose**
- Include **comprehensive tests**
- Add a **detailed README** with usage examples
- Follow **semantic versioning**
- Document all **public APIs**

## Testing Guidelines

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit --testsuite=Feature

# Run specific test file
./vendor/bin/phpunit tests/Feature/ModuleSystemTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Test Organization

- **Feature tests**: Test complete features end-to-end
- **Unit tests**: Test individual classes/methods in isolation
- **Integration tests**: Test interactions between components

### Test Best Practices

- Use **factories** for creating test data
- Use **database transactions** to keep tests isolated
- **Mock external services** to avoid network calls
- Test **edge cases** and error conditions
- Keep tests **fast** and **deterministic**

## Documentation

### Code Documentation

- Add **PHPDoc blocks** for all public methods
- Document **complex algorithms** with inline comments
- Include **usage examples** in docblocks for key classes
- Keep documentation **up-to-date** with code changes

### Example PHPDoc

```php
/**
 * Create a new workspace with the given attributes.
 *
 * This method handles workspace creation including:
 * - Validation of input data
 * - Creation of default settings
 * - Assignment of owner permissions
 *
 * @param  array  $attributes  Workspace attributes (name, slug, settings)
 * @return \Core\Mod\Tenant\Models\Workspace
 * @throws \Illuminate\Validation\ValidationException
 */
public function create(array $attributes): Workspace
{
    // Implementation
}
```

## Review Process

### What We Look For

- **Code quality**: Clean, readable, maintainable code
- **Tests**: Adequate test coverage for new code
- **Documentation**: Clear documentation for new features
- **Performance**: No significant performance regressions
- **Security**: No security vulnerabilities introduced

### Timeline

- Initial review typically within **1-3 business days**
- Follow-up reviews within **1 business day**
- Complex PRs may require additional review time

## License

By contributing to the Core PHP Framework, you agree that your contributions will be licensed under the **EUPL-1.2** license.

## Questions?

If you have questions about contributing, feel free to:

- Open a **GitHub Discussion**
- Create an **issue** labeled "question"
- Email **support@host.uk.com**

Thank you for contributing! 🎉
