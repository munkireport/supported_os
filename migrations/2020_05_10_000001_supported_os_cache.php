<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class SupportedOsCache extends Migration
{
    private $tableName = 'supported_os';

    public function up()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->integer('shipping_os')->nullable();
            $table->text('model_support_cache')->nullable();
            
            $table->index('shipping_os');
        });
     }
    
    public function down()
    {
        $capsule = new Capsule();
        $capsule::schema()->table($this->tableName, function (Blueprint $table) {
            $table->dropColumn('model_support_cache');
            $table->dropColumn('shipping_os');
        });
    }
}
