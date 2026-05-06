<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Free — lifetime, tidak ada expired
        DB::table('plans')->updateOrInsert(
            ['id' => 'plan_free'],
            [
                'id'           => 'plan_free',
                'nama_plan'    => 'Free',
                'harga'        => 0,
                'batas_outlet' => 2,
                'durasi_hari'  => 0,          // ← 0 = tidak berlaku
                'is_lifetime'  => 1,          // ← selamanya
                'deskripsi'    => 'Gratis selamanya, maksimal 2 outlet',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        // Pro — ada masa aktif, default 90 hari (3 bulan)
        DB::table('plans')->updateOrInsert(
            ['id' => 'plan_pro'],
            [
                'id'           => 'plan_pro',
                'nama_plan'    => 'Pro',
                'harga'        => 299000,
                'batas_outlet' => -1,         // ← unlimited
                'durasi_hari'  => 90,         // ← 3 bulan default
                'is_lifetime'  => 0,
                'deskripsi'    => 'Unlimited outlet, berlaku 90 hari',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        $this->command->info('✅ PlanSeeder done: ' . DB::table('plans')->count() . ' plans');
    }
}