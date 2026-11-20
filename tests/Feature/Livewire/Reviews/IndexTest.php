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
        ->assertSeeHtml( 'data-reject-form' )
        ->assertSee( 'Reason (optional)' )
        ->assertSee( 'For your team. The reviewer is not told.' )
        ->set( 'rejectReason', str_repeat( 'x', 501 ) )
        ->call( 'reject' )
        ->assertHasErrors( [ 'rejectReason' => 'max' ] )
        ->set( 'rejectReason', 'Off-topic' )
        ->call( 'reject' )
        ->assertHasNoErrors()
        ->assertSet( 'rejecting', false );

    expect( $review->refresh()->status )->toBe( 'rejected' )
        ->and( $reasons )->toBe( [ 'Off-topic' ] );

    removeAllActions( 'ap.ecommerce.review.rejected' );
} );

it( 'rejects a review without a reason, since the reason is optional', function (): void {
    $review = ProductReview::factory()->create();

    Livewire::test( Index::class )
        ->call( 'startReject', $review->id )
        ->call( 'reject' )
        ->assertHasNoErrors()
        ->assertSet( 'rejecting', false );

    expect( $review->refresh()->status )->toBe( 'rejected' );
} );

it( 'bulk rejects with the bulk bar reason, or none', function (): void {
    $reviews = ProductReview::factory()->count( 3 )->create();
    $reasons = [];
    addAction( 'ap.ecommerce.review.rejected', static function ( $rejected, string $reason ) use ( &$reasons ): void {
        $reasons[] = $reason;
    }, 10, 2 );

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $reviews[0]->id, (string) $reviews[1]->id ] )
        ->set( 'rejectReason', str_repeat( 'x', 501 ) )
        ->call( 'runBulkAction', 'reject' )
        ->assertHasErrors( [ 'rejectReason' => 'max' ] )
        ->set( 'rejectReason', 'Duplicates' )
        ->call( 'runBulkAction', 'reject' )
        ->assertHasNoErrors()
        ->assertSet( 'rejectReason', '' )
        ->set( 'filters.status', '' )
        ->set( 'selected', [ (string) $reviews[2]->id ] )
        ->call( 'runBulkAction', 'reject' )
        ->assertHasNoErrors();

    expect( ProductReview::query()->where( 'status', 'rejected' )->count() )->toBe( 3 )
        ->and( $reasons )->toBe( [ 'Duplicates', 'Duplicates', '' ] );

    removeAllActions( 'ap.ecommerce.review.rejected' );
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

it( 'approves one review from its drawer', function (): void {
    $review = ProductReview::factory()->create( [ 'author_name' => 'Ada' ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'Approve the review by Ada' )
        ->call( 'showReview', $review->id )
        ->assertSeeHtml( 'Approve the review by Ada' )
        ->call( 'moderate', $review->id, 'approve' )
        ->assertDontSee( 'Read the review by Ada' );

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

it( 'keeps the multi-selection when a single-row delete is cancelled or done', function (): void {
    $first  = ProductReview::factory()->create();
    $second = ProductReview::factory()->create();
    $doomed = ProductReview::factory()->create();

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ $first->id, $second->id ] )
        ->call( 'confirmDelete', $doomed->id )
        ->assertSet( 'selected', [ $doomed->id ] )
        ->call( 'cancelBulkAction' )
        ->assertSet( 'confirmingBulkAction', null )
        ->assertSet( 'selected', [ $first->id, $second->id ] )
        ->assertSet( 'selectionBeforeDelete', null );

    $component->call( 'confirmDelete', $doomed->id );
    $component->call( 'confirmBulkAction', $component->viewData( 'tableConfirmToken' ) )
        ->assertSet( 'selected', [ $first->id, $second->id ] );

    expect( ProductReview::query()->find( $doomed->id ) )->toBeNull()
        ->and( ProductReview::query()->count() )->toBe( 2 );
} );

it( 'keeps rejection reasons with angle brackets intact', function (): void {
    $review   = ProductReview::factory()->create();
    $reasons  = [];
    $callback = static function ( ProductReview $rejected, string $reason ) use ( &$reasons ): void {
        $reasons[] = $reason;
    };

    addAction( 'ap.ecommerce.review.rejected', $callback );

    Livewire::test( Index::class )
        ->call( 'startReject', $review->id )
        ->set( 'rejectReason', '  Rating <3 but text praises it; "O<Brien" spam  ' )
        ->call( 'reject' )
        ->assertHasNoErrors();

    removeAction( 'ap.ecommerce.review.rejected', $callback );

    expect( $review->refresh()->status )->toBe( 'rejected' )
        ->and( $reasons )->toBe( [ 'Rating <3 but text praises it; "O<Brien" spam' ] );
} );

it( 'scopes the moderation loading states to their own actions', function (): void {
    grantAbilities( [ 'review.viewAny', 'review.moderate', 'review.delete' ] );
    Gate::define( 'ecommerce.review.view', static fn (): bool => false );
    ProductReview::factory()->create( [ 'author_name' => 'Ada' ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toMatch( '/data-moderate="approve"/' )
        ->and( preg_match_all( '/wire:click="moderate\([^"]*"[^>]*wire:target="moderate"/', $html ) )->toBeGreaterThan( 0 )
        ->and( $html )->toMatch( '/data-moderate="delete"[^>]*data-icon-only|icon-only[^>]*data-moderate="delete"/s' );
} );

it( 'refuses moderation without review.moderate', function (): void {
    Gate::define( 'ecommerce.review.moderate', static fn (): bool => false );
    $review = ProductReview::factory()->create( [ 'author_name' => 'Ada' ] );

    Livewire::test( Index::class )
        ->call( 'showReview', $review->id )
        ->assertDontSeeHtml( 'Approve the review by Ada' )
        ->assertDontSeeHtml( 'Reject the review by Ada' )
        ->assertSeeHtml( 'Delete the review by Ada' )
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
    $reasons = [];
    addAction( 'ap.ecommerce.review.rejected', static function ( $rejected, string $reason ) use ( &$reasons ): void {
        $reasons[] = $reason;
    }, 10, 2 );

    Livewire::test( Index::class )
        ->call( 'startReject', $reviews[0]->id )
        ->set( 'rejectReason', 'Typed, then cancelled' )
        ->call( 'closeReject' )
        ->assertSet( 'rejectReason', '' )
        ->set( 'selected', $reviews->pluck( 'id' )->map( 'strval' )->all() )
        ->call( 'runBulkAction', 'reject' )
        ->assertHasNoErrors();

    expect( ProductReview::query()->where( 'status', 'rejected' )->count() )->toBe( 2 )
        ->and( $reasons )->toBe( [ '', '' ] );

    removeAllActions( 'ap.ecommerce.review.rejected' );
} );

it( 'renders the design-system table: description, search hint, stars, clamped text and muted cells', function (): void {
    ProductReview::factory()->create( [
        'author_name'          => 'Ada',
        'author_email'         => 'ada@example.com',
        'rating'               => 2,
        'title'                => null,
        'body'                 => str_repeat( 'Long review text. ', 20 ),
        'is_verified_purchase' => false,
    ] );

    $html = Livewire::test( Index::class )
        ->assertSee( 'What customers say about your products, waiting for you to approve before it goes live.' )
        ->assertSeeHtml( 'placeholder="Search reviews, products or authors"' )
        ->assertSeeHtml( 'class="apui-rating' )
        ->assertSeeHtml( 'role="img"' )
        ->assertSee( '2 out of 5' )
        ->assertSeeHtml( 'data-review-excerpt' )
        ->assertDontSee( 'ada@example.com' )
        ->html();

    expect( substr_count( $html, 'Long review text.' ) )->toBe( 20 )
        ->and( $html )->not->toContain( 'link-hover' )
        ->and( $html )->not->toContain( 'opacity-75' )
        ->and( $html )->not->toContain( 'text-end' );
} );

it( 'offers Read and Delete in the row, named for the author', function (): void {
    $review = ProductReview::factory()->create( [ 'author_name' => 'Ada' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'aria-label="Read the review by Ada"' )
        ->assertSeeHtml( 'aria-label="Delete the review by Ada"' )
        ->assertSeeHtml( 'data-read-review="' . $review->id . '"' )
        ->assertDontSeeHtml( 'data-moderate="approve"' );
} );

it( 'puts the moderation buttons in the row when the drawer is not allowed', function (): void {
    Gate::define( 'ecommerce.review.view', static fn (): bool => false );
    ProductReview::factory()->create( [ 'author_name' => 'Ada' ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'Read the review by Ada' )
        ->assertSeeHtml( 'aria-label="Approve the review by Ada"' )
        ->assertSeeHtml( 'aria-label="Reject the review by Ada"' )
        ->assertSeeHtml( 'aria-label="Mark the review by Ada as spam"' );
} );

it( 'titles the drawer for the author and moderates from its footer', function (): void {
    $order  = ArtisanPackUI\Ecommerce\Models\Order::factory()->create();
    $review = ProductReview::factory()->create( [
        'author_name'          => 'Daniel Okafor',
        'author_email'         => 'daniel@example.com',
        'is_verified_purchase' => true,
        'order_id'             => $order->id,
    ] );

    Livewire::test( Index::class )
        ->call( 'showReview', $review->id )
        ->assertSee( 'Review by Daniel Okafor' )
        ->assertSee( 'daniel@example.com' )
        ->assertSee( '#' . $order->order_number )
        ->assertSeeHtml( 'data-review-moderation' )
        ->assertSeeHtml( 'aria-label="Approve the review by Daniel Okafor"' )
        ->assertSeeHtml( 'aria-label="Reject the review by Daniel Okafor"' )
        ->assertSeeHtml( 'aria-label="Mark the review by Daniel Okafor as spam"' )
        ->assertSeeHtml( 'aria-label="Delete the review by Daniel Okafor"' )
        ->assertDontSeeHtml( 'data-moderate="requeue"' );
} );

it( 'offers Back to pending, not Approve, for an approved review', function (): void {
    $review = ProductReview::factory()->approved()->create( [ 'author_name' => 'Ada' ] );

    Livewire::test( Index::class )
        ->set( 'filters.status', 'approved' )
        ->call( 'showReview', $review->id )
        ->assertSeeHtml( 'aria-label="Move the review by Ada back to pending"' )
        ->assertDontSeeHtml( 'data-moderate="approve"' )
        ->call( 'moderate', $review->id, 'requeue' );

    expect( $review->refresh()->status )->toBe( 'pending' );
} );

it( 'puts the reason field and a soft Approve in the bulk bar', function (): void {
    $reviews = ProductReview::factory()->count( 2 )->create();

    $html = Livewire::test( Index::class )
        ->set( 'selected', $reviews->pluck( 'id' )->map( 'strval' )->all() )
        ->assertSeeHtml( 'id="reviews-bulk-reject-reason"' )
        ->assertSeeHtml( 'aria-label="Reason (for Reject)"' )
        ->assertSee( 'Mark as spam' )
        ->assertSee( 'Back to pending' )
        ->html();

    expect( substr_count( $html, 'wire:key="bulk-action-approve"' ) )->toBe( 1 )
        ->and( substr_count( $html, 'data-focus-key="bulk-approve"' ) )->toBe( 1 );
} );

it( 'hides the bulk reason and Approve from a user who may not moderate', function (): void {
    Gate::define( 'ecommerce.review.moderate', static fn (): bool => false );
    $reviews = ProductReview::factory()->count( 2 )->create();

    Livewire::test( Index::class )
        ->set( 'selected', $reviews->pluck( 'id' )->map( 'strval' )->all() )
        ->assertDontSeeHtml( 'reviews-bulk-reject-reason' )
        ->assertDontSeeHtml( 'bulk-action-approve' );
} );

it( 'closes the drawer before asking to delete from it, so the confirmation is reachable', function (): void {
    $review = ProductReview::factory()->create();

    Livewire::test( Index::class )
        ->call( 'showReview', $review->id )
        ->assertSet( 'viewing', true )
        ->call( 'confirmDelete', $review->id )
        ->assertSet( 'viewing', false )
        ->assertSet( 'confirmingBulkAction', 'delete' );
} );

it( 'names the stars in the current locale', function (): void {
    ProductReview::factory()->create( [ 'rating' => 2 ] );
    app()->setLocale( 'de' );

    Livewire::test( Index::class )->assertSee( '2 von 5' );
} );

it( 'labels the bulk reason field visibly', function (): void {
    $review = ProductReview::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $review->id ] )
        ->assertSeeHtml( '<label class="ec-bulk-inline-label" for="reviews-bulk-reject-reason">Reason (for Reject)</label>' )
        ->assertSeeHtml( 'placeholder="Optional"' );
} );
