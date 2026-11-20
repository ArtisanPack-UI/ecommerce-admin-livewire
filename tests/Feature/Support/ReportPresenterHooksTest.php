<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.reports.topProducts.table' );
    removeAllFilters( 'ap.ecommerceAdminLivewire.reports.top-products.table' );
    removeAllFilters( 'ap.ecommerceAdminLivewire.reports.sales.table' );
} );

it( 'filters a report table under the camelCase report name (L-H1)', function (): void {
    addFilter( 'ap.ecommerceAdminLivewire.reports.topProducts.table', static function ( array $table ): array {
        $table['seen'][] = 'camel';

        return $table;
    } );

    expect( ReportPresenter::table( 'top-products', [ 'rows' => [] ] )['seen'] ?? [] )->toBe( [ 'camel' ] );
} );

it( 'still runs the deprecated kebab-case report hook after the camelCase one (L-H1)', function (): void {
    addFilter( 'ap.ecommerceAdminLivewire.reports.top-products.table', static function ( array $table ): array {
        $table['seen'][] = 'kebab';

        return $table;
    } );
    addFilter( 'ap.ecommerceAdminLivewire.reports.topProducts.table', static function ( array $table ): array {
        $table['seen'][] = 'camel';

        return $table;
    } );

    expect( ReportPresenter::table( 'top-products', [ 'rows' => [] ] )['seen'] ?? [] )->toBe( [ 'camel', 'kebab' ] );
} );

it( 'runs a single-word report hook once (L-H1)', function (): void {
    addFilter( 'ap.ecommerceAdminLivewire.reports.sales.table', static function ( array $table ): array {
        $table['seen'][] = 'sales';

        return $table;
    } );

    expect( ReportPresenter::table( 'sales', [ 'rows' => [] ] )['seen'] ?? [] )->toBe( [ 'sales' ] );
} );
