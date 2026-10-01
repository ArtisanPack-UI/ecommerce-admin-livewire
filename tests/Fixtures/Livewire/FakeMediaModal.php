<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use Livewire\Component;

/**
 * Stands in for media-library's modal (`media::media-modal`) in tests.
 *
 * @since 1.0.0
 */
class FakeMediaModal extends Component
{
    /**
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $multiSelect = false;

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $context = '';

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function render(): string
    {
        return '<div data-fake-media-modal></div>';
    }
}
