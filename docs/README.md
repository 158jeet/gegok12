# 📚 GegoK12 Documentation Folder

Welcome! This folder contains all documentation, setup guides, and scripts for viewing and generating API documentation.

## 📁 Files & Structure

```
docs/
├── README.md                      ← This file
├── DOCS_README.md                 ← Main setup guide (START HERE)
├── DOCUMENTATION_SETUP.md         ← Detailed setup options
├── DOCUMENTATION_INDEX.md         ← Complete API index & reference
├── docs-viewer.html               ← Interactive documentation viewer
├── phpdoc.xml                     ← PHPDocumentor configuration
├── view-docs.sh                   ← Quick script to open docs in browser
└── generate-docs.sh               ← Script to generate full documentation
```

## 🚀 Quick Start

### 1. View Interactive Documentation

```bash
./view-docs.sh
```

This opens the interactive API documentation in your default browser.

### 2. Generate Full PHPDocumentor Documentation

```bash
./generate-docs.sh
```

This generates comprehensive HTML documentation from phpDoc comments and opens it in your browser.

## 📖 Documentation Files

| File                       | Purpose                                                    |
| -------------------------- | ---------------------------------------------------------- |
| **DOCS_README.md**         | 📚 Main guide - start here for complete setup instructions |
| **DOCUMENTATION_SETUP.md** | 🔧 Multiple setup options (PHAR, Docker, VS Code, etc.)    |
| **DOCUMENTATION_INDEX.md** | 📑 Complete API reference and index                        |
| **docs-viewer.html**       | 🌐 Browser-based documentation viewer                      |

## 🎯 What You Can Do

### View API Documentation

```bash
# Open interactive docs in browser
./view-docs.sh

# Or access directly
open docs-viewer.html
```

### Generate Fresh Documentation

```bash
# Generates from phpDoc comments in /app
./generate-docs.sh

# Output saved to: storage/phpdoc/index.html
```

### View Generated Docs

```bash
cd storage/phpdoc
python3 -m http.server 8000
# Visit: http://localhost:8000
```

## 📚 What's Documented

### Traits

-   ✅ **MSG91** - SMS gateway integration
-   ✅ **AuthenticatesUsers** - Authentication logic

### Controllers

-   ✅ **LoginController** - Authentication endpoints

### Middleware

-   ✅ **AdminAccountant** - Access control

### Models

-   ✅ **User** - User management
-   ✅ **School** - School management

## 🔧 Configuration

### PHPDocumentor Settings

Edit `phpdoc.xml` to customize:

-   Source directory (`./app`)
-   Output location (`./storage/phpdoc`)
-   Documentation template
-   Excluded folders

### Shell Scripts

Make scripts executable:

```bash
chmod +x view-docs.sh
chmod +x generate-docs.sh
```

## 💡 Tips

1. **Update documentation** when you change code
2. **Generate docs before code reviews** for easy reference
3. **Share generated docs** with your team
4. **Keep phpDoc blocks current** with your implementation
5. **Use standard tags**: `@param`, `@return`, `@throws`

## 🆘 Troubleshooting

**Q: "Permission denied" when running scripts?**

```bash
chmod +x *.sh
```

**Q: "Can't see generated docs?"**

```bash
# Check if files were generated
ls -la storage/phpdoc/

# Serve them locally
python3 -m http.server 8000 --directory storage/phpdoc
```

**Q: "How to clean and regenerate?"**

```bash
rm -rf storage/phpdoc/*
./generate-docs.sh
```

## 🔗 Resources

-   [DOCS_README.md](DOCS_README.md) - Main setup guide
-   [DOCUMENTATION_SETUP.md](DOCUMENTATION_SETUP.md) - Advanced options
-   [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md) - API reference
-   [PHPDocumentor Docs](https://docs.phpdoc.org/) - Official documentation
-   [PSR-5 Standard](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md) - PHPDoc standard

## 📊 Documentation Commands Summary

```bash
# View documentation
./view-docs.sh                    # Interactive viewer
open docs-viewer.html             # Direct HTML file

# Generate documentation
./generate-docs.sh                # Auto-generate from phpDoc

# Serve generated docs
cd storage/phpdoc
python3 -m http.server 8000       # HTTP server on port 8000
php -S localhost:8000             # PHP server alternative
```

## 🎓 Documentation Standards

All code in this project follows these standards:

```php
/**
 * Brief description
 *
 * Longer explanation if needed. Can span multiple lines.
 *
 * @param string $param Parameter description
 * @param int $id Entity identifier
 * @return string Result description
 *
 * @throws Exception When something fails
 */
public function methodName($param, $id)
{
    //...
}
```

## ✅ Status

-   ✅ Documentation system fully configured
-   ✅ Interactive viewer ready
-   ✅ Generation scripts ready
-   ✅ Comprehensive guides included
-   ✅ Ready for team use

---

**Version**: 1.1.1  
**Last Updated**: December 11, 2025  
**Status**: Production Ready

For more details, see [DOCS_README.md](DOCS_README.md)
