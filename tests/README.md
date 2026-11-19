# Authentication Tests Documentation

## Overview
This directory contains automated tests for user authentication and login functionality across different user roles in the GegoK12 application.

## Test Structure

### Directory Layout
```
tests/
├── Feature/
│   └── AuthenticationTest.php    # Feature tests for user login scenarios
├── Unit/                          # Unit tests directory
└── TestCase.php                   # Base test case class
```

## Available Tests

### AuthenticationTest.php

The authentication test suite covers the following login scenarios:

#### 1. **School Admin Login** (`test_school_admin_can_login`)
- Tests that a school administrator can successfully log in
- Verifies authentication state is set correctly
- Redirects to `/admin/dashboard`

#### 2. **School Librarian Login** (`test_school_librarian_can_login`)
- Tests that a school librarian can successfully log in
- Verifies authentication state is set correctly
- Redirects to `/library/dashboard`

#### 3. **Student Login** (`test_student_can_login`)
- Tests that a student can successfully log in
- Verifies authentication state is set correctly
- Redirects to `/student/dashboard`

#### 4. **Teacher Login** (`test_teacher_can_login`)
- Tests that a teacher can successfully log in
- Verifies authentication state is set correctly
- Redirects to `/teacher/dashboard`

#### 5. **Accountant Login** (`test_accountant_can_login`)
- Tests that an accountant can successfully log in
- Verifies authentication state is set correctly
- Redirects to `/accountant/dashboard`

#### 6. **Invalid Credentials** (`test_login_fails_with_invalid_credentials`)
- Tests that login fails with wrong password
- Verifies session errors are set
- Confirms user is not authenticated

#### 7. **Non-existent User** (`test_login_fails_with_non_existent_user`)
- Tests that login fails for non-existent users
- Verifies session errors are set
- Confirms user is not authenticated

## Factory Classes

### UserFactory
Extended with state methods for creating specific user types:

```php
// Create a school admin
User::factory()->schoolAdmin()->create();

// Create a librarian
User::factory()->librarian()->create();

// Create a student
User::factory()->student()->create();

// Create a teacher
User::factory()->teacher()->create();

// Create an accountant
User::factory()->accountant()->create();

// Create a parent
User::factory()->parent()->create();

// Create a receptionist
User::factory()->receptionist()->create();

// Create a stock keeper
User::factory()->stockKeeper()->create();
```

### SchoolFactory
Creates a school with all necessary relationships:

```php
// Create a school
$school = School::factory()->create();

// Create a school with custom attributes
$school = School::factory()->create([
    'name' => 'Test School',
    'email' => 'school@example.com',
]);
```

### Location Factories
Supporting factories for creating countries, states, and cities:

- **CountryFactory**: Creates country records
- **StateFactory**: Creates state/province records
- **CityFactory**: Creates city records

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Only Authentication Tests
```bash
php artisan test tests/Feature/AuthenticationTest.php
```

### Run Specific Test
```bash
php artisan test tests/Feature/AuthenticationTest.php --filter=test_school_admin_can_login
```

### Run Tests with Coverage
```bash
php artisan test --coverage
```

### Run Tests in Parallel
```bash
php artisan test --parallel
```

## Test Database Configuration

Tests use a separate testing database. Configure in `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Or use a dedicated test database:
```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="gegok12_test"/>
```

## Key Features

### RefreshDatabase Trait
- Automatically refreshes the database before each test
- Ensures tests are isolated and don't affect each other
- Runs migrations automatically

### Test Fixtures
- Uses factories to create test data
- Schools, users, and all related records are automatically created
- Data is cleaned up after each test

### Authentication Testing
- Uses Laravel's built-in authentication testing helpers
- `assertAuthenticatedAs()`: Confirms user is authenticated
- `assertGuest()`: Confirms user is not authenticated
- `assertRedirect()`: Verifies redirect location

## Best Practices

### 1. Test Data Isolation
Each test creates its own data using factories, ensuring tests don't interfere with each other.

### 2. Descriptive Test Names
Test names follow the pattern: `test_[scenario]_[expected_result]`

### 3. Arrange-Act-Assert Pattern
Tests follow the AAA pattern:
- **Arrange**: Set up test data
- **Act**: Perform the action being tested
- **Assert**: Verify the results

### 4. Factory Usage
Always use factories for creating test data:
```php
// Good
$admin = User::factory()->schoolAdmin()->for($school)->create();

// Avoid
$admin = new User();
$admin->fill([...]);
$admin->save();
```

## Adding New Tests

### Template for New Tests
```php
public function test_new_scenario(): void
{
    // Arrange: Create test data
    $school = School::factory()->create();
    $user = User::factory()
        ->userType()  // e.g., schoolAdmin(), teacher()
        ->for($school)
        ->create();

    // Act: Perform the action
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    // Assert: Verify results
    $response->assertRedirect('/expected/dashboard');
    $this->assertAuthenticatedAs($user);
}
```

## Debugging Tests

### Enable SQL Query Logging
```php
public function setUp(): void
{
    parent::setUp();
    DB::listen(function($query) {
        \Log::info($query->sql);
    });
}
```

### Use dd() for Debugging
```php
$response->dd();  // Dump response and die
```

### Test Individual Assertions
Break down complex assertions to debug failures:
```php
$this->assertTrue($user->is_authenticated);
$this->assertEquals('admin@school.com', $user->email);
```

## Common Issues and Solutions

### Issue: Tests Fail with "Target class does not exist"
**Solution**: Ensure all model namespaces are correct and models are imported.

### Issue: "SQLSTATE[HY000]: General error: 1 no such table"
**Solution**: Ensure migrations have run in test environment.

### Issue: Tests Timeout
**Solution**: Check for infinite loops or missing database indexes. Use `--timeout` flag.

## Dependencies

- **Laravel Framework**: ^12.0
- **PHP**: ^8.4
- **PHPUnit**: Latest version included with Laravel

## Related Documentation

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Laravel Database Testing](https://laravel.com/docs/database-testing)
- [Laravel Factories](https://laravel.com/docs/eloquent-factories)

## Contributing

When adding new tests:
1. Follow existing naming conventions
2. Use factories for data creation
3. Include descriptive test names
4. Add documentation for new test scenarios
5. Ensure tests are isolated and repeatable

## License

SPDX-License-Identifier: MIT
(c) 2025 GegoSoft Technologies and GegoK12 Contributors
