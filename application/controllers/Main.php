<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {
    
    public $social;
    
    public function __construct() {
        parent::__construct();
        $this->globals->set("social", $this->db->select("*")->from("social")->get()->result());
        $this->breadcrumb->reset("Home", "<span class='fa fa-angle-right'></span>");
    }

    public function index() {
    	$this->breadcrumb->add("News");
        $this->pages->call($this);
    }
}