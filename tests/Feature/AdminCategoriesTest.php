<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_migration_moves_config_categories_to_database_keeping_old_keys(): void
    {
        $this->assertSame(['Fashion', 'Sepatu', 'Tas', 'Aksesoris', 'Vintage Tech', 'Lainnya'], Category::slugs());
        $this->assertSame(['label' => 'Elektronik', 'icon' => '📷'], Category::options()['Vintage Tech']);

        $this->actingAs($this->admin)->get(route('admin.categories'))->assertOk()
            ->assertSee('Pakaian')
            ->assertSee('Elektronik');
    }

    public function test_admin_can_add_category_and_it_appears_in_catalog_and_validation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['label' => 'Mainan Anak', 'icon' => '🧸'])
            ->assertSessionHas('success');

        $category = Category::where('slug', 'mainan-anak')->sole();
        $this->assertSame('Mainan Anak', $category->label);
        $this->assertSame(7, $category->sort_order);

        $this->get('/')->assertSee('Mainan Anak')->assertSee('kategori=mainan-anak', false);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['label' => 'Sepatu'])
            ->assertSessionHasErrorsIn('createCategory', 'label');

        $this->assertSame(6, Category::count());
    }

    public function test_admin_can_rename_category_without_changing_its_key(): void
    {
        $category = Category::where('slug', 'Fashion')->sole();

        $this->actingAs($this->admin)->put(route('admin.categories.update', $category), ['label' => 'Baju & Celana', 'icon' => '👖', 'sort_order' => 3])
            ->assertSessionHas('success');

        $category->refresh();
        $this->assertSame('Fashion', $category->slug);
        $this->assertSame('Baju & Celana', $category->label);
        $this->assertSame(['label' => 'Baju & Celana', 'icon' => '👖'], Category::options()['Fashion']);
    }

    public function test_category_in_use_cannot_be_deleted_but_unused_one_can(): void
    {
        $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified']);
        Product::create(['user_id' => $seller->id, 'title' => 'Tas Kulit', 'price' => 10000, 'image_url' => 'p.jpg', 'kategori' => 'Tas', 'status' => 'Available']);

        $tas = Category::where('slug', 'Tas')->sole();
        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $tas))->assertSessionHas('error');
        $this->assertModelExists($tas);

        $lainnya = Category::where('slug', 'Lainnya')->sole();
        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $lainnya))->assertSessionHas('success');
        $this->assertModelMissing($lainnya);
        $this->assertNotContains('Lainnya', Category::slugs());
    }
}
