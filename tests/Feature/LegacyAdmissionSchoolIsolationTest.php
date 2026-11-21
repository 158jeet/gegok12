<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression contract for the legacy admissions controller's tenant boundary.
 *
 * These checks deliberately inspect each public endpoint independently so a
 * future refactor cannot accidentally remove the school predicate from one
 * action while leaving the others protected. Runtime multi-school fixtures
 * remain a separate acceptance item where the legacy schema permits them.
 */
class LegacyAdmissionSchoolIsolationTest extends TestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(base_path('app/Http/Controllers/Admin/AdmissionController.php'));
    }

    private function methodSource(string $method): string
    {
        $source = $this->controllerSource();
        $pattern = '/public function ' . preg_quote($method, '/') . '\\s*\\([^)]*\\)\\s*\\{(.*?)(?=\\n    public function |\\n})/s';

        $this->assertSame(1, preg_match($pattern, $source, $matches), "Could not locate {$method}() in AdmissionController.");

        return $matches[1];
    }

    public function test_list_is_scoped_to_authenticated_school_and_active_academic_year(): void
    {
        $method = $this->methodSource('admissionlist');

        $this->assertStringContainsString("Auth::user()->school_id", $method);
        $this->assertStringContainsString("where('school_id'", $method);
        $this->assertStringContainsString("where('academic_year_id'", $method);
        $this->assertStringContainsString("whereIn('application_status', ['Draft', 'Pending'])", $method);
    }

    public function test_each_record_endpoint_scopes_lookup_before_returning_or_mutating_record(): void
    {
        foreach (['show', 'edit', 'update', 'destroy'] as $action) {
            $method = $this->methodSource($action);

            $this->assertStringContainsString("where('school_id', Auth::user()->school_id)", $method, "{$action}() must constrain the record to the authenticated school.");
            $this->assertStringContainsString('firstOrFail()', $method, "{$action}() must fail closed when the record is outside the school.");
        }
    }

    public function test_show_only_offers_sections_from_the_authenticated_school(): void
    {
        $method = $this->methodSource('show');

        $this->assertStringContainsString("StandardLink::where('school_id', Auth::user()->school_id)", $method);
        $this->assertStringContainsString("where('standard_id', \\$admission->standard_id)", $method);
    }

    public function test_legacy_admission_controller_has_no_debug_dump_or_exception_detail_response(): void
    {
        $source = $this->controllerSource();

        $this->assertDoesNotMatchRegularExpression('/\\b(dd|dump|var_dump)\\s*\\(/', $source);
        $this->assertStringNotContainsString('$e->getMessage()', $source);
        $this->assertStringNotContainsString('exception' . " => " . '$e->getMessage()', $source);
    }
}
