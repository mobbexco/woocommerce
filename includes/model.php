<?php

namespace Mobbex;

abstract class Model
{
    public $table;
    public $primary_key;

    /**
     * Instance the model and try to fill properties.
     * 
     * @param mixed ...$props
     */
    public function __construct(...$props)
    {
        //load the data from db
        $this->load($props);
        //load the params from construct
        $this->fill($props);
        
    }

    public function load($props)
    {
        if (empty($props[0]))
            return $this;

        //Get data from database
        global $wpdb;
        $query = "SELECT * FROM ".$wpdb->prefix.$this->table." WHERE ".$this->primary."=".$props[0];
        $params = $wpdb->get_results($query, 'ARRAY_A');

        if(!empty($params[0])){
            foreach ($params[0] as $key => $value) {
                $this->$key = $value;
            }

            return $this->update = true;
        }

        return $this->update = false;
    }

    /**
     * Fill properties to current model.
     * 
     * @param mixed $props
     */
    public function fill($props)
    {
        foreach ($props as $key => $value)
            if (isset($this->fillable[$key]))
                $this->{$this->fillable[$key]} = $value;
    }

    public function save($data)
    {
        global $wpdb;

        if($this->update){
            $wpdb->update($wpdb->prefix.$this->table, $data, [$this->primary => $data[$this->primary]]);
            return empty($wpdb->last_error) ? true : false;
        } else {
            return $wpdb->insert($wpdb->prefix.$this->table, $data, \MobbexHelper::db_column_format($data));
        }
    }
}