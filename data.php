<?php
/**
 * Database queries and actions
 */
class DLS_SUS_Data
{
    
    public $wpdb;
    public $tables = array();
    public $detailed_errors = false;
    
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Set table names
        $this->tables = array(
            'sheet' => array(
                'name' => $this->wpdb->prefix.'dls_sus_sheets',
                'allowed_fields' => array(
                    'title' => false,
                    'details' => false,
                    'date' => false,
                    'trash' => false,
                ),
            ),
            'task' => array(
                'name' => $this->wpdb->prefix.'dls_sus_tasks',
                'allowed_fields' => array(
                    'sheet_id' => false,
                    'title' => false,
                    'qty' => false,
                    'position' => false,
                ),
            ),
            'signup' => array(
                'name' => $this->wpdb->prefix.'dls_sus_signups',
                'allowed_fields' => array(
                    'task_id' => false,
                    'firstname' => false,
                    'lastname' => false,
                    'email' => false,
                    'phone' => false,
                ),
            ),
        );

    }
     
    /**
     * Get all Sheets
     * 
     * @param     bool     get just trash
     * @param     bool     get only active sheets or those without a set date
     * @return    mixed    array of sheets
     */
    public function get_sheets($trash=false, $active_only=false)
    {
        $results = $this->wpdb->get_results("
            SELECT * 
            FROM ".$this->tables['sheet']['name']." 
            WHERE trash = ".(($trash) ? "TRUE" : "FALSE")."
            ".(($active_only) ? " AND (date >= DATE_FORMAT(NOW(), '%Y-%m-%d') OR date = '0000-00-00')" : "")."
            ORDER BY date DESC, id DESC
        ");
        $results = $this->stripslashes_full($results);
        return $results;
    }
     
    /**
     * Get all Sheets
     * 
     * @return    mixed    array of sheets
     */
    public function get_sheet($id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->tables['sheet']['name']." WHERE id = %d" , $id));
        $results = $this->stripslashes_full($results);
        return $results[0];
    }
    
    /**
    * Get number of sheets
    */
    public function get_sheet_count($trash=false)
    { 
        $results = $this->wpdb->get_results("
            SELECT COUNT(*) AS count 
            FROM ".$this->tables['sheet']['name']." 
            WHERE trash = ".(($trash) ? "TRUE" : "FALSE")."
        ");
        $results = $this->stripslashes_full($results);
        return $results[0]->count;
    }
    
    /**
     * Get tasks by sheet
     * 
     * @param     int        id of sheet
     * @return    mixed    array of tasks
     */
    public function get_tasks($sheet_id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->tables['task']['name']." WHERE sheet_id = %d ORDER BY position, id" , $sheet_id));
        $results = $this->stripslashes_full($results);
        return $results;
    }
     
    /**
     * Get single task
     * 
     * @param     int      task id
     * @return    mixed    array of sheets
     */
    public function get_task($id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->tables['task']['name']." WHERE id = %d" , $id));
        $results = $this->stripslashes_full($results);
        return $results[0];
    }
    
    /**
     * Get signups by task
     * 
     * @param    int        id of task
     * @return    mixed    array of siginups
     */
    public function get_signups($task_id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->tables['signup']['name']." WHERE task_id = %d" , $task_id));
        $results = $this->stripslashes_full($results);
        return $results;
    }
    
    /**
     * Get all data
     *
     * @param   int|null    $sheet_id
     * @param   bool        $trash
     * @return  mixed       array of signups
     */
    public function get_all_data($sheet_id=null, $trash=false)
    {
        $results = $this->wpdb->get_results("
            SELECT
                sheet.id AS sheet_id
                , sheet.title AS sheet_title
                , sheet.details AS sheet_details
                , sheet.date AS sheet_date
                , sheet.trash AS sheet_trash
                , task.id AS task_id
                , task.title AS task_title
                , task.qty AS task_qty
                , task.position AS task_position
                , signup.id AS signup_id
                , firstname
                , lastname
                , email
                , phone
            FROM  ".$this->tables['sheet']['name']." sheet
            LEFT JOIN ".$this->tables['task']['name']." task ON sheet.id = task.sheet_id
            LEFT JOIN ".$this->tables['signup']['name']." signup ON task.id = signup.task_id
            AND sheet.trash = ".(($trash) ? "TRUE" : "FALSE")."
        ");
        $results = $this->stripslashes_full($results);
        return $results;
    }
    
    /**
    * Get number of signups on a specific sheet
    * 
    * @param    int    sheet id
    */
    public function get_sheet_signup_count($id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT COUNT(*) AS count FROM ".$this->tables['task']['name']." t
            RIGHT OUTER JOIN ".$this->tables['signup']['name']." s ON t.id = s.task_id 
            WHERE t.sheet_id = %d
        ", $id));
        return $results[0]->count;
    }
    
    /**
    * Get number of total spots on a specific sheet
    * 
    * @param    int    sheet id
    */
    public function get_sheet_total_spots($id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT SUM(qty) AS total FROM ".$this->tables['task']['name']." t
            WHERE t.sheet_id = %d
        ", $id));
        return $results[0]->total;
    }
    
    /**
     * Add a new sheet
     * 
     * @param    array    array of fields and values to insert
     * @return    mixed    false if insert fails
     */
    public function add_sheet($fields)
    {
        $clean_fields = $this->clean_array($fields, 'sheet_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['sheet']['allowed_fields']);
        if (isset($clean_fields['date'])) {
            if ($clean_fields['date'] == '') $clean_fields['date'] = '0000-00-00';
            if ($clean_fields['date'] != '0000-00-00') $clean_fields['date'] = date('Y-m-d', strtotime($clean_fields['date']));
        }
        $result = $this->wpdb->insert($this->tables['sheet']['name'], $clean_fields);
        if ($result === false) throw new DLS_SUS_Data_Exception('Error adding sheet.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
        return $result;
    }
    
    /**
     * Add a new task
     * 
     * @param    array    array of fields and values to insert
     * @param   int     sheet id
     * @return    mixed    false if insert fails
     */
    public function add_task($fields, $sheet_id)
    {
        $clean_fields = $this->clean_array($fields, 'task_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['task']['allowed_fields']);
        $clean_fields['sheet_id'] = $sheet_id;
        if ($clean_fields['qty'] < 2) $clean_fields['qty'] = 1;
        $result = $this->wpdb->insert($this->tables['task']['name'], $clean_fields);
        if ($result === false) throw new DLS_SUS_Data_Exception('Error adding task.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
        return $result;
    }
    
    /**
     * Add a new signup to a task
     * 
     * @param   array   array of fields and values to insert
     * @param   int     task id
     * @return  mixed   false if insert fails
     */
    public function add_signup($fields, $task_id)
    {
        $clean_fields = $this->clean_array($fields, 'signup_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['signup']['allowed_fields']);
        $clean_fields['task_id'] = $task_id;
        
        // Check if signup spots are filled
        $task = $this->get_task($task_id);
        $signups = $this->get_signups($task_id);
        if (count($signups) >= $task->qty) {
            throw new DLS_SUS_Data_Exception('Error adding signup.  All spots are filled.'. (($this->detailed_errors === true) ? ' Current Signups: '.count($signups).', Total Spots:'.$task->qty : ''));
            return false;
        }
        
        $result = $this->wpdb->insert($this->tables['signup']['name'], $clean_fields);
        if ($result === false) throw new DLS_SUS_Data_Exception('Error adding signup.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
        return $result;
    }
    
    /**
     * Update a sheet
     * 
     * @param    int        sheet id
     * @param    array     array of fields and values to update
     * @return    mixed    number of rows update or false if fails
     */
    public function update_sheet($fields, $id)
    {
        $clean_fields = $this->clean_array($fields, 'sheet_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['sheet']['allowed_fields']);
        if (isset($clean_fields['date'])) {
            if ($clean_fields['date'] == '') $clean_fields['date'] = '0000-00-00';
            if ($clean_fields['date'] != '0000-00-00') $clean_fields['date'] = date('Y-m-d', strtotime($clean_fields['date']));
        }
        $result = $this->wpdb->update($this->tables['sheet']['name'], $clean_fields, array('id' => $id), null, array('%d'));
        if ($result === false) throw new DLS_SUS_Data_Exception('Error updating sheet.'. (($this->detailed_errors === true) ? '... '.print_r(mysql_error(), true) : ''));
        return $result;
    }
    
    /**
     * Update a task
     * 
     * @param    int        task id
     * @param    array     array of fields and values to update
     * @return    mixed    number of rows update or false if fails
     */
    public function update_task($fields, $id)
    {
        // Clean Data
        $clean_fields = $this->clean_array($fields, 'task_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['task']['allowed_fields']);
        if ($clean_fields['qty'] < 2) $clean_fields['qty'] = 1;
        
        // Error Handling
        $signup_count = count($this->get_signups($id));
        if ($signup_count > $clean_fields['qty']) throw new DLS_SUS_Data_Exception('Could not update the number of people needed on task "'.$clean_fields['title'].'" to be "'.$clean_fields['qty'].'" because the number of signups is already "'.$signup_count.'".  You will need to clear spots before adjusting this number.');
        
        // Process
        $result = $this->wpdb->update($this->tables['task']['name'], $clean_fields, array('id' => $id), null, array('%d'));
        if ($result === false) throw new DLS_SUS_Data_Exception('Error updating task.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
        return $result;
    }
    
    /**
    * Delete a sheet and all associated tasks and signups
    * 
    * @param    int     sheet id
    */
    public function delete_sheet($id)
    {
        $tasks = $this->get_tasks($id);
        foreach ($tasks AS $task) {
            // Delete Signups
            if ($this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['signup']['name']." WHERE task_id = %d" , $task->id)) === false) {
                throw new DLS_SUS_Data_Exception('Error deleting signups from task #'.$task->id.' on sheet.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
                return false;
            }
        }
        // Delete Tasks
        if ($this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['task']['name']." WHERE sheet_id = %d" , $id)) === false) {
            throw new DLS_SUS_Data_Exception('Error deleting tasks on sheet.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
            return false;
        }
        // Delete Sheet
        if ($this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['sheet']['name']." WHERE id = %d" , $id)) === false) {
            throw new DLS_SUS_Data_Exception('Error deleting sheet.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
            return false;
        }
        return true;
    }
    
    /**
    * Delete a task
    * 
    * @param    int     task id
    */
    public function delete_task($id)
    {
        $result = $this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['task']['name']." WHERE id = %d" , $id));
        if ($result === false) throw new DLS_SUS_Data_Exception('Error deleting task.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
        return $result;
    }
    
    /**
    * Delete a signup
    * 
    * @param    int     signup id
    */
    public function delete_signup($id)
    {
        $result = $this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['signup']['name']." WHERE id = %d" , $id));
        if ($result === false) throw new DLS_SUS_Data_Exception('Error deleting signup.'. (($this->detailed_errors === true) ? '.. '.print_r(mysql_error(), true) : ''));
        return $result;
    }
    
    /**
    * Copy a sheet and all tasks to a new sheet for editing
    * 
    * @param    int     sheet id
    */
    public function copy_sheet($id)
    {
        $new_fields = array();
        
        $sheet = $this->get_sheet($id);
        $sheet = (array)$sheet;
        foreach ($this->tables['sheet']['allowed_fields'] AS $field=>$nothing) {
            $new_fields['sheet_'.$field] = $sheet[$field].(($field == 'title') ? " (Copy)" : "");
        }
        if ($this->add_sheet($new_fields) === false) return false;
        
        $new_sheet_id = $this->wpdb->insert_id;
        
        $tasks = $this->get_tasks($id);
        foreach ($tasks AS $task) {
            $new_fields = array();
            $task = (array)$task;
            foreach ($this->tables['task']['allowed_fields'] AS $field=>$nothing) {
                $new_fields['task_'.$field] = $task[$field];
            }
            if ($this->add_task($new_fields, $new_sheet_id) === false) return false;
        }
        
        return $new_sheet_id;
    }
    
    /**
    * Remove prefix from keys of an array and return records that were cleaned
    * 
    * @param    array   input array
    * @param    string  the prefix
    * @return   array   records that were cleaned
    */
    public function clean_array($input=array(), $prefix=false)
    {
        if (!is_array($input)) return false;
        $clean_fields = array();
        foreach ($input AS $k=>$v) {
            if ($prefix === false || (substr($k, 0, strlen($prefix)) == $prefix)) {
                $clean_fields[str_replace($prefix, '', $k)] = ($prefix == 'signup_') ? sanitize_text_field($v) : $v;
            }
        }
        return $clean_fields;
    }
    
    /**
    * Remove slashes from strings, arrays and objects
    * 
    * @param    mixed   input data
    * @return   mixed   cleaned input data
    */
    public function stripslashes_full($input)
    {
        if (is_array($input)) {
            $input = array_map(array('DLS_SUS_Data', 'stripslashes_full'), $input);
        } elseif (is_object($input)) {
            $vars = get_object_vars($input);
            foreach ($vars as $k=>$v) {
                $input->{$k} = $this->stripslashes_full($v);
            }
        } else {
            $input = stripslashes($input);
        }
        return $input;
    }
    
}

/**
 * Data Exception Class
 */
class DLS_SUS_Data_Exception extends Exception{}