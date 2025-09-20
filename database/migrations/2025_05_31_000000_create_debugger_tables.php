<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debugs', function (Blueprint $table) {
            $table->id();
            $table->integer('line_number');
            $table->string('class_name');
            $table->nullableMorphs('debug');
            $table->timestamps();
        });

        Schema::create('texts', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('numbers', function (Blueprint $table) {
            $table->id();
            $table->decimal('number', 65, 8);
            $table->boolean('is_int')->default(false);
            $table->timestamps();
        });

        Schema::create('jsons', function (Blueprint $table) {
            $table->id();
            $table->json('json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debugs');
        Schema::dropIfExists('texts');
        Schema::dropIfExists('numbers');
        Schema::dropIfExists('jsons');
    }
};
