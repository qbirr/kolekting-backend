<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'nama_lengkap' => 'Superadmin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('admin123'),
            'status' => 'Admin',
            'foto' => 'public/foto_profil/default.png',
            'foto_ktp' => 'public/foto_ktp/default.png',
            'no_hp' => '081234567890',
            'nik' => 'admin@gmail.com',
            'provinsi' => 'Jambi',
            'kabupaten' => 'Jambi',
            'kecamatan' => 'Danau Teluk',
            'kelurahan' => 'Pasir Panjang',
            'rt' => '001',
            'rw' => '001',
            'lrg' => '-',
            'kode_referal' => 'admin@gmail.com',
        ]);
    }
}
