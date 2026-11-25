<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests: full Laravel app + fresh DB wiped before every test.
| Unit tests: plain TestCase only - no DB, no HTTP, no app boot.
|--------------------------------------------------------------------------
*/

// Every file inside tests/Feature/ automatically gets Laravel TestCase
// AND RefreshDatabase - no need to repeat this in every test file.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Every file inside tests/Unit/ gets only the base TestCase.
// No database. No HTTP. Pure logic only.
pest()->extend(TestCase::class)
    ->in('Unit');
