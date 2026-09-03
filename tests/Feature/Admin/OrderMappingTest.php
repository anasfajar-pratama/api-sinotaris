<?php

namespace Tests\Feature\Admin;

use App\Models\ActorType;
use App\Models\AssetType;
use App\Models\DocumentCatalog;
use App\Models\DocumentType;
use App\Models\DocumentTypeActor;
use App\Models\DocumentTypeActorDocument;
use App\Models\DocumentTypeActorField;
use App\Models\DocumentTypeAsset;
use App\Models\DocumentTypeStage;
use App\Models\ProfileField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $staffUser;
    private DocumentType $documentType;
    private ActorType $actorType;
    private ProfileField $profileField;
    private DocumentCatalog $documentCatalog;
    private AssetType $assetType;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']);

        $adminRole = Role::findByName('super-admin', 'web');
        $adminRole->syncPermissions(['settings.view', 'settings.edit']);

        $staffRole = Role::findByName('staff', 'web');
        $staffRole->syncPermissions(['settings.view']);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);
        $this->adminUser->assignRole('super-admin');

        $this->staffUser = User::factory()->create([
            'name' => 'Staff User',
            'email' => 'staff@test.com',
        ]);
        $this->staffUser->assignRole('staff');

        $this->documentType = DocumentType::create([
            'name' => 'Test Document',
            'slug' => 'test-document',
            'sla_days' => 14,
        ]);

        $this->actorType = ActorType::create([
            'key' => 'buyer',
            'label' => 'Pembeli',
        ]);

        $this->profileField = ProfileField::create([
            'key' => 'full_name',
            'label' => 'Nama Lengkap',
            'data_type' => 'text',
        ]);

        $this->documentCatalog = DocumentCatalog::create([
            'key' => 'ktp',
            'label' => 'KTP',
            'category' => 'identity',
        ]);

        $this->assetType = AssetType::create([
            'key' => 'land',
            'label' => 'Tanah',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_order_mapping(): void
    {
        $response = $this->postJson('/api/v1/admin/settings/order-mapping', [
            'type_id' => $this->documentType->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_settings_edit_permission_gets_403(): void
    {
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', [
                'type_id' => $this->documentType->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_missing_type_id_returns_422(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type_id']);
    }

    public function test_invalid_type_id_returns_422(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', [
                'type_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type_id']);
    }

    public function test_super_admin_can_save_order_mapping_with_actors(): void
    {
        $payload = [
            'type_id' => $this->documentType->id,
            'actors' => [
                [
                    'actor_type_key' => 'buyer',
                    'is_required' => true,
                    'label_override' => 'Pembeli Utama',
                    'fields' => [
                        [
                            'profile_field_key' => 'full_name',
                            'is_required' => true,
                        ],
                    ],
                    'documents' => [
                        [
                            'document_catalog_key' => 'ktp',
                            'is_required' => true,
                        ],
                    ],
                ],
            ],
            'assets' => [],
            'stages' => [],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Mapping order berhasil disimpan',
            ]);

        $this->assertDatabaseHas('document_type_actors', [
            'document_type_id' => $this->documentType->id,
            'actor_type_id' => $this->actorType->id,
            'is_required' => true,
            'label_override' => 'Pembeli Utama',
        ]);

        $this->assertDatabaseHas('document_type_actor_fields', [
            'document_type_id' => $this->documentType->id,
            'actor_type_id' => $this->actorType->id,
            'profile_field_id' => $this->profileField->id,
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('document_type_actor_documents', [
            'document_type_id' => $this->documentType->id,
            'actor_type_id' => $this->actorType->id,
            'document_catalog_id' => $this->documentCatalog->id,
            'is_required' => true,
        ]);
    }

    public function test_super_admin_can_save_order_mapping_with_assets(): void
    {
        $payload = [
            'type_id' => $this->documentType->id,
            'actors' => [],
            'assets' => [
                [
                    'asset_type_key' => 'land',
                    'is_required' => true,
                ],
            ],
            'stages' => [],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('document_type_assets', [
            'document_type_id' => $this->documentType->id,
            'asset_type_id' => $this->assetType->id,
            'is_required' => true,
        ]);
    }

    public function test_super_admin_can_save_order_mapping_with_stages(): void
    {
        $payload = [
            'type_id' => $this->documentType->id,
            'actors' => [],
            'assets' => [],
            'stages' => [
                ['stage_name' => 'Verifikasi Dokumen'],
                ['stage_name' => 'Pembuatan Akta'],
                ['stage_name' => 'Penandatanganan'],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('document_type_stages', [
            'document_type_id' => $this->documentType->id,
            'stage_number' => 1,
            'stage_name' => 'Verifikasi Dokumen',
        ]);

        $this->assertDatabaseHas('document_type_stages', [
            'document_type_id' => $this->documentType->id,
            'stage_number' => 2,
            'stage_name' => 'Pembuatan Akta',
        ]);

        $this->assertDatabaseHas('document_type_stages', [
            'document_type_id' => $this->documentType->id,
            'stage_number' => 3,
            'stage_name' => 'Penandatanganan',
        ]);
    }

    public function test_order_mapping_removes_old_data_on_update(): void
    {
        DocumentTypeActor::create([
            'document_type_id' => $this->documentType->id,
            'actor_type_id' => $this->actorType->id,
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $payload = [
            'type_id' => $this->documentType->id,
            'actors' => [],
            'assets' => [],
            'stages' => [],
        ];

        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', $payload)
            ->assertStatus(200);

        $this->assertDatabaseMissing('document_type_actors', [
            'document_type_id' => $this->documentType->id,
            'actor_type_id' => $this->actorType->id,
        ]);
    }

    public function test_put_method_not_accepted_for_order_mapping(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson('/api/v1/admin/settings/order-mapping/' . $this->documentType->id, [
                'actors' => [],
                'assets' => [],
                'stages' => [],
            ]);

        $response->assertStatus(404);
    }

    public function test_post_endpoint_exists_without_type_id_in_url(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/settings/order-mapping', [
                'type_id' => $this->documentType->id,
                'actors' => [],
                'assets' => [],
                'stages' => [],
            ]);

        $response->assertStatus(200);
    }
}
