<?php

namespace Tests\Feature;

use App\Livewire\Garage;
use App\Models\TaxonomyNode;
use App\Models\User;
use App\Models\UserProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P5: the garage stores all Honda/Acura products (table `user_products`). Adding a product seeds
 * facet follows (engine/chassis) so the feed surfaces matching articles; a product pinned to a
 * taxonomy node additionally follows the node's make/model/generation + chassis codes.
 */
class GarageTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_product_persists_and_seeds_follows(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Garage::class)
            ->call('newVehicle')
            ->set('model', 'Integra')->set('chassis', 'DC2')->set('engine', 'B18C')
            ->call('saveVehicle')->assertHasNoErrors();

        $this->assertDatabaseHas('user_products', ['user_id' => $user->id, 'model' => 'Integra', 'chassis' => 'DC2']);
        $this->assertTrue($user->follows()->where('kind', 'engine')->where('value', 'b18c')->exists());
        $this->assertTrue($user->follows()->where('kind', 'chassis')->where('value', 'dc2')->exists());
    }

    public function test_deleting_a_product_removes_it(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['make' => 'Honda', 'model' => 'Civic']);

        Livewire::actingAs($user)->test(Garage::class)->call('deleteVehicle', $product->id);

        $this->assertDatabaseMissing('user_products', ['id' => $product->id]);
    }

    public function test_node_pinned_product_implies_node_follows(): void
    {
        $eg = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'generation', 'slug' => 'eg', 'name' => 'EG',
            'path' => 'cars/honda/civic/eg', 'meta' => ['chassis_codes' => ['eg', 'eh']],
        ]);

        $product = UserProduct::create(['user_id' => User::factory()->create()->id, 'taxonomy_node_id' => $eg->id]);

        $follows = collect($product->impliedFollows());
        $this->assertTrue($follows->contains(fn ($f) => $f['kind'] === 'generation' && $f['value'] === 'eg'));
        $this->assertTrue($follows->contains(fn ($f) => $f['kind'] === 'chassis' && $f['value'] === 'eg'));
        $this->assertTrue($follows->contains(fn ($f) => $f['kind'] === 'chassis' && $f['value'] === 'eh'));
    }
}
