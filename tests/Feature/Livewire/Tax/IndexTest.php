<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Models\TaxRate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Support\TaxRateCsv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'taxRate.viewAny', 'taxRate.create', 'taxRate.update', 'taxRate.delete' ] );
    $this->actingAs( makeUser() );
} );

function taxCsv( string $body ): UploadedFile
{
    return UploadedFile::fake()->createWithContent( 'rates.csv', $body );
}

it( 'renders classes, rates, and the manual provider', function (): void {
    TaxRate::factory()->create( [ 'country_code' => 'US', 'region_code' => 'NY', 'rate_ubps' => 88_750_000, 'label' => 'NY sales tax' ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( 'data-tax-provider="manual"' )
        ->assertDontSeeHtml( 'data-rates-unused' )
        ->assertSee( 'Reduced rate' )
        ->assertSee( 'NY sales tax' )
        ->assertSee( '8.875%' )
        ->assertSeeHtml( '<caption class="sr-only">Tax rates</caption>' );
} );

it( 'labels the rates unused when another provider is active', function (): void {
    config( [ 'artisanpack.ecommerce.tax.provider' => 'avalara' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-tax-provider="avalara"' )
        ->assertSeeHtml( 'data-rates-unused' )
        ->assertSee( 'These rates are not used.' );
} );

it( 'is denied without taxRate.viewAny', function (): void {
    Gate::define( 'ecommerce.taxRate.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'adds 8.375% for a US state inline', function (): void {
    Livewire::test( Index::class )
        ->call( 'createRate' )
        ->assertSeeHtml( 'data-new-rate' )
        ->set( 'rateForm.country_code', 'us' )
        ->set( 'rateForm.region_code', 'ny' )
        ->set( 'rateForm.rate_ubps', 83_750_000 )
        ->set( 'rateForm.label', 'Sales tax' )
        ->set( 'rateForm.is_shipping_taxable', true )
        ->call( 'saveRate' )
        ->assertHasNoErrors()
        ->assertSet( 'creatingRate', false )
        ->assertSee( '8.375%' );

    $rate = TaxRate::query()->sole();

    expect( $rate )
        ->tax_class_key->toBe( 'standard' )
        ->country_code->toBe( 'US' )
        ->region_code->toBe( 'NY' )
        ->rate_ubps->toBe( 83_750_000 )
        ->is_shipping_taxable->toBeTrue()
        ->is_compound->toBeFalse();
} );

it( 'prefills a new rate from the class and country filters', function (): void {
    TaxRate::factory()->create( [ 'tax_class_key' => 'reduced', 'country_code' => 'DE' ] );

    Livewire::test( Index::class )
        ->set( 'filters.class', 'reduced' )
        ->set( 'filters.country', 'DE' )
        ->call( 'createRate' )
        ->assertSet( 'rateForm.tax_class_key', 'reduced' )
        ->assertSet( 'rateForm.country_code', 'DE' );
} );

it( 'validates the rate', function (): void {
    Livewire::test( Index::class )
        ->call( 'createRate' )
        ->set( 'rateForm.tax_class_key', 'missing' )
        ->set( 'rateForm.country_code', 'XX' )
        ->set( 'rateForm.rate_ubps', 2_000_000_000 )
        ->set( 'rateForm.label', '' )
        ->set( 'rateForm.priority', 'high' )
        ->call( 'saveRate' )
        ->assertHasErrors( [
            'rateForm.tax_class_key' => 'exists',
            'rateForm.country_code'  => 'in',
            'rateForm.rate_ubps'     => 'max',
            'rateForm.label'         => 'required',
            'rateForm.priority'      => 'integer',
        ] );

    Livewire::test( Index::class )
        ->call( 'createRate' )
        ->set( 'rateForm.country_code', 'US' )
        ->set( 'rateForm.label', 'Sales tax' )
        ->set( 'rateForm.rate_ubps', null )
        ->call( 'saveRate' )
        ->assertHasErrors( [ 'rateForm.rate_ubps' => 'required' ] );

    expect( TaxRate::query()->count() )->toBe( 0 );
} );

it( 'edits a rate in its row and marks it compound', function (): void {
    $rate = TaxRate::factory()->create( [ 'country_code' => 'CA', 'region_code' => 'QC', 'label' => 'QST', 'priority' => 1 ] );

    Livewire::test( Index::class )
        ->call( 'editRate', $rate->id )
        ->assertSeeHtml( 'data-editing-rate="' . $rate->id . '"' )
        ->set( 'rateForm.is_compound', true )
        ->set( 'rateForm.rate_ubps', 99_750_000 )
        ->call( 'saveRate' )
        ->assertHasNoErrors()
        ->assertSet( 'editingRateId', null );

    expect( $rate->refresh() )->is_compound->toBeTrue()->rate_ubps->toBe( 99_750_000 )->label->toBe( 'QST' );
} );

it( 'filters rates by class and country', function (): void {
    TaxRate::factory()->create( [ 'tax_class_key' => 'standard', 'country_code' => 'DE', 'label' => 'German VAT' ] );
    TaxRate::factory()->create( [ 'tax_class_key' => 'reduced', 'country_code' => 'DE', 'label' => 'German reduced VAT' ] );
    TaxRate::factory()->create( [ 'tax_class_key' => 'standard', 'country_code' => 'FR', 'label' => 'French VAT' ] );

    Livewire::test( Index::class )
        ->set( 'filters.country', 'DE' )
        ->assertSee( 'German VAT' )->assertSee( 'German reduced VAT' )->assertDontSee( 'French VAT' )
        ->set( 'filters.class', 'reduced' )
        ->assertDontSee( 'German VAT' )
        ->assertSee( 'German reduced VAT' );
} );

it( 'switches rates off and deletes them in bulk', function (): void {
    $rates = TaxRate::factory()->count( 2 )->create();

    $component = Livewire::test( Index::class )
        ->set( 'selected', $rates->pluck( 'id' )->map( 'strval' )->all() )
        ->call( 'runBulkAction', 'deactivate' );

    expect( TaxRate::query()->where( 'is_active', true )->count() )->toBe( 0 );

    $component->call( 'confirmDeleteRate', $rates->first()->id )->assertSet( 'confirmingBulkAction', 'delete' );

    $token = $component->viewData( 'tableConfirmToken' );
    $component->call( 'confirmBulkAction', $token );

    expect( TaxRate::query()->pluck( 'id' )->all() )->toBe( [ $rates->last()->id ] );
} );

it( 'adds and relabels a tax class', function (): void {
    Livewire::test( Index::class )
        ->set( 'newClassKey', 'Books' )
        ->set( 'newClassLabel', 'Books' )
        ->call( 'createClass' )
        ->assertHasNoErrors()
        ->assertSet( 'newClassKey', '' );

    $class = TaxClass::query()->where( 'key', 'books' )->sole();

    Livewire::test( Index::class )
        ->call( 'editClass', $class->id )
        ->set( 'editingClassLabel', 'Printed books' )
        ->call( 'saveClass' )
        ->assertHasNoErrors();

    expect( $class->refresh() )->label->toBe( 'Printed books' )->key->toBe( 'books' );
} );

it( 'validates a new tax class', function (): void {
    Livewire::test( Index::class )
        ->set( 'newClassKey', 'standard' )
        ->set( 'newClassLabel', '' )
        ->call( 'createClass' )
        ->assertHasErrors( [ 'newClassKey' => 'unique', 'newClassLabel' => 'required' ] );

    Livewire::test( Index::class )
        ->set( 'newClassKey', 'bad key!' )
        ->set( 'newClassLabel', 'Bad' )
        ->call( 'createClass' )
        ->assertHasErrors( [ 'newClassKey' => 'regex' ] );
} );

it( 'asks the policy about the specific class before editing or deleting it', function (): void {
    $locked = TaxClass::query()->where( 'key', 'reduced' )->sole();
    $other  = TaxClass::query()->where( 'key', 'zero' )->sole();

    foreach ( [ 'update', 'delete' ] as $action ) {
        addFilter( 'ap.ecommerce.abilities.taxRate.' . $action, static fn ( bool $allowed, $user, $request, mixed $subject = null ): bool => $allowed && ! ( $subject instanceof TaxClass && $subject->is( $locked ) ), 10, 4 );
    }

    Livewire::test( Index::class )->call( 'editClass', $locked->id )->assertForbidden();
    Livewire::test( Index::class )->call( 'confirmDeleteClass', $locked->id )->assertForbidden();
    Livewire::test( Index::class )->call( 'editClass', $other->id )->assertSet( 'editingClassId', $other->id );

    removeAllFilters( 'ap.ecommerce.abilities.taxRate.update' );
    removeAllFilters( 'ap.ecommerce.abilities.taxRate.delete' );
} );

it( 'blocks deleting a class that rates or products use', function (): void {
    $class = TaxClass::query()->where( 'key', 'reduced' )->sole();
    TaxRate::factory()->create( [ 'tax_class_key' => 'reduced' ] );
    Product::factory()->create( [ 'tax_class_key' => 'reduced' ] );

    $component = Livewire::test( Index::class )
        ->call( 'confirmDeleteClass', $class->id )
        ->assertSeeHtml( 'data-class-blocked' )
        ->assertSee( '1 rate, 1 product' );

    $component->call( 'deleteClass', $component->viewData( 'deleteClassToken' ) );

    expect( TaxClass::query()->whereKey( $class->id )->exists() )->toBeTrue();
    expect( sentToasts( $component ) )->toContain( 'The tax class was not deleted.' );
} );

it( 'blocks deleting the default class', function (): void {
    $class = TaxClass::query()->where( 'key', 'standard' )->sole();

    $component = Livewire::test( Index::class )->call( 'confirmDeleteClass', $class->id );
    $component->call( 'deleteClass', $component->viewData( 'deleteClassToken' ) );

    expect( TaxClass::query()->whereKey( $class->id )->exists() )->toBeTrue();
} );

it( 'deletes an unused class after confirmation', function (): void {
    $class = TaxClass::query()->where( 'key', 'zero' )->sole();

    $component = Livewire::test( Index::class )
        ->call( 'confirmDeleteClass', $class->id )
        ->assertDontSeeHtml( 'data-class-blocked' );

    $component->call( 'deleteClass', $component->viewData( 'deleteClassToken' ) );

    expect( TaxClass::query()->whereKey( $class->id )->exists() )->toBeFalse();
} );

it( 'dry-runs a CSV import, then loads the valid rows', function (): void {
    $existing = TaxRate::factory()->create( [ 'country_code' => 'DE', 'region_code' => null, 'postal_pattern' => null, 'priority' => 0, 'rate_ubps' => 160_000_000, 'label' => 'MwSt.' ] );

    $csv = implode( "\n", [
        'tax_class_key,country_code,region_code,postal_pattern,rate_percent,label,is_compound,is_shipping_taxable,priority,is_active',
        'standard,DE,,,19,MwSt.,0,1,0,1',
        'standard,FR,,,20,TVA,0,1,0,1',
        'reduced,IT,,,10,IVA ridotta,no,no,,yes',
        'standard,XX,,,20,Nowhere,0,0,0,1',
        'luxury,ES,,,21,IVA,0,0,0,1',
        'standard,NL,,,abc,BTW,0,0,0,1',
    ] );

    $component = Livewire::test( Index::class )
        ->call( 'openImport' )
        ->set( 'ratesCsv', taxCsv( $csv ) )
        ->call( 'checkImport' )
        ->assertHasNoErrors()
        ->assertSet( 'importReport.create', 2 )
        ->assertSet( 'importReport.update', 1 )
        ->assertSet( 'importReport.invalid', 3 )
        ->assertSeeHtml( 'data-import-error="5"' )
        ->assertSee( 'There is no tax class "luxury".' );

    expect( TaxRate::query()->count() )->toBe( 1 );

    $component->call( 'applyImport', $component->viewData( 'importToken' ) )
        ->assertSet( 'importing', false );

    expect( TaxRate::query()->count() )->toBe( 3 );
    expect( $existing->refresh() )->rate_ubps->toBe( 190_000_000 )->is_shipping_taxable->toBeTrue();
    expect( TaxRate::query()->where( 'country_code', 'IT' )->sole() )
        ->tax_class_key->toBe( 'reduced' )
        ->rate_ubps->toBe( 100_000_000 )
        ->is_active->toBeTrue();
} );

it( 'refuses a CSV without the required columns', function (): void {
    Livewire::test( Index::class )
        ->call( 'openImport' )
        ->set( 'ratesCsv', taxCsv( "country_code,label\nUS,Tax" ) )
        ->call( 'checkImport' )
        ->assertHasErrors( [ 'ratesCsv' ] )
        ->assertSet( 'importReport', null );

    Livewire::test( Index::class )
        ->call( 'openImport' )
        ->call( 'checkImport' )
        ->assertHasErrors( [ 'ratesCsv' => 'required' ] );
} );

it( 'refuses import rows that update rates without taxRate.update', function (): void {
    Gate::define( 'ecommerce.taxRate.update', static fn (): bool => false );
    TaxRate::factory()->create( [ 'country_code' => 'DE', 'region_code' => null, 'postal_pattern' => null, 'priority' => 0 ] );

    Livewire::test( Index::class )
        ->call( 'openImport' )
        ->set( 'ratesCsv', taxCsv( "tax_class_key,country_code,rate_percent,label\nstandard,DE,19,MwSt." ) )
        ->call( 'checkImport' )
        ->assertSet( 'importReport.update', 0 )
        ->assertSet( 'importReport.invalid', 1 );
} );

it( 'exports rates in the import format', function (): void {
    TaxRate::factory()->create( [ 'country_code' => 'US', 'region_code' => 'NY', 'rate_ubps' => 88_750_000, 'label' => 'NY sales tax' ] );

    Livewire::test( Index::class )
        ->call( 'exportRates' )
        ->assertFileDownloaded();

    $csv = TaxRateCsv::export( TaxRate::query()->orderBy( 'id' ) );

    expect( $csv )->toContain( 'tax_class_key,country_code,region_code' )->toContain( 'standard,US,NY,,8.875,"NY sales tax"' );
} );

it( 'cuts an export at tables.export_max_rows and says so', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.tables.export_max_rows', 2 );
    TaxRate::factory()->count( 3 )->sequence( [ 'country_code' => 'US' ], [ 'country_code' => 'CA' ], [ 'country_code' => 'DE' ] )->create();

    $component = Livewire::test( Index::class )->call( 'exportRates' );
    $download  = $component->effects['download'] ?? null;
    $lines     = array_values( array_filter( explode( "\n", trim( base64_decode( (string) $download['content'] ) ) ) ) );

    expect( $lines )->toHaveCount( 3 )
        ->and( sentToasts( $component ) )->toContain( 'The export was cut short.' );
} );

it( 'hides write controls without the abilities', function (): void {
    Gate::define( 'ecommerce.taxRate.create', static fn (): bool => false );
    Gate::define( 'ecommerce.taxRate.update', static fn (): bool => false );
    Gate::define( 'ecommerce.taxRate.delete', static fn (): bool => false );
    TaxRate::factory()->create();

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-new-class' )
        ->assertDontSeeHtml( 'wire:click="createRate"' )
        ->assertDontSeeHtml( 'wire:click="openImport"' )
        ->assertDontSeeHtml( 'editRate(' )
        ->call( 'createRate' )
        ->assertForbidden();
} );

it( 'does not recreate a rate deleted while it was being edited', function (): void {
    $rate = TaxRate::factory()->create( [ 'label' => 'Gone soon' ] );

    $component = Livewire::test( Index::class )->call( 'editRate', $rate->id );

    $rate->delete();

    $component->call( 'saveRate' )->assertSet( 'editingRateId', null );

    expect( TaxRate::query()->count() )->toBe( 0 );
    expect( sentToasts( $component ) )->toContain( 'This tax rate was deleted while you were editing it.' );
} );

it( 'matches import rows to existing rates whose region is empty or null', function (): void {
    TaxRate::factory()->create( [ 'country_code' => 'FR', 'region_code' => '', 'postal_pattern' => null, 'priority' => 0, 'label' => 'TVA' ] );

    Livewire::test( Index::class )
        ->call( 'openImport' )
        ->set( 'ratesCsv', taxCsv( "tax_class_key,country_code,rate_percent,label\nstandard,fr,20,TVA" ) )
        ->call( 'checkImport' )
        ->assertSet( 'importReport.update', 1 )
        ->assertSet( 'importReport.create', 0 )
        ->assertSee( 'Import 1 rate' );
} );

it( 'warns an editor without taxRate.create when their rate was deleted', function (): void {
    Gate::define( 'ecommerce.taxRate.create', static fn (): bool => false );
    $rate = TaxRate::factory()->create();

    $component = Livewire::test( Index::class )->call( 'editRate', $rate->id );

    $rate->delete();

    $component->call( 'saveRate' )->assertOk()->assertSet( 'editingRateId', null );

    expect( sentToasts( $component ) )->toContain( 'This tax rate was deleted while you were editing it.' );
} );
