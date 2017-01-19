<?php namespace Oca\LeadScraper\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateOcaLeadscraperScrapelog extends Migration
{
    public function up()
    {
        Schema::create('oca_leadscraper_scrapelog', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->text('specialty');
            $table->text('initiated_by'); // auto, manual
            $table->text('type'); //Full, past 7 days, past 30 days
            $table->text('status'); //pending, running, error, complete - new leads, complete - no new
            $table->integer('lead_year');
            $table->integer('lead_source');
            $table->integer('number_at_start');
            $table->integer('number_of_completed');
            $table->integer('number_of_duplicates');
            $table->dateTime('time_start');
            $table->dateTime('time_completed');
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('oca_leadscraper_scrapelog');
    }
}