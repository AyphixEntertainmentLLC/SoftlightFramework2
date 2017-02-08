<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Globals {
    public function __construct() {
        // Get the CodeIgniter instance to use the internal classes
        $this->i =& get_instance();
        $this->properties = "";
        $this->init();
    }
    
    public function init() {
        
    }
    
    public function set($name, $value) {
        $exp = explode("->", $name);
        $last = null;
        if(count($exp) == 1) {
            $this->{$name} = $value;
        }else{
            $last = $this;
            $index = 0;
            foreach($exp as $var) {
                if($index !== count($exp)) {
                    $last->{$var} = new stdClass();
                }else{
                    $last->{$var} = $value;
                }
                $last = $last->{$var};
                ++$index;
            }
        }
    }
    
    public function get($name) {
        $exp = explode("->", $name);
        $last = null;
        if(count($exp) < 1) {
            return $this->{$name};
        }else{
            $last = $this;
            foreach($exp as $var) {
                $last = $last->{$var};
            }
        }
        
        return $last;
    }
    
    public function remove($name) {
        if($this->has($name)) {
            unset($this->{$name}); 
        }
    }
    
    public function has($name) {
        $exp = explode("->", $name);
        $last = null;
        if(count($exp) < 1) {
            if(isset($this->{$name})) {
                return true;
            } else{
                return false;
            }
        }else{
            $last = $this;
            foreach($exp as $var) {
                if(!isset($last->{$var})){
                    return false;
                }
                $last = $last->{$var};
            }
            
            return true;
        }
    }
}