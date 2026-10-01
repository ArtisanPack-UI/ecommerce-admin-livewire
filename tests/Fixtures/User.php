<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A host-app user backed by Laravel's default `users` table.
 */
class User extends Authenticatable
{
    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var string
     */
    protected $table = 'users';
}
