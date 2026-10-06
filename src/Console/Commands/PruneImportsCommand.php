<?php

/**
 * Prune imports command.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Console\Commands;

use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductImports;
use Illuminate\Console\Command;

/**
 * Deletes product imports (and their uploaded files) that finished, failed,
 * or were abandoned more than `ProductImports::KEEP_DAYS` days ago. Schedule
 * it daily:
 *
 * ```php
 * Schedule::command( 'ecommerce-admin:prune-imports' )->daily();
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class PruneImportsCommand extends Command
{
    /**
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'ecommerce-admin:prune-imports';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Delete product imports that finished, failed, or were abandoned a while ago, with their uploaded files.';

    /**
     * Prunes the imports.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function handle(): int
    {
        $deleted = ProductImports::prune();

        $this->components->info( trans_choice( 'Deleted :count old import.|Deleted :count old imports.', $deleted, [ 'count' => $deleted ] ) );

        return self::SUCCESS;
    }
}
