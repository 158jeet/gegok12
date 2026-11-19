# Tagore Academic Structure

The Tagore layer keeps GegoK12 as the source of truth for schools, academic years, and class standards. Tagore adds institution-specific streams and class sections.

Hierarchy: Tagore Group -> Institution -> Academic Year -> GegoK12 Standard -> Section -> Student enrollment.

Streams are institution-scoped. Sections are scoped to an institution, academic year, GegoK12 standard, and optional stream. Cross-institution academic-year, class, and stream assignments are rejected by the controller.
