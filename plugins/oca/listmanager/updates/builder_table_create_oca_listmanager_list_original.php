<?php namespace Oca\ListManager\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateOcaListmanagerListOriginal extends Migration
{
    public function up()
    {
        Schema::create('oca_listmanager_list_original', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('source', 255)->nullable()->default(null)->comment('PR, RD, KF');
            $table->integer('year')->nullable()->default(null);
            $table->integer('listServiceId')->nullable()->default(null);
            $table->string('name', 255)->nullable()->default(null);
            $table->string('firstname', 255)->nullable()->default(null);
            $table->string('lastname', 255)->nullable()->default(null);
            $table->string('specialty', 255)->nullable()->default(null);
            $table->text('address')->nullable()->default(null);
            $table->string('city', 255)->nullable()->default(null);
            $table->string('state', 255)->nullable()->default(null);
            $table->string('zip', 255)->nullable()->default(null);
            $table->string('email1', 255)->nullable()->default(null);
            $table->string('email2', 255)->nullable()->default(null);
            $table->string('cellPhone', 255)->nullable()->default(null);
            $table->string('homePhone', 255)->nullable()->default(null);
            $table->string('programPhone', 255)->nullable()->default(null);
            $table->string('hospitalPhone', 255)->nullable()->default(null);
            $table->string('pagePhone', 255)->nullable()->default(null);
            $table->string('citizenship', 255)->nullable()->default(null);
            $table->string('hometown', 255)->nullable()->default(null);
            $table->string('gender', 255)->nullable()->default(null);
            $table->string('children', 255)->nullable()->default(null);
            $table->string('maritalStatus', 255)->nullable()->default(null);
            $table->string('spouseName', 255)->nullable()->default(null);
            $table->string('spouseHometown', 255)->nullable()->default(null);
            $table->string('occupation', 255)->nullable()->default(null);
            $table->string('residency', 255)->nullable()->default(null);
            $table->string('fellowship', 255)->nullable()->default(null);
            $table->text('addlFellowshipPlans')->nullable()->default(null);
            $table->text('KFcurrentPosition')->nullable()->default(null)->comment('R or F');
            $table->text('KFavailable')->nullable()->default(null);
            $table->text('KFprogram')->nullable()->default(null);
            $table->timestamps();
        });
        
        Schema::create('oca_listmanager_list_datavalidation', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('source', 255);
            $table->text('address', 255)->nullable()->default(null);
            $table->string('grade', 2)->nullable()->default(null);
            $table->string('click', 2)->nullable()->default(null);
            $table->string('open', 2)->nullable()->default(null);
            $table->string('hard', 2)->nullable()->default(null);
            $table->string('optout', 2)->nullable()->default(null);
            $table->string('complain', 2)->nullable()->default(null);
            $table->string('trap', 2)->nullable()->default(null);
            $table->string('deceased', 2)->nullable()->default(null);
            $table->string('originalEmail', 255)->nullable()->default(null);
            $table->timestamps();

        });
    }
    
    public function down()
    {
        Schema::dropIfExists('oca_listmanager_list_original');
        Schema::dropIfExists('oca_listmanager_list_datavalidation');
    }
}