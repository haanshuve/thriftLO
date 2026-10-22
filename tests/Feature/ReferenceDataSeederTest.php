<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedReference(): void
    {
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_seeder_is_a_no_op_on_a_freshly_migrated_database(): void
    {
        $this->seedReference();
        $this->seedReference();

        $this->assertSame(count(ReferenceDataSeeder::CATEGORIES), Category::count());
        $this->assertSame(count(ReferenceDataSeeder::BRANDS), Brand::count());
    }

    public function test_seeder_fills_empty_tables_once_and_is_safe_to_rerun(): void
    {
        DB::table('reference_data_seeds')->delete();
        Category::query()->delete();
        Brand::query()->delete();

        $this->seedReference();
        $this->seedReference();

        $this->assertSame(count(ReferenceDataSeeder::CATEGORIES), Category::count());
        $this->assertSame(count(ReferenceDataSeeder::BRANDS), Brand::count());
        $this->assertSame('Elektronik', Category::where('slug', 'Vintage Tech')->value('label'));
        $this->assertTrue(Brand::where('slug', 'levis')->first()->is_popular);
    }

    public function test_seeder_does_not_overwrite_admin_edits(): void
    {
        Category::where('slug', 'Tas')->update(['label' => 'Tas & Dompet', 'icon' => '👛', 'sort_order' => 99]);
        Brand::where('slug', 'nike')->update(['is_active' => false, 'is_popular' => false]);

        $this->seedReference();

        $tas = Category::where('slug', 'Tas')->first();
        $this->assertSame(['Tas & Dompet', '👛', 99], [$tas->label, $tas->icon, (int) $tas->sort_order]);

        $nike = Brand::where('slug', 'nike')->first();
        $this->assertFalse($nike->is_active);
        $this->assertFalse($nike->is_popular);
    }

    public function test_seeder_does_not_recreate_items_deleted_by_admin(): void
    {
        Category::where('slug', 'Lainnya')->delete();
        Brand::where('slug', 'zara')->delete();

        $this->seedReference();

        $this->assertFalse(Category::where('slug', 'Lainnya')->exists());
        $this->assertFalse(Brand::where('name', 'Zara')->exists());
    }

    public function test_seeder_does_not_duplicate_brand_renamed_by_admin(): void
    {
        Brand::where('slug', 'hm')->first()->update(['name' => 'H & M']);

        $this->seedReference();

        $this->assertSame(1, Brand::whereIn('name', ['H&M', 'H & M'])->count());
    }

    public function test_seeder_matches_existing_brand_by_name_when_ledger_is_missing(): void
    {
        DB::table('reference_data_seeds')->delete();
        Brand::where('slug', 'nike')->first()->update(['name' => 'NIKE']);

        $this->seedReference();

        $this->assertSame(1, Brand::whereRaw('LOWER(name) = ?', ['nike'])->count());
    }

    public function test_database_seeder_never_creates_demo_products(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Product::count());
    }

    public function test_railway_pre_deploy_runs_migrations_and_reference_seeder_only(): void
    {
        $config = json_decode(file_get_contents(base_path('railway.json')), true);

        $this->assertSame(
            ['php artisan migrate --force && php artisan db:seed --class=ReferenceDataSeeder --force'],
            $config['deploy']['preDeployCommand']
        );
    }

    public function test_homepage_static_assets_exist_in_repo(): void
    {
        $this->assertFileExists(public_path('images/logo.jpeg.jpeg'));
        $this->assertFileExists(public_path('favicon.ico'));
    }
}
