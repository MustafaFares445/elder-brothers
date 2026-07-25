<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ApiContractTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void { parent::setUp();$this->seed(DatabaseSeeder::class); }
 public function test_health_endpoint(): void {$this->getJson('/api/health')->assertOk()->assertJsonPath('code','HEALTHY');}
 public function test_verified_user_can_login(): void {$this->postJson('/api/v1/auth/login',['phone'=>'+963900000001','password'=>'Password123!'])->assertOk()->assertJsonStructure(['success','code','message','data'=>['token_type','access_token','user'=>['id','full_name','phone']]]);}
 public function test_suspended_user_cannot_login(): void {$this->postJson('/api/v1/auth/login',['phone'=>'+963900000003','password'=>'Password123!'])->assertForbidden()->assertJsonPath('code','ACCOUNT_SUSPENDED');}
 public function test_home_returns_real_seeded_catalog(): void {$u=User::where('phone','+963900000001')->first();$this->actingAs($u)->getJson('/api/v1/home')->assertOk()->assertJsonCount(4,'data.academic_years')->assertJsonPath('data.academic_years.0.title','السنة الأولى');}
 public function test_qr_preview_returns_course(): void {$u=User::where('phone','+963900000002')->first();$this->actingAs($u)->postJson('/api/v1/subscriptions/qr/preview',['code'=>'ELDER-PHYSICS-2026-GROUP'])->assertOk()->assertJsonPath('code','QR_VALID')->assertJsonPath('data.valid',true);}
}
