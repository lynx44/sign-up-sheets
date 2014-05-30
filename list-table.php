<?php
/**
* Class to create admin list tables
*/
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH.'wp-admin/includes/screen.php';
    require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}
if (!class_exists('DLS_SUS_Data')) require_once 'data.php';

class DLS_SUS_List_Table extends WP_List_Table
{
    
    private $data;
    private $rows = array();
    private $show_trash;
    
    /**
    * construct
    * 
    * @param    bool    show trash?
    * @return   DLS_SUS_List_Table
    */
    function __construct($show_trash)
    {
        global $status, $page;
        $this->show_trash = $show_trash;
        
        // Set data and convert to array
        $this->data = new DLS_SUS_Data();
        $rows = (array)$this->data->get_sheets($show_trash);
        foreach ($rows AS $k=>$v) {
            $this->rows[$k] = (array)$v;
        }
                
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'sheet',
            'plural'    => 'sheets',
            'ajax'      => false
        ) );
        
    }
    
    /**
    * Set data and convert an object into an array if neccessary
    * 
    * @param    mixed   object or array of data
    * @return   array   data
    */
    function set_data($data)
    {
        return (array)$data;
    }
    
    /**
    * Process columns if not defined in a specific column like column_title
    * 
    * @param    array   one row of data
    * @param    array   name of column to be processed
    * @return   string  text that will go in the column's TD
    */
    function column_default($item, $column_name){
        switch($column_name){
            case 'id':
                return $item[$column_name];
            case 'date':
                return ($item[$column_name] == '0000-00-00') ? 'N/A' : date('Y/m/d', strtotime($item[$column_name]));
            case 'task_num':
                return count($this->data->get_tasks($item['id']));
            case 'spot_num':
                return $this->data->get_sheet_total_spots($item['id']);
            case 'filled_spot_num':
                return $this->data->get_sheet_signup_count($item['id']).' '.(($this->data->get_sheet_total_spots($item['id']) == $this->data->get_sheet_signup_count($item['id'])) ? '&#10004;' : '');
            default:
                return print_r($item,true); // Show the whole array for troubleshooting purposes
        }
    }
    
    /**
    * Custom column title processer
    * 
    * @see      WP_List_Table::::single_row_columns()
    * @param    array   one row of data
    * @return   string  text that will go in the title column's TD
    */
    function column_title($item)
    {
        // Set actions
        if ($this->show_trash) {
            $actions = array(
                'untrash'   => sprintf('<a href="?page=%s&action=%s&sheet_id=%s">Restore</a>', $_REQUEST['page'], 'untrash', $item['id']),
                'delete'    => sprintf('<a href="?page=%s&action=%s&sheet_id=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['id']),
            );
        } else {
            $actions = array(
                'view_signup'   => sprintf('<a href="?page=%s&action=%s&sheet_id=%s">View Sign-ups</a>', $_REQUEST['page'], 'view_signup', $item['id']),
                'edit'          => sprintf('<a href="?page=%s&action=%s&sheet_id=%s">Edit</a>', 'dls-sus-settings_modify_sheet', 'edit', $item['id']),
                'copy'          => sprintf('<a href="?page=%s&action=%s&sheet_id=%s">Copy</a>', $_REQUEST['page'], 'copy', $item['id']),
                'trash'         => sprintf('<a href="?page=%s&action=%s&sheet_id=%s">Trash</a>', $_REQUEST['page'], 'trash', $item['id']),
            );
        }
        
        return sprintf('<strong><a href="?page=%1$s&action=view_signups&sheet_id=%2$s">%3$s</a></strong>%4$s', 
            $_REQUEST['page'], // %1$s
            $item['id'],  // %2$s
            $item['title'], // %3$s
            $this->row_actions($actions) // %4$s
        );
    }
    
    /**
    * Checkbox column method
    * 
    * @see      WP_List_Table::::single_row_columns()
    * @param    array   one row of data
    * @return   string  text that will go in the column's TD
    * @todo     finish
    */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'], // $1%s
            $item['id'] // $2%s
        );
    }
    
    /**
    * All columns
    */
    function get_columns()
    {
        $columns = array(
            'id'                => 'ID#',
            'title'             => 'Title',
            'date'              => 'Date',
            'task_num'          => '# of Tasks',
            'spot_num'          => 'Total Spots',
            'filled_spot_num'   => 'Filled Spots',
        );
        
        // Add checkbox if bulk actions is available
        if (count($this->get_bulk_actions()) > 0) {
            $columns = array_reverse($columns, true);
            $columns['cb'] = '<input type="checkbox" />';
            $columns = array_reverse($columns, true);
        }
        
        return $columns;
    }
    
    /**
    * All sortable columns
    */
    function get_sortable_columns()
    {
        $sortable_columns = array(
            'id'    => array('id',false),
            'title' => array('title',false),
            'date'  => array('date',true),
        );
        return $sortable_columns;
    }
    
    /**
    * All allowed bulk actions
    * 
    * @todo finish
    */
    function get_bulk_actions()
    {
        $actions = array();
        /*
        if ($this->show_trash) {
            $actions = array(
                'delete' => 'Delete'
            );
        } else {
            $actions = array(
                'trash' => 'Trash'
            );
        }
        */
        return $actions;
    }
    
    /**
    * Process bulk actions if called
    * 
    * @todo finish this
    */
    function process_bulk_action()
    {
        if('trash' === $this->current_action()) {
            //TODO
        } elseif ('delete' === $this->current_action()) {
            //TODO
        }
    }
    
    /**
    * Get data and prepare for use
    * 
    * @todo finish data
    */
    function prepare_items()
    {
        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();//TODO remove if no bulk actions

        // Sort Data
        function usort_reorder($a,$b)
        {
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'title'; // If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; // If no order, default to asc
            $result = strcmp($a[$orderby], $b[$orderby]); // Determine sort order
            return ($order === 'asc') ? $result : -$result; // Send final sort direction to usort
        }
        usort($this->rows, 'usort_reorder');
        
        $current_page = $this->get_pagenum();
        $total_items = count($this->rows);
        $this->rows = array_slice($this->rows,(($current_page-1)*$per_page),$per_page);
        $this->items = $this->rows;
        
        // Register pagination calculations
        $this->set_pagination_args( array(
            'total_items'   => $total_items,
            'per_page'      => $per_page,
            'total_pages'   => ceil($total_items/$per_page)
        ) );
    }
    
}