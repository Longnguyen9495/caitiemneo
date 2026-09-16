<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 tests for navigation: unique IDs between desktop/mobile and no-JS fallback.
 */
class NavigationIdAndNoJsTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_has_no_duplicate_collapse_ids(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();

        $this->actingAs($manager);

        $response = $this->get(route('admin.dashboard'));
        $html = $response->getContent() ?: '';

        // Extract all id="nav-desktop-group-..." attributes
        preg_match_all('/id="(nav-desktop-group-[^"]+)"/', $html, $desktopMatches);
        $desktopIds = $desktopMatches[1];

        // Extract all id="nav-mobile-group-..." attributes
        preg_match_all('/id="(nav-mobile-group-[^"]+)"/', $html, $mobileMatches);
        $mobileIds = $mobileMatches[1];

        // Desktop and mobile should have the same group IDs but prefixed differently
        $this->assertNotEmpty($desktopIds, 'Desktop navigation group IDs should exist');
        $this->assertNotEmpty($mobileIds, 'Mobile navigation group IDs should exist');

        // There should be no duplicates within each prefix
        $this->assertSame(
            count($desktopIds),
            count(array_unique($desktopIds)),
            'Duplicate desktop navigation group IDs found: '.implode(', ', $desktopIds)
        );
        $this->assertSame(
            count($mobileIds),
            count(array_unique($mobileIds)),
            'Duplicate mobile navigation group IDs found: '.implode(', ', $mobileIds)
        );

        // IDs must be different between desktop and mobile (prefixed differently)
        $common = array_intersect($desktopIds, $mobileIds);
        $this->assertEmpty($common, 'Desktop and mobile navigation IDs should not overlap');
    }

    public function test_navigation_items_are_visible_without_javascript(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();

        $this->actingAs($manager);

        $response = $this->get(route('admin.dashboard'));
        $html = $response->getContent() ?: '';

        // Navigation links should be present in the HTML even if JS is off
        // Bootstrap collapse with `show` class keeps items visible
        // Navigation links should be present in the HTML even if JS is off
        // The noscript style ensures items are visible
        $this->assertStringContainsString('Tổng quan', $html);
        $this->assertStringContainsString('Chấm công', $html);
        $this->assertStringContainsString('Hóa đơn', $html);
        $this->assertStringContainsString('Lịch hẹn', $html);
        // Verify noscript tag exists for no-JS fallback
        $this->assertStringContainsString('<noscript>', $html);
    }
}
