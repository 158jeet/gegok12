#!/bin/bash

# Testing Implementation Verification Script
# Verifies that all testing files have been properly created

echo "🔍 GegoK12 Testing Implementation Verification"
echo "=============================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counter
total=0
passed=0

# Function to check file
check_file() {
    local file=$1
    local description=$2

    total=$((total + 1))

    if [ -f "$file" ]; then
        # Check PHP syntax if it's a PHP file
        if [[ "$file" == *.php ]]; then
            if php -l "$file" > /dev/null 2>&1; then
                echo -e "${GREEN}✓${NC} $description"
                passed=$((passed + 1))
            else
                echo -e "${RED}✗${NC} $description (Syntax Error)"
            fi
        else
            echo -e "${GREEN}✓${NC} $description"
            passed=$((passed + 1))
        fi
    else
        echo -e "${RED}✗${NC} $description (File Not Found)"
    fi
}

# Function to check directory
check_dir() {
    local dir=$1
    local description=$2

    total=$((total + 1))

    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓${NC} $description"
        passed=$((passed + 1))
    else
        echo -e "${RED}✗${NC} $description (Directory Not Found)"
    fi
}

echo "📂 Checking Directories..."
check_dir "tests" "Tests directory"
check_dir "tests/Feature" "Tests/Feature directory"
check_dir "tests/Unit" "Tests/Unit directory"
check_dir "database/factories" "Factories directory"
echo ""

echo "🧪 Checking Test Files..."
check_file "tests/TestCase.php" "Base TestCase class"
check_file "tests/Feature/AuthenticationTest.php" "Core Authentication Tests (5 roles)"
check_file "tests/Feature/AuthenticationAdvancedTest.php" "Advanced Authentication Tests"
check_file "tests/Feature/AuthenticationExamplesTest.php" "Authentication Examples & Patterns"
echo ""

echo "🏭 Checking Factory Files..."
check_file "database/factories/UserFactory.php" "User Factory (with 8 role states)"
check_file "database/factories/SchoolFactory.php" "School Factory"
check_file "database/factories/CountryFactory.php" "Country Factory"
check_file "database/factories/StateFactory.php" "State Factory"
check_file "database/factories/CityFactory.php" "City Factory"
echo ""

echo "📚 Checking Documentation Files..."
check_file "_co-pilot_docs/README_COMPLETE_IMPLEMENTATION.md" "Complete Implementation Summary"
check_file "_co-pilot_docs/TESTING_IMPLEMENTATION.md" "Testing Implementation Guide"
check_file "_co-pilot_docs/TESTING_QUICK_REFERENCE.md" "Quick Reference Guide"
check_file "_co-pilot_docs/TESTING_INDEX.md" "Documentation Index"
check_file "tests/README.md" "Tests Directory README"
echo ""

echo "🔧 Checking Configuration..."
check_file "phpunit.xml" "PHPUnit Configuration"
echo ""

# Summary
echo "=============================================="
echo "📊 Verification Summary"
echo "=============================================="
echo -e "Total Checks: ${total}"
echo -e "Passed: ${GREEN}${passed}${NC}"
echo -e "Failed: ${RED}$((total - passed))${NC}"
echo ""

if [ $passed -eq $total ]; then
    echo -e "${GREEN}✓ All files verified successfully!${NC}"
    echo ""
    echo "📝 Next Steps:"
    echo "  1. Run tests: php artisan test"
    echo "  2. View results"
    echo "  3. Check documentation in _co-pilot_docs/:"
    echo "     - README_COMPLETE_IMPLEMENTATION.md (Overview)"
    echo "     - TESTING_QUICK_REFERENCE.md (Quick Start)"
    echo "     - TESTING_IMPLEMENTATION.md (Details)"
    echo ""
    exit 0
else
    echo -e "${RED}✗ Some files are missing or have errors!${NC}"
    echo "Please check the output above for details."
    exit 1
fi
