<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['name' => 'Budi', 'role' => 'pembeli']);
        $this->seller = User::factory()->create(['name' => 'Rina', 'role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Batam Vintage Hub', 'lokasi_lapak' => 'Batam Center']);
    }

    private function message(User $from, User $to, string $text, bool $read = false, ?Product $product = null): Message
    {
        return Message::create([
            'sender_id' => $from->id,
            'receiver_id' => $to->id,
            'message' => $text,
            'is_read' => $read,
            'product_id' => $product?->id,
        ]);
    }

    private function product(?User $owner = null, string $title = 'Jaket Denim Levi\'s'): Product
    {
        return Product::create([
            'user_id' => ($owner ?? $this->seller)->id,
            'title' => $title,
            'price' => 185000,
            'image_url' => 'products/jaket.jpg',
            'image_path' => 'products/jaket.jpg',
            'status' => 'Available',
        ]);
    }

    public function test_contact_list_only_shows_people_you_have_chatted_with(): void
    {
        $stranger = User::factory()->create(['name' => 'Orang Asing Tanpa Chat']);
        $this->message($this->seller, $this->buyer, 'Halo kak, masih ada');

        $this->actingAs($this->buyer)->get('/chat')->assertOk()
            ->assertSee('Batam Vintage Hub')
            ->assertSee('Halo kak, masih ada')
            ->assertSee('1 pesan belum dibaca')
            ->assertDontSee('Orang Asing Tanpa Chat');
    }

    public function test_conversations_are_ordered_by_latest_message_with_own_prefix(): void
    {
        $other = User::factory()->create(['name' => 'Dimas', 'role' => 'penjual', 'nama_toko' => 'Nagoya Thrift']);
        $this->message($this->buyer, $other, 'Pesan lama ke Dimas', true);
        $this->message($this->buyer, $this->seller, 'Pesan terbaru ke Rina', true);

        $this->actingAs($this->buyer)->get('/chat')->assertOk()
            ->assertSeeInOrder(['Batam Vintage Hub', 'Kamu: Pesan terbaru ke Rina', 'Nagoya Thrift', 'Kamu: Pesan lama ke Dimas']);
    }

    public function test_conversations_for_counts_unread_per_partner(): void
    {
        $this->message($this->seller, $this->buyer, 'satu');
        $this->message($this->seller, $this->buyer, 'dua');
        $this->message($this->buyer, $this->seller, 'balasan', true);

        $conversations = Message::conversationsFor($this->buyer);

        $this->assertCount(1, $conversations);
        $this->assertSame($this->seller->id, $conversations[0]['partner']->id);
        $this->assertSame('balasan', $conversations[0]['last']->message);
        $this->assertSame(2, $conversations[0]['unread']);
    }

    public function test_opening_chat_from_product_shows_product_card_and_new_contact(): void
    {
        $product = $this->product();

        $this->actingAs($this->buyer)->get(route('chat.index', ['user_id' => $this->seller->id, 'product_id' => $product->id]))->assertOk()
            ->assertSee('Membahas barang')
            ->assertSee("Jaket Denim Levi's")
            ->assertSee('Rp185.000')
            ->assertSee(asset('storage/products/jaket.jpg'), false)
            ->assertSee('Percakapan baru')
            ->assertSee('name="product_id" value="' . $product->id . '"', false);
    }

    public function test_product_not_owned_by_either_party_is_ignored(): void
    {
        $someoneElse = User::factory()->create(['role' => 'penjual']);
        $product = $this->product($someoneElse, 'Barang Orang Lain');

        $this->actingAs($this->buyer)->get(route('chat.index', ['user_id' => $this->seller->id, 'product_id' => $product->id]))->assertOk()
            ->assertDontSee('Barang Orang Lain')
            ->assertDontSee('Membahas barang');
    }

    public function test_opening_conversation_marks_incoming_messages_read(): void
    {
        $incoming = $this->message($this->seller, $this->buyer, 'Halo');

        $this->actingAs($this->buyer)->get(route('chat.index', ['user_id' => $this->seller->id]))->assertOk();

        $this->assertTrue((bool) $incoming->fresh()->is_read);
    }

    public function test_sending_message_keeps_product_context(): void
    {
        $product = $this->product();

        $this->actingAs($this->buyer)
            ->post(route('chat.send'), ['receiver_id' => $this->seller->id, 'product_id' => $product->id, 'message' => 'Bisa nego?'])
            ->assertRedirect(route('chat.index', ['user_id' => $this->seller->id, 'product_id' => $product->id]));

        $this->assertDatabaseHas('messages', ['sender_id' => $this->buyer->id, 'receiver_id' => $this->seller->id, 'product_id' => $product->id, 'message' => 'Bisa nego?']);
    }

    public function test_cannot_chat_with_yourself(): void
    {
        $this->actingAs($this->buyer)->get(route('chat.index', ['user_id' => $this->buyer->id]))
            ->assertRedirect(route('chat.index'));

        $this->actingAs($this->buyer)->from('/chat')
            ->post(route('chat.send'), ['receiver_id' => $this->buyer->id, 'message' => 'halo diri sendiri'])
            ->assertSessionHasErrors('receiver_id');

        $this->assertSame(0, Message::count());
    }

    public function test_mobile_bottom_nav_is_hidden_inside_a_conversation_only(): void
    {
        $this->actingAs($this->buyer)->get('/chat')->assertOk()
            ->assertSee('Navigasi bawah', false);

        $this->actingAs($this->buyer)->get(route('chat.index', ['user_id' => $this->seller->id]))->assertOk()
            ->assertDontSee('Navigasi bawah', false)
            ->assertSee('Kembali ke daftar chat', false);
    }

    public function test_floating_widget_lists_conversations_not_all_users(): void
    {
        User::factory()->create(['name' => 'Orang Asing Tanpa Chat']);
        $this->message($this->seller, $this->buyer, 'Halo');

        $this->actingAs($this->buyer)->get('/')->assertOk()
            ->assertSee('Rina')
            ->assertDontSee('Orang Asing Tanpa Chat');
    }
}
