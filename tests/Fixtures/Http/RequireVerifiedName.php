<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for a host's `verified` / 2FA middleware: only users whose name
 * starts with "Verified" get through.
 */
class RequireVerifiedName
{
    public function handle( Request $request, Closure $next ): Response
    {
        abort_unless( str_starts_with( (string) $request->user()?->name, 'Verified' ), 403, 'Not verified.' );

        return $next( $request );
    }
}
