<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Block extends CI_Model {
    
    private $skin_name;
    
    public function __construct() {
        parent::__construct();
    }
    
    public function traverse($node, $skip = false) {
        if(!$skip) {
            $this->parse_node($node);
        }
        
        if(count($node) > 0) {
            foreach($node->children() as $child) {
                $this->traverse($child);
            }
        }
    }
    
    private function sl_hide($expr, $controller, $vars = array()) {
        return $this->scripting->evaluate($expr, $controller, $vars);
    }
    
    private function check_attributes($node) {
        // Does our node have Attributes we're looking for?
        if($node->hasAttribute("sl-init")) {
        	$expr = $node->getAttribute("sl-init");
			$node->removeAttribute("sl-init");
			
			$this->i->scripting->evaluate($expr, $this, null, false);
        }
                
        if($node->hasAttribute("sl-repeat")) {
            $exp = $node->getAttribute("sl-repeat");
            $node->removeAttribute("sl-repeat");
            $outer = $node->outertext;
            
            $expl = explode(" ", $exp);
            
            if(count($expl) <> 3) {
                show_error("Your SL-REPEAT attribute did not contain a valid expression: Invalid number of arguments", "0x0001");
            } 
            
            $var  = $expl[0];
            $prop = $expl[2];
            
            if(isset($this->{$prop}) || $this->i->globals->has($prop)) {
                // Get our property
                $arr = null;
                if(isset($this->{$prop})) {
                    $arr = $this->{$prop};
                } else if($this->i->globals->has($prop)) {
                    $arr = $this->i->globals->get($prop);
                } else {
                    var_dump($arr);
                    //show_error("The property " . $prop . " passed the initial check but could not be otherwise found.", "0x0004");
                }
                
                if(is_array($arr)) {
                    $repeated = "";
                    foreach($arr as $$var) {
                        if($node->hasAttribute("sl-hide")) {
                            $expr = $node->getAttribute("sl-hide");
                            $node->removeAttribute("sl-hide");
                            if($this->sl_hide($expr, $this, array($var => $$var))) {
                                $node->outertext = "";
                                return;
                            }
                        }
                        $parsed = $outer;
                        $max_matches = 300;
                        $match_num = 0;
                        $match = array();
                        preg_match("/\{\:.*?\:\}/", $parsed, $match, PREG_OFFSET_CAPTURE);
                        do {
                            if(!isset($match[0][0])) { continue; }
                            $expr = substr($match[0][0], 2, strlen($match[0][0]) - 4);
                            $ex   = explode(".", $expr);
                            $v    = null;
                            
                            $p = null;
                            $n = null;
                            
                            if(count($ex) > 1) {
                                $p = $ex[0];
                                $n = $ex[1];
                                
                                $ip = $$p;
                                
                                if(property_exists($ip, $n)) {
                                    $v = $ip->{$n};
                                } else {
                                    $v = "[SLF Error: No property " . $n . " found.]";
                                }
                            } else {
                                $p = $expr;
                                
                                $ip = $$p;
                                
                                if(property_exists($ip->{$p}, $p)) {
                                    $v = $ip->{$p};
                                } else {
                                    $v = "[SLF Error: No property " . $p . " found.]";
                                }
                            }
                            
                            $parsed = substr_replace($parsed, $v, $match[0][1], strlen($match[0][0]));
                            preg_match("/\{\:.*?\:\}/", $parsed, $match, PREG_OFFSET_CAPTURE);
                            ++$match_num;
                        } while(count($match) > 0 && $match_num < $max_matches);
                        $repeated .= $parsed;
                    }
                    
                    $node->outertext = "<!-- START SL-REPEAT: " . $prop . " -->\n" . $repeated . "<!-- END SL-REPEAT: " . $prop . " -->";
                } else {
                    show_error("The property " . $prop . " in your controller is not an array, which is required for a sl-repeat", "0x0003");
                }    
            } else {
                show_error("Your contoller does not contain the property: " . $prop, "0x0002");
            }
        }

        if($node->hasAttribute("sl-hide")) {
            $expr = $node->getAttribute("sl-hide");
            $node->removeAttribute("sl-hide");
            if($this->sl_hide($expr, $this)) {
                $node->outertext = "";
                return;
            }
        }
    }

    public function load_view($file) {
        return $this->load->view("blocks/".$this->skin_name."/".$file, null, true);
    }

	public function load_view($file) {
		return $this->load->view("blocks/".$this->skin_name."/".$file, null, true);
	}
    
    public function parse_node($node) {
        // echo $node->tag;
        // Set $name to the name of the element
        $name = $node->tag;
        
        // Root and Unknown elements contain no attributes therefore we aren't interested in them
        // they could cause errors if we let them go though so we skip over them.
        if($name == "root" || $name == "unknown") {
            return;
        }
        
        //echo $name . "<br/>";
        
        // Check our node for attributes
        $this->check_attributes($node);
        
        // Check to see if the current tag is a block
        if(in_array($name, $this->skin->blocks)) {
            $this->$name->run($node);
        }
    }
    
	public function set_skin($skin) {
		$this->skin_name = $skin;
	}
	
	private function controller_get($name){
        $exp = explode(".", $name);
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
    
    private function controller_has($name) {
        $exp = explode(".", $name);
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
    
    public function run($node) {
        // Pass this to our decendants
    }
        
    public function call($node, $view = null) {
        // Traverse through the block but skip the root element so
        // the root element doesn't try to run this block again in an inifite loop
        
        $output = "";
        
        if($view != null) {
            $html = new simple_html_dom();
            $html->load($view);
            $this->traverse($html->root);
            $output = $html->save();
        } else {
            $this->traverse($node, true);
            
            $output = $node->outertext;
        }
        
        $max_matches = 300;
        $match_num = 0;
        $match = array();
        preg_match("/\{\:.*?\:\}/", $output, $match, PREG_OFFSET_CAPTURE);
        if(count($match) > 0) {
            do {
                if(!isset($match[0][0])) { continue; }
                $expr = substr($match[0][0], 2, strlen($match[0][0]) - 4);
                if($expr[0] == "?") {
                    $output = substr_replace($output, "{{".substr($expr, 1)."}}", $match[0][1], strlen($match[0][0]));
                    break;
                }
                
                if($this->controller_has($expr)){
                    $v = $this->controller_get($expr);
                }else if($this->globals->has($expr)) {
                    //$v = $this->i->globals->{$p}->{$n};
                    $v = $this->globals->get($expr);
                }else{
                    $v = "[SLF Error: No property " . $expr . " found.]";
                }
                
                
                $output = substr_replace($output, $v, $match[0][1], strlen($match[0][0]));
                preg_match("/\{\:.*?\:\}/", $output, $match, PREG_OFFSET_CAPTURE);
                ++$match_num;
            } while (count($match) > 0 && $match_num < $max_matches);
        }
        
        return $output;
    }
}
