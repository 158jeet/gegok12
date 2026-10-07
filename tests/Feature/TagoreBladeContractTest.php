<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TagoreBladeContractTest extends TestCase
{
    public function test_tagore_blade_views_have_safe_form_and_route_contracts(): void
    {
        $files = File::allFiles(resource_path('views/tagore'));

        $this->assertNotEmpty($files, 'Expected Tagore Blade views to exist.');

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getPathname());

            preg_match_all(
                '/<form\\b(?<attrs>[^>]*)>(?<body>[\\s\\S]*?)<\\/form>/i',
                $contents,
                $forms,
                PREG_SET_ORDER
            );

            foreach ($forms as $form) {
                $method = '';
                if (preg_match('/\\bmethod\\s*=\\s*["\\\']?(post|put|patch|delete)["\\\']?/i', $form['attrs'], $methodMatch)) {
                    $method = strtolower($methodMatch[1]);
                }

                if ($method !== '') {
                    $this->assertStringContainsString(
                        '@csrf',
                        $form['body'],
                        "State-changing form in {$file->getFilename()} must contain @csrf."
                    );
                }
            }

            preg_match_all(
                '/route\\(\\s*["\\\']([^"\\\']+)["\\\']/',
                $contents,
                $routeMatches
            );

            foreach (array_unique($routeMatches[1]) as $routeName) {
                $this->assertNotNull(
                    app('router')->getRoutes()->getByName($routeName),
                    "Blade view {$file->getFilename()} references missing route {$routeName}."
                );
            }

            preg_match_all(
                '/\\bid\\s*=\\s*["\\\']([^"\\\']+)["\\\']/i',
                $contents,
                $idMatches
            );

            $counts = array_count_values($idMatches[1]);
            foreach ($counts as $id => $count) {
                $this->assertSame(
                    1,
                    $count,
                    "Blade view {$file->getFilename()} contains duplicate id={$id}."
                );
            }
        }
    }
}
