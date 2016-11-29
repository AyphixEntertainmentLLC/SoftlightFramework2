<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
    }

    public function index() {
        $this->skin->load_skin("default");
        $this->skin->load_page("standard");
        $this->skin->show($this);
    }
}