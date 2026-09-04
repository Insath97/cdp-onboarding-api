<?php

use App\Models\User;
use App\Models\Role;
use App\Models\ChannelWiseEmployment;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);

    $this->user = User::firstOrCreate(
        ['email' => 'admin@test.com'],
        [
            'name' => 'Super Admin User',
            'username' => 'superadmin_test',
            'password' => bcrypt('password'),
            'user_type' => 'super_admin',
            'is_active' => true,
            'can_login' => true,
        ]
    );

    $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'api')->first();
    if ($superAdminRole && !$this->user->hasRole($superAdminRole)) {
        $this->user->assignRole($superAdminRole);
    }

    $this->token = JWTAuth::fromUser($this->user);
});

afterEach(function () {
    ChannelWiseEmployment::where('name', 'like', '%Test%')
        ->orWhere('name', 'like', '%Channel%')
        ->orWhere('name', 'like', '%Full-time%')
        ->orWhere('name', 'like', '%Updated Name%')
        ->delete();
});

it('can list channel wise employments with authentication', function () {
    ChannelWiseEmployment::create(['name' => 'Full-time Test', 'description' => 'Direct contract', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                     ->getJson('/api/v1/channel-wise-employments');

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'status',
                 'message',
                 'data' => [
                     'data',
                     'current_page',
                 ]
             ]);
});

it('can create a channel wise employment', function () {
    $payload = [
        'name' => 'Contractual Channel Test',
        'description' => 'Temporary employment channel',
        'is_active' => true,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                     ->postJson('/api/v1/channel-wise-employments', $payload);

    $response->assertStatus(201)
             ->assertJson([
                 'status' => 'success',
                 'message' => 'Channel wise employment created successfully',
                 'data' => [
                     'name' => 'Contractual Channel Test',
                     'is_active' => true,
                 ]
             ]);

    $this->assertDatabaseHas('channel_wise_employments', [
        'name' => 'Contractual Channel Test',
    ]);
});

it('fails validation when creating with duplicate name', function () {
    ChannelWiseEmployment::create(['name' => 'Duplicate Channel Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                     ->postJson('/api/v1/channel-wise-employments', [
                         'name' => 'Duplicate Channel Test',
                     ]);

    $response->assertStatus(422)
             ->assertJsonStructure([
                 'message',
                 'errors',
             ]);
});

it('can get active list of channel wise employments', function () {
    ChannelWiseEmployment::create(['name' => 'Active Channel Test', 'is_active' => true]);
    ChannelWiseEmployment::create(['name' => 'Inactive Channel Test', 'is_active' => false]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                     ->getJson('/api/v1/channel-wise-employments/list');

    $response->assertStatus(200)
             ->assertJson(['status' => 'success']);

    $data = $response->json('data');
    expect(collect($data)->pluck('name')->contains('Active Channel Test'))->toBeTrue();
    expect(collect($data)->pluck('name')->contains('Inactive Channel Test'))->toBeFalse();
});

it('can toggle channel wise employment status', function () {
    $employment = ChannelWiseEmployment::create(['name' => 'Toggle Status Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                     ->patchJson("/api/v1/channel-wise-employments/{$employment->id}/toggle-status");

    $response->assertStatus(200)
             ->assertJson([
                 'status' => 'success',
                 'data' => [
                     'id' => $employment->id,
                     'is_active' => false,
                 ]
             ]);

    expect($employment->fresh()->is_active)->toBeFalse();
});

it('can update a channel wise employment', function () {
    $employment = ChannelWiseEmployment::create(['name' => 'Initial Name Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                     ->putJson("/api/v1/channel-wise-employments/{$employment->id}", [
                         'name' => 'Updated Name Test',
                     ]);

    $response->assertStatus(200)
             ->assertJson([
                 'status' => 'success',
                 'data' => [
                     'name' => 'Updated Name Test',
                 ]
             ]);
});

it('can delete a channel wise employment', function () {
    $employment = ChannelWiseEmployment::create(['name' => 'To Delete Test', 'is_active' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                     ->deleteJson("/api/v1/channel-wise-employments/{$employment->id}");

    $response->assertStatus(200)
             ->assertJson([
                 'status' => 'success',
             ]);

    $this->assertDatabaseMissing('channel_wise_employments', [
        'id' => $employment->id,
    ]);
});
