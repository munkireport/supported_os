<?php

use munkireport\lib\Request;

/**
 * Supported_os module class
 *
 * @package munkireport
 * @author AvB
 **/
class Supported_os_controller extends Module_controller
{
    public function __construct()
    {
        // Store module path
        $this->module_path = dirname(__FILE__);
    }

    public function index()
    {
        echo "You've loaded the supported_os module!";
    }
    
    public function admin()
    {
        $obj = new View();
        $obj->view('supported_os_admin', [], $this->module_path.'/views/');
    }

    /**
     * Return json array with os breakdown
     *
     * @author AvB, tweaked by tuxudo
     **/
    public function os()
    {
        $out = array();
        $machine = new Supported_os_model();
        $sql = "SELECT count(1) as count, highest_supported
				FROM supported_os
				LEFT JOIN reportdata USING (serial_number)
				WHERE ".get_machine_group_filter('')."
				AND highest_supported > 0
				GROUP BY highest_supported
				ORDER BY highest_supported DESC";

        foreach ($machine->query($sql) as $obj) {
            $obj->highest_supported = $obj->highest_supported ? $obj->highest_supported : '0';
            $out[] = array('label' => $obj->highest_supported, 'count' => intval($obj->count));
        }

        jsonView($out);
    }
    
    /**
     * Force data pull from supported_os GitHub
     *
     * @return void
     * @author tuxudo
     **/
    public function update_cached_data()
    {
        $queryobj = new Supported_os_model();

        // Get YAML from supported_os GitHub
        $web_request = new Request();
        $options = ['http_errors' => false];
        $yaml_result = (string) $web_request->get('https://raw.githubusercontent.com/munkireport/supported_os/master/supported_os_data.yml', $options);

        // Check if we got results
        if (strpos($yaml_result, 'current_os: ') === false ){
            $yaml_result = file_get_contents(__DIR__ . '/supported_os_data.yml');
            $return_status = 2;
            $cache_source = 2;
        } else {
            $return_status = 1;
            $cache_source = 1;
        }

        $yaml_data = (object) Symfony\Component\Yaml\Yaml::parse($yaml_result);
        $current_os = $yaml_data->current_os;
        $digits = explode('.', $current_os);
        $mult = 10000;
        $current_os = 0;
        foreach ($digits as $digit) {
            $current_os += $digit * $mult;
            $mult = $mult / 100;
        }

        // Get the current time
        $current_time = time();
        
        // Save new cache data to the cache table
        munkireport\models\Cache::updateOrCreate(
            [
                'module' => 'supported_os', 
                'property' => 'yaml',
            ],[
                'value' => $yaml_result,
                'timestamp' => $current_time,
            ]
        );
        munkireport\models\Cache::updateOrCreate(
            [
                'module' => 'supported_os', 
                'property' => 'source',
            ],[
                'value' => $cache_source,
                'timestamp' => $current_time,
            ]
        );
        munkireport\models\Cache::updateOrCreate(
            [
                'module' => 'supported_os', 
                'property' => 'current_os',
            ],[
                'value' => $current_os,
                'timestamp' => $current_time,
            ]
        );
        munkireport\models\Cache::updateOrCreate(
            [
                'module' => 'supported_os', 
                'property' => 'last_update ',
            ],[
                'value' => $current_time,
                'timestamp' => $current_time,
            ]
        );
        
        // Send result
        $out = array("status"=>$return_status,"source"=>$cache_source,"timestamp"=>$current_time,"current_os"=>$current_os);
        jsonView($out);
    }

     /**
     * Pull in supported os data for all serial numbers :D
     *
     * @return void
     * @author tuxudo
     **/
    public function pull_all_supported_os_data($incoming_serial = '')
    {
        // Check if we are returning a list of all serials or processing a serial
        // Returns either a list of all serial numbers in MunkiReport OR
        // a JSON of what serial number was just ran with the status of the run

        // Remove non-serial number characters
        $incoming_serial = preg_replace("/[^A-Za-z0-9_\-]]/", '', $incoming_serial);

        if ( $incoming_serial == ''){
            // Get all the serial numbers in an object
            $machine = new Supported_os_model();
            $filter = get_machine_group_filter();

            $sql = "SELECT machine.serial_number
                        FROM machine
                        LEFT JOIN reportdata USING (serial_number)
                        $filter";

            // Loop through each serial number for processing
            $out = array();
            foreach ($machine->query($sql) as $serialobj) {
                $out[] = $serialobj->serial_number;
            }

            // Send result
            jsonView($out);

        } else {

            // Get machine model
            $machine = new Supported_os_model();
            $sql = "SELECT machine.machine_model, machine.os_version
                        FROM machine
                        WHERE serial_number = '".$incoming_serial."'";

            $data = [];
            $data["machine_id"] = $machine->query($sql)[0]->machine_model;
            $data["current_os"] = $machine->query($sql)[0]->os_version;
            $data["serial_number"] = $incoming_serial;
            $data["reprocess"] = true;

            // Process the serial in the model
            $machine = new Supported_os_model();

            // Send result
            jsonView($machine->process($data));
        }
    }
    
    /**
     * Reprocess serial number
     *
     * @return void
     * @author tuxudo
     **/
    public function recheck_highest_os($serial)
    {   
        // Remove non-serial number characters
        $serial = preg_replace("/[^A-Za-z0-9_\-]]/", '', $serial);

        // Process the serial in the model
        $machine = new Supported_os_model();

        $sql = "SELECT machine.machine_model, machine.os_version
                        FROM machine
                        WHERE serial_number = '".$serial."'";

        $data = [];
        $data["machine_id"] = $machine->query($sql)[0]->machine_model;
        $data["current_os"] = $machine->query($sql)[0]->os_version;
        $data["serial_number"] = $serial;
        $data["reprocess"] = true;

        $machine->process($data);

        // Send people back to the client tab once serial is reprocessed
        redirect("clients/detail/$serial#tab_supported_os-tab");
    }

    /**
     * Return JSON with information for admin page
     *
     * @return void
     * @author tuxudo
     **/
    public function get_admin_data()
    {
        $current_os = munkireport\models\Cache::select('value')
                        ->where('module', 'supported_os')
                        ->where('property', 'current_os')
                        ->value('value');
        $source = munkireport\models\Cache::select('value')
                        ->where('module', 'supported_os')
                        ->where('property', 'source')
                        ->value('value');
        $last_update = munkireport\models\Cache::select('value')
                        ->where('module', 'supported_os')
                        ->where('property', 'last_update')
                        ->value('value');

        $out = array('current_os' => $current_os,'source' => $source,'last_update' => $last_update);
        jsonView($out);
    }

    /**
     * Retrieve data in json format
     *
     **/
    public function get_data($serial_number = '')
    {
        $os = new Supported_os_model($serial_number);        
        jsonView($os->rs);
    }
} // End class Supported_os_controller
