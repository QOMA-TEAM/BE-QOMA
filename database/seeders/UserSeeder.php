<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan roles ada
        $roleCount = DB::table('roles')->count();
        if ($roleCount === 0) {
            $this->command->error('❌ Roles kosong! Jalankan RoleSeeder dulu.');
            return;
        }

        // ============================================================
        // 1. SUPER ADMIN
        // ============================================================
        $superAdminId = Str::uuid()->toString();
        DB::table('users')->updateOrInsert(
            ['username' => 'superadmin'],
            [
                'id'           => $superAdminId,
                'role_id'      => 'role_super_admin',
                'usaha_id'     => null,
                'outlet_id'    => null,
                'username'     => 'superadmin',
                'nama_lengkap' => 'Super Administrator',
                'email'        => 'superadmin@demo.com',
                'password'     => Hash::make('password123'),
                'is_active'    => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // ============================================================
        // 2. OWNER
        // ============================================================
        $ownerId = Str::uuid()->toString();
        DB::table('users')->updateOrInsert(
            ['username' => 'owner1'],
            [
                'id'           => $ownerId,
                'role_id'      => 'role_owner',
                'usaha_id'     => null, // diupdate setelah usaha dibuat
                'outlet_id'    => null,
                'username'     => 'owner1',
                'nama_lengkap' => 'Budi Santoso',
                'email'        => 'owner@demo.com',
                'password'     => Hash::make('password123'),
                'is_active'    => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // Ambil id owner yang baru/sudah ada
        $owner = DB::table('users')->where('username', 'owner1')->first();

        // ============================================================
        // 3. USAHA
        // ============================================================
        $usahaId = Str::uuid()->toString();
        DB::table('usaha')->updateOrInsert(
            ['nama_usaha' => 'Warung Barokah'],
            [
                'id'          => $usahaId,
                'nama_usaha'  => 'Warung Barokah',
                'owner_id'    => $owner->id,
                'email'       => 'warungbarokah@demo.com',
                'status'      => 'active',
                'approved_at' => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        // Ambil usaha_id yang baru/sudah ada
        $usaha = DB::table('usaha')->where('nama_usaha', 'Warung Barokah')->first();

        // Update owner dengan usaha_id
        DB::table('users')->where('username', 'owner1')
                          ->update(['usaha_id' => $usaha->id]);

        // ============================================================
        // 4. SUBSCRIPTION
        // ============================================================
        DB::table('subscriptions')->updateOrInsert(
            ['usaha_id' => $usaha->id],
            [
                'id'         => Str::uuid()->toString(),
                'usaha_id'   => $usaha->id,
                'plan_id'    => 'plan_pro',
                'start_date' => now()->toDateString(),
                'end_date'   => now()->addYear()->toDateString(),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // ============================================================
        // 5. OUTLET 1
        // ============================================================
        $outlet1Id = Str::uuid()->toString();
        DB::table('outlet')->updateOrInsert(
            ['nama_outlet' => 'Warung Barokah Pusat'],
            [
                'id'          => $outlet1Id,
                'usaha_id'    => $usaha->id,
                'nama_outlet' => 'Warung Barokah Pusat',
                'alamat'      => 'Jl. Merdeka No. 1, Semarang',
                'status_buka' => 1,
                'email' => 'pusat@warungbarokah.com',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $outlet1 = DB::table('outlet')->where('nama_outlet', 'Warung Barokah Pusat')->first();

        // ============================================================
        // 6. OUTLET 2
        // ============================================================
        $outlet2Id = Str::uuid()->toString();
        DB::table('outlet')->updateOrInsert(
            ['nama_outlet' => 'Warung Barokah Cabang'],
            [
                'id'          => $outlet2Id,
                'usaha_id'    => $usaha->id,
                'nama_outlet' => 'Warung Barokah Cabang',
                'alamat'      => 'Jl. Pemuda No. 45, Semarang',
                'status_buka' => 1,
                'email' => 'cabang@warungbarokah.com',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $outlet2 = DB::table('outlet')->where('nama_outlet', 'Warung Barokah Cabang')->first();

        // ============================================================
        // 7. USER OUTLET 1
        // ============================================================
        DB::table('users')->updateOrInsert(
            ['username' => 'outlet_pusat'],
            [
                'id'           => Str::uuid()->toString(),
                'role_id'      => 'role_outlet',
                'usaha_id'     => $usaha->id,
                'outlet_id'    => $outlet1->id,
                'username'     => 'outlet_pusat',
                'nama_lengkap' => 'Kasir Pusat',
                'email'        => 'outletpusat@demo.com',
                'password'     => Hash::make('password123'),
                'is_active'    => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // ============================================================
        // 8. USER OUTLET 2
        // ============================================================
        DB::table('users')->updateOrInsert(
            ['username' => 'outlet_cabang'],
            [
                'id'           => Str::uuid()->toString(),
                'role_id'      => 'role_outlet',
                'usaha_id'     => $usaha->id,
                'outlet_id'    => $outlet2->id,
                'username'     => 'outlet_cabang',
                'nama_lengkap' => 'Kasir Cabang',
                'email'        => 'outletcabang@demo.com',
                'password'     => Hash::make('password123'),
                'is_active'    => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // ============================================================
        // 9. OWNER PENDING (untuk testing approval)
        // ============================================================
        $ownerPendingId = Str::uuid()->toString();
        DB::table('users')->updateOrInsert(
            ['username' => 'owner_pending'],
            [
                'id'           => $ownerPendingId,
                'role_id'      => 'role_owner',
                'usaha_id'     => null,
                'outlet_id'    => null,
                'username'     => 'owner_pending',
                'nama_lengkap' => 'Candra Wijaya',
                'email'        => 'ownerpending@demo.com',
                'password'     => Hash::make('password123'),
                'is_active'    => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        $ownerPending = DB::table('users')->where('username', 'owner_pending')->first();

        $usahaPendingId = Str::uuid()->toString();
        DB::table('usaha')->updateOrInsert(
            ['nama_usaha' => 'Kedai Baru'],
            [
                'id'         => $usahaPendingId,
                'nama_usaha' => 'Kedai Baru',
                'owner_id'   => $ownerPending->id,
                'email'      => 'kedaibaru@demo.com',
                'status'     => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $usahaPending = DB::table('usaha')->where('nama_usaha', 'Kedai Baru')->first();
        DB::table('users')->where('username', 'owner_pending')
                          ->update(['usaha_id' => $usahaPending->id]);

        
        // ============================================================
        // 10. OWNER KEDUA (untuk test data isolation)
        // ============================================================
        $owner2Id = Str::uuid()->toString();
        DB::table('users')->updateOrInsert(
            ['username' => 'owner2'],
            [
                'id'           => $owner2Id,
                'role_id'      => 'role_owner',
                'usaha_id'     => null,
                'outlet_id'    => null,
                'username'     => 'owner2',
                'nama_lengkap' => 'Siti Rahayu',
                'password'     => Hash::make('password123'),
                'is_active'    => 1,
                'email'       => 'restositi@demo.com',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        $owner2 = DB::table('users')->where('username', 'owner2')->first();

        // Usaha owner2
        $usaha2Id = Str::uuid()->toString();
        DB::table('usaha')->updateOrInsert(
            ['nama_usaha' => 'Resto Siti Jaya'],
            [
                'id'          => $usaha2Id,
                'nama_usaha'  => 'Resto Siti Jaya',
                'owner_id'    => $owner2->id,
                'email'       => 'restositijaya@demo.com',
                'alamat'      => 'Jl. Gajahmada No. 10, Semarang',
                'status'      => 'active',
                'approved_at' => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $usaha2 = DB::table('usaha')->where('nama_usaha', 'Resto Siti Jaya')->first();

        // Update owner2 dengan usaha_id
        DB::table('users')->where('username', 'owner2')
                        ->update(['usaha_id' => $usaha2->id]);

        // Subscription owner2 (free trial)
        DB::table('subscriptions')->updateOrInsert(
            ['usaha_id' => $usaha2->id],
            [
                'id'         => Str::uuid()->toString(),
                'usaha_id'   => $usaha2->id,
                'plan_id'    => 'plan_free',
                'start_date' => now()->toDateString(),
                'end_date'   => now()->addDays(14)->toDateString(),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Outlet milik owner2
        $outlet3Id = Str::uuid()->toString();
        DB::table('outlet')->updateOrInsert(
            ['nama_outlet' => 'Resto Siti Jaya Pusat'],
            [
                'id'          => $outlet3Id,
                'usaha_id'    => $usaha2->id,
                'nama_outlet' => 'Resto Siti Jaya Pusat',
                'alamat'      => 'Jl. Gajahmada No. 10, Semarang',
                'status_buka' => 1,
                'email'       => 'restositi@demo.com',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $outlet3 = DB::table('outlet')->where('nama_outlet', 'Resto Siti Jaya Pusat')->first();

        // User outlet milik owner2
        DB::table('users')->updateOrInsert(
            ['username' => 'outlet_siti'],
            [
                'id'           => Str::uuid()->toString(),
                'role_id'      => 'role_outlet',
                'usaha_id'     => $usaha2->id,
                'outlet_id'    => $outlet3->id,
                'username'     => 'outlet_siti',
                'nama_lengkap' => 'Kasir Siti',
                'email'        => 'outletsiti@demo.com',
                'password'     => Hash::make('password123'),
                'is_active'    => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // ============================================================
        // SUMMARY
        // ============================================================
        $this->command->info('');
        $this->command->info('✅ UserSeeder selesai!');
        $this->command->table(
            ['Role', 'Username', 'Email', 'Password', 'Usaha', 'Plan'],
            [
                ['super_admin', 'superadmin',    'superadmin@demo.com',  'password123', '-',               '-'],
                ['owner',       'owner1',        'owner@demo.com',       'password123', 'Warung Barokah',  'Pro'],
                ['owner',       'owner2',        'restositi@demo.com',   'password123', 'Resto Siti Jaya', 'Free'],
                ['outlet',      'outlet_pusat',  'outletpusat@demo.com', 'password123', 'Warung Barokah',  '-'],
                ['outlet',      'outlet_cabang', 'outletcabang@demo.com','password123', 'Warung Barokah',  '-'],
                ['outlet',      'outlet_siti',   'outletsiti@demo.com',  'password123', 'Resto Siti Jaya', '-'],
                ['owner',       'owner_pending', 'ownerpending@demo.com','password123', 'Kedai Baru',      'pending'],
            ]
        );
    }
}