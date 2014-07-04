<?php

/**
 * CLASS DATABASE
 *
 * @package LIBRARY
 * @author Caesare M. Morata
 * @version 0.3.1
 * @copyright Copyright (c) 2008-2010 Entertainment Gateway Group (http://www.egg.ph)
 *
 * @name class.database.php
 *
 * @editor Richard Neil Roque 
 * @date July 1, 2014
 */

class C_DB{
	
	
    private static $instance = NULL;
    private $db_sessions;
    private $con;
    private $db_name;
    private $q;
    private $result;
    private $total;
    private $error;

    private function __construct(){}

    public function __destruct(){} 

    private function __clone(){}

    public static function &get(){
        if (!self::$instance){
            self::$instance = new C_DB;
        }
        return self::$instance;
    }
	
    public function connect($db_label,$db_host,$db_user,$db_pwd="",$persistent=false){
        $this->con = $db_label;
        $this->db_sessions[$db_label] = mysql_connect($db_host,$db_user,$db_pwd, $persistent) or die("cannot create connection");
    }

    public function use_connection($con){
        $this->con = $con;
    }

    public function use_db($db_name){
        $this->db_name = $db_name;
        mysql_select_db($this->db_name,$this->db_sessions[$this->con]) or die (mysql_error($this->db_sessions[$this->con]));
    }
	
    public function command($q, $justshow=false){
        $this->q = $q;
        if($justshow){
            return $this->show();
        }else{
        	$this->set_result();
        }
    }	

    private function execute(){
        $con = $this->db_sessions[$this->con];
        $this->result = mysql_query($this->q,$con) or die( "[MYSQL ERROR]: ".mysql_error($con) );
    }
	
    public function select($table,$fields="*",$condition="",$justshow=false){
        $this->q = "select $fields from $table";
        if(!empty($condition)){
            $this->q .= " where $condition";
        }

        if($justshow){
            return $this->show();
        }else{
            return $this->set_result();
        }
    }

    public function insert($table,$data,$justshow=false){
        $p = "";
        $v = "";

        foreach ($data as $key => $value ){
            $p = empty($p) ? $key : $p.",".$key;
            $v = empty($v) ? "'".addslashes($value)."'" : $v.",'".addslashes($value)."'";
        }

	 	$this->q = "insert into $table($p) values ($v)";
	 	if($justshow){
             return $this->show();
        }else{
            return $this->set_result(1);
        }
    }

    public function update($table,$data,$condition="",$justshow=false){
        $this->q = "update ".$table." set ".$data;
        
        if(!empty($condition)){
            $this->q .= " where $condition";
        }

        if($justshow){
            return $this->show();
        }else{
            return $this->set_result(2);
        }				
    }

    public function delete($table,$condition){
        $this->q = "delete from $table" ;
        if(!empty($condition)){
            $this->q .= " where $condition";
            $this->execute();
            return true;
        }else{
            return false;
        }
    }
    
    private function set_result($type = 0){
        $this->execute();
        if($this->result){
            switch($type){
                case 2: //update
                    $this->total = mysql_affected_rows($this->db_sessions[$this->con]);
                    return $this->total;                     
                break;
                
                case 1: //insert
                    $this->total = mysql_affected_rows( $this->db_sessions[$this->con] );
                    return mysql_insert_id();
                break;
                
                default:
                    $this->total = mysql_num_rows($this->result);
                    return mysql_num_rows($this->result);              
                break;
            }
            
        }else{
            return $this->error;
        }              
    }
    
    public function get_total(){ 
        return $this->total; 
    }

    public function get_data(){
        return mysql_fetch_assoc($this->result);
    }

    public function close(){ 
        mysql_close($this->db_sessions[$this->con]);	
    }

    public function show(){	
        return $this->q;
    }
	
    public function get_fields(){
        $fields = array();
        $i=0;
        $total_fields = mysql_num_fields($this->result);
        while ($i < $total_fields) {
            $meta = mysql_fetch_field($this->result, $i);
            $fields[] = $meta->name;	
        }
        return $fields;
    }

    private function set_error($error){
        $this->error = $error;
    }
    
	public function get_array(){
    	$array = array();
    	$i = 0;
    	
		while($i < $this->total){
		   $array[] = $this->get_data();
		   $i++;
		}    	
    	
		return $array;
    }	
}