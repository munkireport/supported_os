<?php

use CFPropertyList\CFPropertyList;
use munkireport\lib\Request;

class Supported_os_model extends \Model
{
    public function __construct($serial = '')
    {
        parent::__construct('id', 'supported_os'); // Primary key, tablename
        $this->rs['id'] = '';
        $this->rs['serial_number'] = $serial;;
        $this->rs['current_os'] = 0;
        $this->rs['highest_supported'] = 0;
        $this->rs['machine_id'] = null;
        $this->rs['last_touch'] = 0;
        $this->rs['shipping_os'] = null;

        if ($serial) {
            $this->retrieve_record($serial);
        }

        $this->serial_number = $serial;

        // Add local config
        configAppendFile(__DIR__ . '/config.php');
    }

    // ------------------------------------------------------------------------

    /**
     * Process method, is called by the client
     *
     * @return void
     * @author tuxudo
     **/
    public function process($data)
    {
        // Check if we have data
        if ( ! $data){
            throw new Exception("Error Processing Request: No property list found", 1);
        }

        // Check if we have cached supported OS YAML
        $cached_data_time = munkireport\models\Cache::select('value')
                        ->where('module', 'supported_os')
                        ->where('property', 'last_update')
                        ->value('value');

        // Get the current time
        $current_time = time();

        // Check if we have a null result or a week has passed
        if($cached_data_time == null || ($current_time > ($cached_data_time + 604800))){

            // Get YAML from supported_os GitHub repo
            $web_request = new Request();
            $options = ['http_errors' => false];
            $yaml_result = (string) $web_request->get('https://raw.githubusercontent.com/munkireport/supported_os/master/supported_os_data.yml', $options);

            // Check if we got results
            if (strpos($yaml_result, 'current_os: ') === false ){
                error_log("Unable to fetch new YAML from supported_os GitHub page!! Using local version instead. ");
                // print_r("Unable to fetch new YAML from supported_os GitHub page!! Using local version instead. ");
                $yaml_result = file_get_contents(__DIR__ . '/supported_os_data.yml');
                $cache_source = 2;
            } else {
                // print_r("Updating cache file from GitHub page. ");
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
        } else {

            // Retrieve cached YAML from database
            $yaml_result = munkireport\models\Cache::select('value')
                            ->where('module', 'supported_os')
                            ->where('property', 'yaml')
                            ->value('value');
        }

        // Decode YAML
        $yaml_data = (object) Symfony\Component\Yaml\Yaml::parse($yaml_result);
        $yaml_data = json_decode(json_encode($yaml_data), TRUE);
        $highest_os = $yaml_data['highest'];
        $shipping_os = $yaml_data['shipping'];
        $most_current_os = $yaml_data['current_os'];

        // Store existing current_os value
        if(empty($this->rs['current_os'])){
            $stored_current_os = null;
        } else {
            $stored_current_os = $this->rs['current_os'];
        }

        // Check if we are processing a plist or not
        if(!is_array($data)){
            $parser = new CFPropertyList();
            $parser->parse($data);     
            $plist = $parser->toArray();

            $this->rs['machine_id'] = $plist['machine_id'];
            $this->rs['current_os'] = $plist['current_os'];
            $this->rs['last_touch'] = $plist['last_touch'];

        } else if($data['reprocess']){
            $this->retrieve_record($data['serial_number']);
            $this->rs['serial_number'] = $data['serial_number'];
            $this->rs['machine_id'] = $data['machine_id'];
            $this->rs['current_os'] = $data['current_os'];
            $this->rs['last_touch'] = $current_time;
        }

        $model_family = preg_replace("/[^A-Za-z]/", "", $this->rs['machine_id']);
        $model_num = preg_replace("/[^0-9]/", "", $this->rs['machine_id']);

        // Process model ID for highest_supported
        if (array_key_exists($model_family, $highest_os)) {
            // Sort the model ID numbers
            krsort($highest_os[$model_family]);

            // Process each model ID number in the model ID family
            foreach($highest_os[$model_family] as $model_check=>$model_os){

                // Compare model ID number to supported OS array, highest first
                if ($model_num >= $model_check){

                    // If supported OS is zero, set it to the current OS key from YAML
                    if($model_os == 0){
                        $model_os = $most_current_os;
                    }
                    $this->rs['highest_supported'] = $model_os;
                    break;
                }
            }

        } else {
            // Error out if we cannot locate that machine.
            error_log("Machine model '".$this->rs['machine_id']."' not found in highest supported array. ");
        }

        // Convert highest_supported to int
        if (isset($this->rs['highest_supported'])) {
            $digits = explode('.', $this->rs['highest_supported']);
            $mult = 10000;
            $this->rs['highest_supported'] = 0;
            foreach ($digits as $digit) {
                $this->rs['highest_supported'] += $digit * $mult;
                $mult = $mult / 100;
            }
        }

        // Set default highest_supported value
        if(empty($this->rs['highest_supported'])){
            $this->rs['highest_supported'] = null;
        }

        // Process model ID for shipping_os
        if (array_key_exists($model_family, $shipping_os)) {
            // Sort the model ID numbers
            krsort($shipping_os[$model_family]);

            // Process each model ID number in the model ID family
            foreach($shipping_os[$model_family] as $model_check=>$model_os){

                // Compare model ID number to shipping OS array, highest first
                if ($model_num >= $model_check){
                    $this->rs['shipping_os'] = $model_os;
                    break;
                }
            }

        } else {
            // Error out if we cannot locate that machine.
            error_log("Machine model '".$this->rs['machine_id']."' not found in shipping os array. ");
        }

        // Convert shipping_os to int
        if (isset($this->rs['shipping_os'])) {
            $digits = explode('.', $this->rs['shipping_os']);
            $mult = 10000;
            $this->rs['shipping_os'] = 0;
            foreach ($digits as $digit) {
                $this->rs['shipping_os'] += $digit * $mult;
                $mult = $mult / 100;
            }
        }

        // Set default shipping_os value
        if(empty($this->rs['shipping_os'])){
            $this->rs['shipping_os'] = null;
        }

        // Convert current_os to int
        if (isset($this->rs['current_os']) && !is_array($data)) {
            $digits = explode('.', $this->rs['current_os']);
            $mult = 10000;
            $this->rs['current_os'] = 0;
            foreach ($digits as $digit) {
                $this->rs['current_os'] += $digit * $mult;
                $mult = $mult / 100;
            }
        }

        // Set default current_os value
        if(empty($this->rs['current_os'])){
            $this->rs['current_os'] = null;
        }

        // Save OS gibblets
        $this->save();

        // Trigger updated macOS event if not nulls and 'supported_os_show_macos_updated' config is set to true
       if(conf('supported_os_show_macos_updated') && ! is_null($stored_current_os) && ! is_null($this->rs['current_os'])){
            // and previous version of macOS is different than new version of macOS
            if (intval($stored_current_os) !== intval($this->rs['current_os'])){
                $this->_storeEvents($stored_current_os, $this->rs['current_os']);
            }
        }

        // Return something if reprocessing
        if(is_array($data)){
            return true;
        }
    } // End process()

    // Process events
    private function _storeEvents($old_version, $new_version)
    {
        $old_version_array = str_split($old_version, 2);
        $old_version_string = $old_version_array[0].".".intval($old_version_array[1]).".".intval($old_version_array[2]);

        $new_version_array = str_split($new_version, 2);
        $new_version_string = $new_version_array[0].".".intval($new_version_array[1]).".".intval($new_version_array[2]);

        $msg = $old_version_string . ' → ' . $new_version_string;
        store_event($this->rs['serial_number'], "macOS updated", 'success', $msg);
    }
}
