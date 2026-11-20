<?php

namespace Tests\\Feature\\Stores;

use App\\Enums\\TeamRole;
use App\\Models\\Store;
use App\\Models\\Team;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Http\\UploadedFile;
use Illuminate\\Support\\Facades\\Storage;
use Inertia\\Testing\\AssertableInertia as Assert;
use Tests\\TestCase;

class StorefrontConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_configuration_page_displays_saved_settings(): void
    {
        [$user, $team, $store] = $this->createOwnedStore([
            'storefront' => [
                'description' => 'Quality everyday goods.',
                'primary_color' => '#123456',
                'accent_color' => '#654321',
                'navigation' => [['label' => 'About', 'url' => '/about']],
                'contact' => ['email' => 'hello@example.test', 'phone' => '+62812345678', 'address' => 'Jakarta'],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('teams.stores.edit', ['team' => $team->slug, 'store' => $store->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stores/settings')
                ->where('store.storefront.description', 'Quality everyday goods.')
                ->where('store.storefront.primary_color', '#123456')
                ->where('store.storefront.navigation.0.label', 'About')
                ->where('store.storefront.contact.email', 'hello@example.test'),
            );
    }

    public function test_storefront_configuration_can_be_updated_with_brand_assets_and_navigation(): void
    {
        Storage::fake('public');
        [$user, $team, $store] = $this->createOwnedStore();

        $this->actingAs($user)
            ->patch(route('teams.stores.update', ['team' => $team->slug, 'store' => $store->id]), [
                'name' => 'Updated Store',
                'slug' => $store->slug,
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'description' => 'A refreshed store description.',
                'primary_color' => '#112233',
                'accent_color' => '#445566',
                'navigation' => [
                    ['label' => 'About', 'url' => '/about'],
                    ['label' => 'Help', 'url' => 'https://support.example.test'],
                ],
                'contact' => ['email' => 'support@example.test', 'phone' => '+628123456789', 'address' => 'Jakarta, Indonesia'],
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
                'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
            ])
            ->assertRedirect(route('teams.stores.show', ['team' => $team->slug, 'store' => $store->id]));

        $store->refresh();
        $settings = $store->settings['storefront'];

        $this->assertSame('Updated Store', $store->name);
        $this->assertSame('#112233', $settings['primary_color']);
        $this->assertSame('About', $settings['navigation'][0]['label']);
        $this->assertSame('support@example.test', $settings['contact']['email']);
        Storage::disk('public')->assertExists($settings['logo_path']);
        Storage::disk('public')->assertExists($settings['banner_path']);
    }

    public function test_public_storefront_renders_saved_branding_navigation_and_contact_details(): void
    {
        $settings = [
            'storefront' => [
                'description' => 'Handpicked products for everyday life.',
                'primary_color' => '#123456',
                'accent_color' => '#654321',
                'navigation' => [['label' => 'About', 'url' => '/about']],
                'contact' => ['email' => 'hello@example.test', 'phone' => '+62812345678', 'address' => 'Jakarta'],
            ],
        ];
        [, , $store] = $this->createOwnedStore($settings);
        $store->update(['status' => 'active']);
        $store->domains()->create([
            'domain' => 'shop.localhost',
            'type' => 'subdomain',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $this->get('http://shop.localhost/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/index')
                ->where('store.name', 'Example Store')
                ->where('store.description', 'Handpicked products for everyday life.')
                ->where('store.primary_color', '#123456')
                ->where('store.navigation.0.label', 'About')
                ->where('store.contact.email', 'hello@example.test'),
            );
    }

    public function test_storefront_configuration_rejects_invalid_theme_colors_and_navigation_urls(): void
    {
        [$user, $team, $store] = $this->createOwnedStore();

        $this->actingAs($user)
            ->from(route('teams.stores.edit', ['team' => $team->slug, 'store' => $store->id]))
            ->patch(route('teams.stores.update', ['team' => $team->slug, 'store' => $store->id]), [
                'name' => $store->name,
                'slug' => $store->slug,
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'primary_color' => 'red',
                'accent_color' => '#445566',
                'navigation' => [['label' => 'Unsafe', 'url' => 'javascript:alert(1)']],
            ])
            ->assertSessionHasErrors(['primary_color', 'navigation.0.url']);
    }

    /**
     * @return array{User, Team, Store}
     */
    private function createOwnedStore(array $settings = []): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create([
            'name' => 'Example Store',
            'slug' => 'example-store',
            'status' => 'draft',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => $settings,
        ]);

        return [$user, $team, $store];
    }
}
