<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('denumire');
            $table->enum('tip_firma', ['SRL', 'SA', 'PFA', 'II', 'IF']);
            $table->string('cui')->unique();
            $table->string('nr_reg_comertului');
            $table->boolean('platitor_tva')->default(false);
            $table->string('adresa');
            $table->string('localitate');
            $table->string('judet');
            $table->string('tara')->default('România');
            $table->string('email');
            $table->string('telefon');
            $table->enum('tip_companie', ['client', 'furnizor', 'intermediar']);
            $table->boolean('activ')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
