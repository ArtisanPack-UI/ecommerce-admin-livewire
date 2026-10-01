<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\Ecommerce\Models\ProductReviewMedia;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reviews\Index;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'review.viewAny', 'review.view', 'review.moderate', 'review.delete' ] );
    $this->actingAs( makeUser() );
} );

it( 'opens on pending reviews with product, rating, author, verified badge, and excerpt', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug' ] );
    ProductReview::factory()->create( [
        'product_id'           => $mug->id,
        'author_name'          => 'Ada',
        'rating'               => 4,
        'title'                => 'Lovely',
        'body'                 => 'Holds a lot of tea.',
        'is_verified_purchase' => true,
    ] );
    ProductReview::factory()->approved()->create( [ 'author_name' => 'Grace' ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSet( 'filters.status', 'pending' )
        ->assertSee( 'Blue Mug' )
        ->assertSee( 'Ada' )
        ->assertSee( '4 out of 5' )
        ->assertSeeHtml( 'data-verified' )
        ->assertSee( 'Lovely — Holds a lot of tea.' )
        ->assertDontSee( 'Grace' );
} );

it( 'is denied without review.viewAny', function (): void {
    Gate::define( 'ecommerce.review.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'filters by status, rating, product, and verified purchases', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Mug' ] );
    ProductReview::factory()->create( [ 'author_name' => 'Five star', 'rating' => 5, 'product_id' => $mug->id ] );
    ProductReview::factory()->create( [ 'author_name' => 'One star', 'rating' => 1 ] );
    ProductReview::factory()->create( [ 'author_name' => 'Buyer', 'rating' => 3, 'is_verified_purchase' => true ] );
    ProductReview::factory()->create( [ 'author_name' => 'Spammer', 'status' => 'spam' ] );

    Livewire::test( Index::class )
        ->set( 'filters.rating', '1' )->assertSee( 'One star' )->assertDontSee( 'Five star' )
        ->set( 'filters', [ 'product' => (string) $mug->id ] )->assertSee( 'Five star' )->assertDontSee( 'One star' )
        ->set( 'filters', [ 'verified' => '1' ] )->assertSee( 'Buyer' )->assertDontSee( 'Five star' )
        ->set( 'filters', [ 'status' => 'spam' ] )->assertSee( 'Spammer' )->assertDontSee( 'Buyer' );
} );

it( 'approves ten reviews at once', function (): void {
    $reviews = ProductReview::factory()->count( 10 )->create();

    Livewire::test( Index::class )
        ->set( 'selected', $reviews->pluck( 'id' )->map( 'strval' )->all() )
        ->call( 'runBulkAction', 'approve' )
        ->assertSet( 'selected', [] );

    expect( ProductReview::query()->where( 'status', 'approved' )->count() )->toBe( 10 )
        ->and( ProductReview::query()->first()->approved_at )->not->toBeNull();
} );

it( 'rejects a review with a reason', function (): void {
    $review  = ProductReview::factory()->create();
    $reasons = [];
    addAction( 'ap.ecommerce.review.rejected', static function ( $rejected, string $reason ) use ( &$reasons ): void {
        $reasons[] = $reason;
    }, 10, 2 );

    Livewire::test( Index::class )
        ->call( 'startReject', $review->id )
        ->assertSet( 'rejecting', true )
        ->call( 'reject' )
        ->assertHasErrors( [ 'rejectReason' => 'required' ] )
        ->set( 'rejectReason', 'Off-topic' )
        ->call( 'reject' )
        ->assertHasNoErrors()
        ->assertSet( 'rejecting', false );

    expect( $review->refresh()->status )->toBe( 'rejected' )
        ->and( $reasons )->toBe( [ 'Off-topic' ] );

    removeAllActions( 'ap.ecommerce.review.rejected' );
} );

it( 'requires a reason for the bulk reject', function (): void {
    $reviews = ProductReview::factory()->count( 2 )->create();

    Livewire::test( Index::class )
        ->set( 'selected', $reviews->pluck( 'id' )->map( 'strval' )->all() )
        ->call( 'runBulkAction', 'reject' )
        ->assertHasErrors( [ 'rejectReason' => 'required' ] )
        ->set( 'rejectReason', 'Duplicates' )
        ->call( 'runBulkAction', 'reject' )
        ->assertHasNoErrors();

    expect( ProductReview::query()->where( 'status', 'rejected' )->count() )->toBe( 2 );
} );

it( 'rescues a false positive from spam and marks spam', function (): void {
    $spam   = ProductReview::factory()->create( [ 'status' => 'spam' ] );
    $review = ProductReview::factory()->create();

    Livewire::test( Index::class )
        ->call( 'moderate', $spam->id, 'requeue' )
        ->call( 'moderate', $review->id, 'spam' )
        ->call( 'moderate', $review->id, 'bogus' );

    expect( $spam->refresh()->status )->toBe( 'pending' )
        ->and( $review->refresh()->status )->toBe( 'spam' );
} );

it( 'approves one review from its row', function (): void {
    $review = ProductReview::factory()->create( [ 'author_name' => 'Ada' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'Approve the review by Ada' )
        ->call( 'moderate', $review->id, 'approve' )
        ->assertDontSee( 'Ada' );

    expect( $review->refresh()->status )->toBe( 'approved' );
} );

it( 'deletes a review after confirmation', function (): void {
    $review = ProductReview::factory()->create();

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $review->id )
        ->assertSet( 'confirmingBulkAction', 'delete' );

    $component->call( 'confirmBulkAction', $component->viewData( 'tableConfirmToken' ) );

    expect( ProductReview::query()->find( $review->id ) )->toBeNull();
} );

it( 'refuses moderation without review.moderate', function (): void {
    Gate::define( 'ecommerce.review.moderate', static fn (): bool => false );
    $review = ProductReview::factory()->create( [ 'author_name' => 'Ada' ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'Approve the review by Ada' )
        ->call( 'moderate', $review->id, 'approve' )
        ->assertForbidden();

    expect( $review->refresh()->status )->toBe( 'pending' );
} );

it( 'shows the full review escaped, with its media, in the drawer', function (): void {
    $review = ProductReview::factory()->create( [
        'title' => '<b>Bold</b>',
        'body'  => '<script>alert("x")</script> Great mug',
    ] );
    ProductReviewMedia::query()->create( [ 'review_id' => $review->id, 'media_id' => 77 ] );

    Livewire::test( Index::class )
        ->call( 'showReview', $review->id )
        ->assertSet( 'viewing', true )
        ->assertSeeHtml( 'data-review-detail="' . $review->id . '"' )
        ->assertSeeHtml( '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Great mug' )
        ->assertDontSeeHtml( '<script>alert("x")</script>' )
        ->assertDontSeeHtml( '<b>Bold</b>' )
        ->assertSee( 'Media library file #77' );
} );

it( 'refuses the drawer without review.view', function (): void {
    Gate::define( 'ecommerce.review.view', static fn (): bool => false );
    $review = ProductReview::factory()->create();

    Livewire::test( Index::class )
        ->call( 'showReview', $review->id )
        ->assertForbidden();
} );

it( 'forgets a reason typed into a cancelled reject dialog', function (): void {
    $reviews = ProductReview::factory()->count( 2 )->create();

    Livewire::test( Index::class )
        ->call( 'startReject', $reviews[0]->id )
        ->set( 'rejectReason', 'Typed, then cancelled' )
        ->call( 'closeReject' )
        ->assertSet( 'rejectReason', '' )
        ->set( 'selected', $reviews->pluck( 'id' )->map( 'strval' )->all() )
        ->call( 'runBulkAction', 'reject' )
        ->assertHasErrors( [ 'rejectReason' => 'required' ] );

    expect( ProductReview::query()->where( 'status', 'rejected' )->count() )->toBe( 0 );
} );
