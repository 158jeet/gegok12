<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */

namespace Tests;

use IlluminateFoundationTestingTestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase-based feature tests need the same baseline fixtures as CI.
     * This keeps seeded users, schools, academic years, and ERP fixtures available
     * after Laravel refreshes the database between tests.
     */
    protected $seed = true;
}
