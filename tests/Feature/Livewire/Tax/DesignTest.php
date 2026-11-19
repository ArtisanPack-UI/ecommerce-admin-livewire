<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ShippingMethod;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Models\TaxRate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax\Index;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'taxRate.viewAny', 'taxRate.create', 'taxRate.update', 'taxRate.delete' ] );
    $this->actingAs( makeUser() );
} );

it( 'names the built-in provider in an info alert and leaves no DaisyUI classes', function (): void {
    TaxRate::factory()->create( [ 'country_code' => 'US', 'region_code' => 'NY', 'postal_pattern' => '10001...10099', 'rate_ubps' => 45_000_000, 'label' => 'NYC tax' ] );

    $component = Livewire::test( Index::class )
        ->assertSee( 'Tax classes and the rates checkout charges.' )
        ->assertSeeHtml( 'data-color="info"' )
        ->assertSee( 'Checkout uses the active rates below.' )
        ->assertSeeHtml( 'id="tax-classes-heading" data-scope="card" data-part="title"' )
        ->assertSeeHtml( 'id="tax-rates-heading" data-scope="card" data-part="title"' );

    expect( $component->html() )->not->toMatch( '/class="[^"]*(?<![\w-])(btn|badge-|rounded-box|border-base-300|divide-base-300|text-sm|text-lg|opacity-75|flex|grow|gap-\d|font-semibold|table-sm|text-error|input-sm|select-sm|text-end)\b/' );
} );

it( 'warns plainly that the rates are not used under another provider', function (): void {
    config( [ 'artisanpack.ecommerce.tax.provider' => 'avalara' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-color="warning"' )
        ->assertSee( 'These rates are not used.' )
        ->assertSee( 'so checkout ignores the rates on this page. Keep them as a fallback, or switch the provider in Settings.' );
} );

it( 'shows what uses each class, zero counts included', function (): void {
    TaxRate::factory()->count( 2 )->create( [ 'tax_class_key' => 'reduced' ] );
    Product::factory()->create( [ 'tax_class_key' => 'reduced' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( '<bdi dir="ltr" class="ec-mono">reduced</bdi>' )
        ->assertSee( '2 rates · 1 product · 0 shipping methods' )
        ->assertSee( '0 rates · 0 products · 0 shipping methods' );
} );

it( 'summarizes usage with the counts that apply', function (): void {
    expect( Index::usageSummary( [ 'rates' => 1, 'products' => 12, 'methods' => 1 ] ) )->toBe( '1 rate · 12 products · 1 shipping method' );
} );

it( 'relabels a class in its row with Save and Cancel returning focus to the pencil', function (): void {
    $class = TaxClass::query()->where( 'key', 'reduced' )->sole();

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-focus-key="tax-class-' . $class->id . '"' )
        ->call( 'editClass', $class->id )
        ->assertSeeHtml( 'data-edit-class="reduced"' )
        ->assertSeeHtml( 'aria-label="Label"' )
        ->assertSeeHtml( 'data-focus-return="tax-class-' . $class->id . '"' )
        ->assertDontSeeHtml( 'aria-label="Edit ' . $class->label . '"' );
} );

it( 'only offers Keep class for a class still in use', function (): void {
    $class = TaxClass::query()->where( 'key', 'reduced' )->sole();
    TaxRate::factory()->count( 3 )->create( [ 'tax_class_key' => 'reduced' ] );
    ShippingMethod::factory()->create( [ 'tax_class_key' => 'reduced' ] );

    Livewire::test( Index::class )
        ->call( 'confirmDeleteClass', $class->id )
        ->assertSeeHtml( 'role="alertdialog"' )
        ->assertSee( 'This class cannot be deleted yet.' )
        ->assertSee( '3 rates and 1 shipping method use it. Move them to another class first.' )
        ->assertSee( 'Keep class' )
        ->assertDontSeeHtml( 'data-delete-class-button' );
} );

it( 'uses the singular when one thing uses a class', function (): void {
    $class = TaxClass::query()->where( 'key', 'reduced' )->sole();
    Product::factory()->create( [ 'tax_class_key' => 'reduced' ] );

    expect( Index::classBlocker( $class ) )->toBe( '1 product uses it. Move it to another class first.' );

    TaxRate::factory()->create( [ 'tax_class_key' => 'reduced' ] );
    ShippingMethod::factory()->create( [ 'tax_class_key' => 'reduced' ] );

    expect( Index::classBlocker( $class ) )->toBe( '1 rate, 1 product and 1 shipping method use it. Move them to another class first.' );
} );

it( 'offers Delete class beside Keep class when nothing uses it', function (): void {
    $class = TaxClass::query()->where( 'key', 'zero' )->sole();

    Livewire::test( Index::class )
        ->call( 'confirmDeleteClass', $class->id )
        ->assertSee( 'Nothing uses this class. Deleting it cannot be undone.' )
        ->assertSeeHtml( 'data-delete-class-button' )
        ->assertSee( 'Keep class' );
} );

it( 'reads Compound and Taxes shipping as a check or a dash, and empty places as all', function (): void {
    TaxRate::factory()->create( [ 'country_code' => 'CA', 'region_code' => 'QC', 'postal_pattern' => null, 'rate_ubps' => 99_750_000, 'label' => 'QST', 'is_compound' => true, 'is_shipping_taxable' => false, 'is_active' => false ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )
        ->toContain( 'data-flag="yes"' )
        ->toContain( 'data-flag="no"' )
        ->toContain( '<bdi dir="ltr" class="ec-mono">QC</bdi>' )
        ->toContain( '<span class="apui-sr-only">All</span>' )
        ->toContain( '<span class="apui-sr-only">Canada</span>' )
        ->toContain( 'data-rate-active="no"' )
        ->toContain( 'Inactive' )
        ->toContain( '<bdi dir="ltr" class="ec-money" data-rate-percent>9.975%</bdi>' );
} );

it( 'edits a rate in its row with named controls, checkboxes and Save and Cancel', function (): void {
    $rate = TaxRate::factory()->create( [ 'label' => 'NYC tax' ] );

    Livewire::test( Index::class )
        ->call( 'editRate', $rate->id )
        ->assertSeeHtml( 'data-editing-rate="' . $rate->id . '"' )
        ->assertSeeHtml( 'aria-label="Postal codes"' )
        ->assertSeeHtml( 'aria-label="Compound"' )
        ->assertSeeHtml( 'aria-label="Taxes shipping"' )
        ->assertSeeHtml( 'data-focus-return="tax-rate-' . $rate->id . '"' );
} );

it( 'hides Download rates CSV and shows the empty state when there are no rates', function (): void {
    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-download-rates' )
        ->assertSee( 'No tax rates yet' )
        ->assertSee( 'Add a rate for each place you collect tax, or import them from a CSV.' );

    TaxRate::factory()->create();

    Livewire::test( Index::class )->assertSeeHtml( 'data-download-rates' );
} );

it( 'checks an import first, lists skipped rows by row number and names the count', function (): void {
    $csv = implode( "\n", [
        'tax_class_key,country_code,region_code,postal_pattern,rate_percent,label,is_compound,is_shipping_taxable,priority,is_active',
        'standard,FR,,,20,TVA,0,1,0,1',
        'standard,IT,,,22,IVA,0,1,0,1',
        'luxury,ES,,,21,IVA,0,0,0,1',
    ] );

    Livewire::test( Index::class )
        ->call( 'openImport' )
        ->assertSeeHtml( 'class="ec-mono">tax_class_key, country_code' )
        ->assertDontSeeHtml( 'data-apply-import' )
        ->set( 'ratesCsv', UploadedFile::fake()->createWithContent( 'rates.csv', $csv ) )
        ->call( 'checkImport' )
        ->assertSee( '2 to add, 0 to update.' )
        ->assertSee( '1 row has errors and will be skipped.' )
        ->assertSeeHtml( 'data-import-error="4"' )
        ->assertSee( 'Import 2 rates' );
} );

it( 'warns when nothing in the file can be imported', function (): void {
    $csv = implode( "\n", [
        'tax_class_key,country_code,region_code,postal_pattern,rate_percent,label,is_compound,is_shipping_taxable,priority,is_active',
        'luxury,ES,,,21,IVA,0,0,0,1',
    ] );

    Livewire::test( Index::class )
        ->call( 'openImport' )
        ->set( 'ratesCsv', UploadedFile::fake()->createWithContent( 'rates.csv', $csv ) )
        ->call( 'checkImport' )
        ->assertSee( '0 to add, 0 to update.' )
        ->assertSeeHtml( 'data-import-counts' )
        ->assertDontSeeHtml( 'data-apply-import' );
} );
