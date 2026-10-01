<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the package, the engine, and Livewire in Testbench.
| Unit tests run without a Laravel application.
|
*/

pest()->extend( Tests\TestCase::class )
    ->in( 'Feature' );
