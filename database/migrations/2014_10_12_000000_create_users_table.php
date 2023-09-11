<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->string('foto');
            $table->string('password');
            $table->string('email')->unique();
            $table->string('no_hp')->unique();
            $table->string('nik')->unique();
            $table->string('foto_ktp');
            $table->string('provinsi');
            $table->string('kabupaten');
            $table->string('kecamatan');
            $table->string('kelurahan');
            $table->string('rt');
            $table->string('rw');
            $table->string('lrg');
            $table->string('kode_referal')->unique()->nullable();
            $table->string('referal_dari')->nullable();
            $table->string('status')->default('Relawan');
            $table->string('password_reset_code')->nullable();
            $table->string('password_reset_token')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
