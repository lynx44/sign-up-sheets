<?php
/**
* Export Sign-up Sheets to a File
*/
require_once '../../../wp-load.php';

if (!class_exists('DLS_SUS_Data')) require_once 'data.php';

class DLS_SUS_Export
{
    
    public $wpdb;
    private $data;
    
    public function __construct()
    {
        if (!current_user_can('manage_signup_sheets'))  {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->data = new DLS_SUS_Data();
    }
    
    /**
     * Create export file with data
     */
    public function create()
    {
        $data = $this->data->get_all_data();
        
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=sign-up-sheets-".date('Ymd-His').".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        $csv = '"Sheet ID","Sheet Title","Sheet Date","Task ID","Task Title","Sign-up ID","Sign-up First Name","Sign-up Last Name","Sign-up Phone","Sign-up Email"'."\n";
        foreach ($data as $d) {
            $csv .= '"' . 
                $this->clean_csv($d->sheet_id) . '","' . 
                $this->clean_csv($d->sheet_title) . '","' . 
                $this->clean_csv($d->sheet_date) . '","' . 
                $this->clean_csv($d->task_id) . '","' . 
                $this->clean_csv($d->task_title) . '","' . 
                $this->clean_csv($d->signup_id) . '","' . 
                $this->clean_csv($d->firstname) . '","' . 
                $this->clean_csv($d->lastname) . '","' . 
                $this->clean_csv($d->phone) . '","' . 
                $this->clean_csv($d->email).'"'.
            "\n";
        }
        echo $csv;
    }
    
    /**
     * Clean/escape CSV values
     * 
     * @param   string   input value
     * @return  string   cleaned value
     */
    private function clean_csv($value)
    {
        return str_replace('"', '""', $value);
    }
    
}
$e = new DLS_SUS_Export();
$e->create();