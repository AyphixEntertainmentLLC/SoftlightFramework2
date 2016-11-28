<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH."vendor/simple_html_dom.php";

class Skin {
    public function __construct() {
        // Get the CodeIgniter instance to use the internal classes
        $this->i =& get_instance();
        // Initialize the HTML rader
        $this->html = new simple_html_dom();
        // Initialize the controller member
        $this->controller = null;
        // This holds the blocks that the system can execute
        $this->blocks = array();
        // This will hold the content of our page
        $this->content = "";
        // This will hold the skin that the user selected
        $this->skin = "";
    }
    
    public function redirect($url, $time) {
        header("Refresh:" . $time . "; url=" . $url);
    }
    
    public function set_content($content) {
        $this->content = $content;
    }
    
    public function load_page($page) {
        $this->set_content($this->i->load->view("themes/".$this->skin."/pages/".$page.".php", null, true));
    }
    
    public function load_skin($skin) {
        $this->skin = $skin;
    }
	
	public function get_pages($skin) {
		$output = array();
		foreach(array_diff(scandir(APPPATH."views/themes/".$skin."/pages/"), array(".", "..")) as $files) {
			if($files !== "." || $files !== "..") {
				array_push($output, ucfirst(substr($files, 0, strlen($files) - 4)));
			}
		}
		return $output;
	}
    
    public function show($inst) {
        // Set our controller to the instance of the controller that called this method
        $this->controller =& $inst;
        
        // Load our view into the $code variable for proccessing
        $code = $this->i->load->view("themes/".$this->skin."/index.php", null, true);
        
        // Load the blocks config file
        $this->i->config->load("blocks");
       
        // Get the blocks from the config file and store them here 
        $this->blocks = $this->i->config->item("blocks");
        
        // Load the base Model class (this allows the other classes to load without errors)
        $this->i->load->model("block");
        
        // Foreach through our blocks and load them
        foreach($this->blocks as $block) {
            $this->i->load->model("blocks/".$block);
        }
        
        // Read our HTML code
        $this->html->load($code);
        
        // Find our content tag
        $content = $this->html->find("content");
        
        // Did our search for the content yield any results?
        if(count($content) !== 0) {
            if(is_object($content[0])) {
                // Replace the contet tag with the content of the page
                $content[0]->outertext = $this->content;
            }
        }
        
        // Reload the page so the content get's parsed as well
        $this->html->load($this->html->save());
        
        // Traverse the HTML
        $this->traverse($this->html->root);
        
        // Setup our replacement variables
        $data = array(
            "base_url"    => $this->i->config->item("base_url"),
            "skin_url"    => $this->i->config->item("base_url") . "application/views/themes/".$this->skin."/",
            "storage_url" => $this->i->config->item("base_url") . "application/storage/",
            "avatar_url"  => $this->i->config->item("base_url") . "application/storage/avatars/"
        );
        
        // Set the final output of the html
        $output = $this->html->save();
        
        $max_matches = 300;
        $match_num = 0;
        $match = array();
        preg_match("/\{\{.*?\}\}/", $output, $match, PREG_OFFSET_CAPTURE);
        if(count($match) > 0) {
            do {
                $expr = substr($match[0][0], 2, strlen($match[0][0]) - 4);
                
                if($this->controller_has($expr)){
                    $v = $this->controller_get($expr);
                }else if($this->i->globals->has($expr)) {
                    //$v = $this->i->globals->{$p}->{$n};
                    $v = $this->i->globals->get($expr);
                }else{
                    $v = "[SLF Error: No property " . $expr . " found.]";
                }
                
                
                $output = substr_replace($output, $v, $match[0][1], strlen($match[0][0]));
                preg_match("/\{\{.*?\}\}/", $output, $match, PREG_OFFSET_CAPTURE);
                ++$match_num;
            } while (count($match) > 0 && $match_num < $max_matches);
        }
        
        $this->i->parser->parse_string($output, $data);
    }
    
    private function controller_get($name){
        $exp = explode(".", $name);
        $last = null;
        if(count($exp) < 1) {
            return $this->controller->{$name};
        }else{
            $last = $this->controller;
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
            if(isset($this->controller->{$name})) {
                return true;
            } else{
                return false;
            }
        }else{
            $last = $this->controller;
            foreach($exp as $var) {
                if(!isset($last->{$var})){
                    return false;
                }
                $last = $last->{$var};
            }
            
            return true;
        }
    }
    
    private function traverse($node) {
        // Proccess the node
        $prevent = $this->parse_node($node);
        // Does the node have children elements?
        if(count($node->children()) > 0 && !$prevent) {
            // Loop through them and recurse down
            foreach($node->children() as $child) {
                $this->traverse($child);
            }
        }
    }
	
	private function sl_hide($expr, $controller) {
		return $this->i->scripting->evaluate($expr, $controller);
	}
    
    private function check_attributes($node) {
        // Does our node have Attributes we're looking for?
        if($node->hasAttribute("sl-init")) {
        	$expr = $node->getAttribute("sl-init");
			$node->removeAttribute("sl-init");
			
			$this->i->scripting->evaluate($expr, $this->controller, false);
        }
        
        if($node->hasAttribute("sl-repeat")) {
            $exp = $node->getAttribute("sl-repeat");
            $node->removeAttribute("sl-repeat");
            $outer = $node->outertext;
			
			if($node->hasAttribute("sl-hide")) {
				$expr = $node->getAttribute("sl-hide");
				$node->removeAttribute("sl-hide");
				if($this->sl_hide($expr, $this->controller)) {
					$node->outertext = "";
					return;
				}
			}
            
            $expl = explode(" ", $exp);
            
            if(count($expl) <> 3) {
                show_error("Your SL-REPEAT attribute did not contain a valid expression: Invalid number of arguments", "0x0001");
            } 
            
            $var  = $expl[0];
            $prop = $expl[2];
            
            // Does our controller have a property by that name?
            if(isset($this->controller->{$prop}) || $this->i->globals->has($prop)) {
                // Get our property
                $arr = null;
                if(isset($this->controller->{$prop})) {
                    $arr = $this->controller->{$prop};
                } else if($this->i->globals->has($prop)) {
                    $arr = $this->i->globals->get($prop);
                } else {
                    var_dump($arr);
                    //show_error("The property " . $prop . " passed the initial check but could not be otherwise found.", "0x0004");
                }
                
                // Is our property an array?
                if(is_array($arr)) {
                    if(count($arr) < 1) {
                        $node->outertext = "<!-- START SL-REPEAT: " . $prop . " -->\n" . "<!-- END SL-REPEAT: " . $prop . " -->";
                        return;
                    }
                    $repeated = "";
                    foreach($arr as $$var) {
                        $parsed = $outer;
                        $max_matches = 300;
                        $match_num = 0;
                        $match = array();
                        preg_match("/\{\{.*?\}\}/", $parsed, $match, PREG_OFFSET_CAPTURE);
                        if(count($match) > 0) {
                            do {
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
                                    
                                    
                                    if(is_object($ip)) {
                                        if(property_exists($ip->{$p}, $p)) {
                                            $v = $ip->{$p};
                                        } else {
                                            $v = "[SLF Error: No property " . $p . " found.]";
                                        }
                                    }else{
                                        $v = $ip;
                                    }
                                }
                                
                                $parsed = substr_replace($parsed, $v, $match[0][1], strlen($match[0][0]));
                                preg_match("/\{\{.*?\}\}/", $parsed, $match, PREG_OFFSET_CAPTURE);
                                ++$match_num;
                            } while(count($match) > 0 && $match_num < $max_matches);
                            $repeated .= $parsed;
                        }else{
                            $repeated .= $outer;
                        }
                    }
                    // Replace the original tag with the repeated code
                    $node->outertext = "<!-- START SL-REPEAT: " . $prop . " -->\n" . $repeated . "<!-- END SL-REPEAT: " . $prop . " -->";
                } else {
                    $node->outertext = "<!-- START SL-REPEAT: " . $prop . " -->\n" . "<!-- END SL-REPEAT: " . $prop . " -->";
                    show_error("The property " . $prop . " in your controller is not an array, which is required for a sl-repeat", "0x0003");
                }    
            } else {
                $node->outertext = "<!-- START SL-REPEAT: " . $prop . " -->\n" . "<!-- END SL-REPEAT: " . $prop . " -->";
                show_error("Your contoller does not contain the property: " . $prop, "0x0002");
            }
        }

		if($node->hasAttribute("sl-hide")) {
			$expr = $node->getAttribute("sl-hide");
			$node->removeAttribute("sl-hide");
			if($this->sl_hide($expr, $this->controller)) {
				$node->outertext = "";
				return;
			}
		}
    }
    
    private function parse_node($node) {
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
        if(in_array($name, $this->blocks)) {
            // Run our block
            $this->i->$name->run($node);
            
            // Tell our traverser that the current node is a block
            return true;
        }
        
        // Current node is not a block
        return false;
    }
}