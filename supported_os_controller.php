<?php

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
        $obj = new View();
        if (! $this->authorized()) {
            $obj->view('json', array('msg' => 'Not authorized'));
            return;
        }

        $out = array();
        $machine = new Supported_os_model();
        $sql = "SELECT count(1) as count, highest_supported
				FROM supported_os
				LEFT JOIN reportdata USING (serial_number)
                WHERE serial_number <> 'JSON_CACHE_DATA'
                ".get_machine_group_filter('AND')."
				GROUP BY highest_supported
				ORDER BY highest_supported DESC";

        foreach ($machine->query($sql) as $obj) {
            $obj->highest_supported = $obj->highest_supported ? $obj->highest_supported : '0';
            $out[] = array('label' => $obj->highest_supported, 'count' => intval($obj->count));
        }

        $obj = new View();
        $obj->view('json', array('msg' => $out));
    }
    
    /**
     * Force data pull from supported_os GitHub
     *
     * @return void
     * @author tuxudo
     **/
    public function update_cached_jsons()
    {
        // Authenticate
        $obj = new View();
        if (! $this->authorized()) {
            $obj->view('json', array('msg' => 'Not authorized'));
            return;
        }

        $queryobj = new Supported_os_model();

        // Get JSONs from supported_os GitHub
        ini_set("allow_url_fopen", 1);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'https://raw.githubusercontent.com/munkireport/supported_os/master/supported_os_data.json');
        $json_result = curl_exec($ch);

        // Check if we got results
        if (strpos($json_result, '"current_os":') === false ){
            $json_result = file_get_contents(__DIR__ . '/supported_os_data.json');
            $return_status = 2;
            $cache_source = 2;
        } else {
            $return_status = 1;
            $cache_source = 1;
        }

        // Delete old cached data
        $sql = "DELETE FROM `supported_os` WHERE serial_number = 'JSON_CACHE_DATA';";
        $queryobj->exec($sql);

        $json_data = json_decode($json_result);
        $current_os = $json_data->current_os;
        $digits = explode('.', $current_os);
        $mult = 10000;
        $current_os = 0;
        foreach ($digits as $digit) {
            $current_os += $digit * $mult;
            $mult = $mult / 100;
        }

        // Get the current time
        $current_time = time();

        // Insert new cached data
        $sql = "INSERT INTO `supported_os` (serial_number,current_os,highest_supported,machine_id,last_touch,shipping_os,model_support_cache) 
                    VALUES ('JSON_CACHE_DATA','".$current_os."','".$cache_source."','Do not delete this row','".$current_time."',0,'".$json_result."')";
        $queryobj->exec($sql);

        // Send result
        $out = array("status"=>$return_status,"source"=>$cache_source,"timestamp"=>$current_time);
        $obj->view('json', array('msg' => $out));
    }

     /**
     * Pull in supported os data for all serial numbers :D
     *
     * @return void
     * @author tuxudo
     **/
    public function pull_all_supported_os_data($incoming_serial = '')
    {
        $obj = new View();
        // Authenticate
        if (! $this->authorized()) {
            $obj->view('json', array('msg' => array('error' => 'Not authenticated')));
            return;
        }
        
        // Check if we are returning a list of all serials or processing a serial
        // Returns either a list of all serial numbers in MunkiReport OR
        // a JSON of what serial number was just ran with the status of the run
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

            $obj->view('json', array('msg' => $out));
        } else {

            $data = [];
            $data["serial_number"] = $incoming_serial;
            $data["reprocess"] = true;

            // Process the serial in the model
            $machine = new Supported_os_model();

            // Send result
            $obj->view('json', array('msg' => $machine->process($data)));
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
        // Authenticate
        if (! $this->authorized()) {
            die('Authenticate first.'); // Todo: return json?
        }
        
        $data = [];
        $data["serial_number"] = $serial;
        $data["reprocess"] = true;

        // Process the serial in the model
        $machine = new Supported_os_model();
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
        $obj = new View();
        if (! $this->authorized()) {
            $obj->view('json', array('msg' => 'Not authorized'));
            return;
        }

        $out = array();
        $machine = new Supported_os_model();
        $sql = "SELECT current_os, highest_supported, last_touch
				FROM supported_os
                WHERE serial_number = 'JSON_CACHE_DATA'";

        $out = $machine->query($sql);

        $obj = new View();
        $obj->view('json', array('msg' => $out));
    }

    /**
     * Retrieve data in json format
     *
     **/
    public function get_data($serial_number = '')
    {
        $obj = new View();

        if (! $this->authorized()) {
            $obj->view('json', array('msg' => 'Not authorized'));
            return;
        }

        $os = new Supported_os_model($serial_number);
        $obj->view('json', array('msg' => $os->rs));
    }
} // End class Supported_os_controller