<?php

use Illuminate\Container\Container;
use Surgiie\Console\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//     return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
// simulate the helpers used in code
function app($key = null)
{
    $container = new Container;
    if (! is_null($key)) {
        return $container->make($key);
    }

    return $container;
}

function base_path(string $path = '')
{
    $base = realpath(__DIR__.'/../');

    return $base.$path;
}

function test_mock_file_path(string $path = '')
{
    $testsPath = realpath(__DIR__);

    return rtrim("$testsPath/mock/".trim($path, '/'), '/');
}

function storage_path(string $path = '')
{
    $path = test_mock_file_path('storage/'.$path);

    @mkdir(dirname($path), recursive: true);

    return $path;
}
