# GegoK12 API Documentation Setup

This guide provides multiple ways to generate and view API documentation from your PHP code's phpDoc comments.

## Option 1: Using PHPDocumentor PHAR (Recommended)

### Step 1: Download PHPDocumentor

```bash
curl -L https://phpdoc.org/phpdoc.phar -o phpdoc.phar
chmod +x phpdoc.phar
```

### Step 2: Generate Documentation

```bash
./phpdoc.phar -d ./app -t ./storage/phpdoc --config=phpdoc.xml
```

### Step 3: View Documentation

```bash
# Open in browser (macOS)
open ./storage/phpdoc/index.html

# Or serve locally
python3 -m http.server 8000 --directory ./storage/phpdoc
# Then visit http://localhost:8000
```

## Option 2: Using VS Code Extension

Install the "PHP Docblocks" extension in VS Code for inline documentation viewing.

Extensions recommended:

-   **PHP Docblocks** (by Neil Brayfield)
-   **PHP Documentation** (by Eric Binnion)

## Option 3: Using Docker

```bash
# Generate docs using Docker
docker run --rm -v $(pwd):/data phpdoc/phpdoc:3.5 \
  -d /data/app \
  -t /data/storage/phpdoc \
  --config=/data/phpdoc.xml
```

## Option 4: Browser-based Documentation Viewer

A simple HTTP server to view generated docs:

```bash
# Using Python
cd ./storage/phpdoc && python3 -m http.server 8000

# Using PHP built-in server
php -S localhost:8000

# Visit: http://localhost:8000
```

## Available Documentation

### Traits

-   **MSG91.php** - SMS gateway integration (sendSMS, getOTP, emergencySMS)
-   **AuthenticatesUsers.php** - Authentication logic with custom validators
-   **Other traits** - [Generate docs to see all]

### Controllers

-   **LoginController.php** - Authentication endpoints
-   **[More controllers]** - [Generate docs to see all]

### Models

-   **User.php** - User management
-   **School.php** - School management
-   **[More models]** - [Generate docs to see all]

## Configuration Files

### phpdoc.xml

Controls documentation generation:

-   `<paths><output>` - Where to save generated docs
-   `<version><folder>` - Which folders to document
-   `<exclude>` - Folders to skip
-   `<template>` - Documentation template style

### generate-docs.sh

Automated script to generate and open documentation (macOS/Linux)

```bash
./generate-docs.sh
```

## Documentation Standards Used

All code follows these documentation standards:

### Class Documentation

```php
/**
 * ClassName Description
 *
 * Detailed explanation of what this class does,
 * its responsibilities, and usage examples.
 *
 * @author Your Name
 * @version 1.0
 */
class ClassName
{
    //...
}
```

### Method Documentation

```php
/**
 * Method description
 *
 * Longer explanation if needed.
 *
 * @param string $param Parameter description
 * @param int $id Entity ID
 * @return string The result description
 *
 * @throws Exception When something bad happens
 */
public function methodName($param, $id)
{
    //...
}
```

### Property Documentation

```php
/**
 * @var string The user's email address
 */
private $email;
```

## Viewing Documentation Locally

### Quick Start (No Installation)

```bash
# Navigate to docs folder
cd storage/phpdoc

# Serve on localhost (Python 3)
python3 -m http.server 8000

# Serve on localhost (PHP)
php -S localhost:8000

# Visit http://localhost:8000 in your browser
```

### Using Live Server Extension (VS Code)

1. Install "Live Server" extension
2. Right-click on `storage/phpdoc/index.html`
3. Select "Open with Live Server"

## CI/CD Integration

Add to your pipeline to generate docs on every commit:

```yaml
# Example for GitHub Actions
- name: Generate Documentation
  run: |
      ./phpdoc.phar -d ./app -t ./docs --config=phpdoc.xml

- name: Deploy Docs
  run: |
      # Deploy to GitHub Pages, AWS S3, etc.
```

## Troubleshooting

**Issue:** "phpdoc: command not found"

-   Solution: Use `./phpdoc.phar` (with .phar extension) or ensure it's in PATH

**Issue:** Empty documentation generated

-   Solution: Check that your code has phpDoc comments starting with `/**`

**Issue:** Missing classes/methods in docs

-   Solution: Verify they're not in excluded folders in phpdoc.xml

**Issue:** Can't open generated docs

-   Solution: Use a simple HTTP server instead of opening as file:// URL

## Next Steps

1. ✅ Run: `./generate-docs.sh` to generate documentation
2. ✅ Open: `./storage/phpdoc/index.html` in your browser
3. ✅ Review: Check the generated documentation for your classes
4. ✅ Share: Host the docs folder on a web server for team access

---

For more information: [PHPDocumentor Official Documentation](https://docs.phpdoc.org/)
