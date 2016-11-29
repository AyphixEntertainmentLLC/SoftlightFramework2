<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Script extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
    }

    public function index() {
        $this->scripting->tokenize("2.2+2=4.2");
    }
}