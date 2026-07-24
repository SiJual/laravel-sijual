<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UmkmProfile;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->profile = UmkmProfile::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_user_can_view_transactions(): void
    {
        Transaction::factory()->count(3)->create([
            'umkm_id' => $this->profile->id
        ]);

        $response = $this->actingAs($this->user)->get('/sikas/transactions');

        $response->assertStatus(200);
        $response->assertViewHas('transactions');
    }

    public function test_user_can_create_transaction(): void
    {
        $response = $this->actingAs($this->user)->post('/sikas/transactions', [
            'type' => 'income',
            'amount' => 50000,
            'category' => 'Sales',
            'description' => 'Test sale',
            'transaction_date' => now()->format('Y-m-d')
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'amount' => 50000,
            'description' => 'Test sale'
        ]);
    }
}
