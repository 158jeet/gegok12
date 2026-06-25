#!/bin/bash

# Quick Documentation Viewer
# Opens the API documentation in your browser

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCS_FILE="${PROJECT_ROOT}/docs-viewer.html"

echo "🚀 Opening GegoK12 API Documentation..."
echo ""
echo "📂 Documentation file: ${DOCS_FILE}"
echo ""

# Try to open in default browser
if command -v open &> /dev/null; then
    # macOS
    open "${DOCS_FILE}"
    echo "✅ Opened in default browser (macOS)"
elif command -v xdg-open &> /dev/null; then
    # Linux
    xdg-open "${DOCS_FILE}"
    echo "✅ Opened in default browser (Linux)"
elif command -v start &> /dev/null; then
    # Windows
    start "${DOCS_FILE}"
    echo "✅ Opened in default browser (Windows)"
else
    echo "⚠️  Could not open browser automatically"
    echo "📖 Please open manually: ${DOCS_FILE}"
fi

echo ""
echo "Or access via local server:"
echo "🌐 http://localhost:8080/docs-viewer.html"
