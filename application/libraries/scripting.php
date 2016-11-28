<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token {
	public $name;

	public $value;

	public $type;

	public $global;

	public function __construct($name, $value, $type, $global = false) {
		$this->name = $name;
		$this->value = $value;
		$this->type = $type;
		$this->global = $global;
	}
}

class InputStream {
	public $source;
	public $length;
	public $current_char;
	public $index = -1;

	public function __construct($source) {
		$this->source = $source;
		$this->length = strlen($source) - 1;
	}

	public function next() {
		++$this->index;
		if($this->index <= $this->length) {
			return $this->source[$this->index];
		} else {
			return null;
		}
	}
	
	public function chomp($n) {
	    for($i = 0; $i < $n; ++$i) {
	        $this->next();
	    }
	}

	public function peek_string($string) {
        $index = -1;
        $length = strlen($string) - 1;
        $ch = "";
        while($index < $length) {
            ++$index;
            $peek = $this->peek($index);
            $ch = $string[$index];
            if($peek != $ch) {
                return false;
            }
        }
        $this->chomp($length);
        return true;
	}

	public function peek($n = 1) {
        if($this->index <= $this->length) {
            return $this->source[$this->index + $n];
        } else { 
            return null;
        }
	}
}

class StringReader {
    public static function ReadString($input) {
        $escape = false;
        $string = "";
        while(($ch = $input->next()) !== null) {
            if(!$escape && $ch == "'") {
                return new Token("String", $string, "String");
            } else if($escape) {
                $escape = false;
            } else {
                if($ch == "\\") {
                    $peek = $input->peek();
                    if($peek === null) {
                        show_error("Escaped chracter not found at line: 1, character: " . $input->index. ", input: " . $string, "200", "Lexing Error!");
                    }
                    
                    if($peek == "'") {
                        $string .= "\\'";
                        $escape = true;
                        continue;
                    }
                }
                
                $string .= $ch;
            }
        }
        show_error: {
            show_error("Unterminated string literal at line: 1, character: " . $input->index. ", input: '" . $string,"200","Lexing Error!");
        }
    }
}

class Lexer {
    private $input;
    
	public function __construct($stream) {
	    $this->input = $stream;
	    $this->tokens = array();
	}
	
	public function add_token($token) {
	    array_push($this->tokens, $token);
	}
	
	public function tokenize() {
	    
    	while(($ch = $this->input->next()) !== null) {
    	    $token = null;
    	    switch($ch) {
    	        case "":
    	        case " ":
    	        case "\t":
    	        case "\n":
    	            continue;
    	        case "'":
    	            $token = StringReader::ReadString($this->input);
    	            $this->add_token($token);
    	            break;
    	        case "$":
    	            
    	            break;
    	        case ".":
    	            
    	            break;
    	        default:
    	            if($this->input->peek_string("true")) {
    	                $this->add_token(new Token("True", 1, "Boolean"));
    	                continue;
    	            }
    	            
    	            if($this->input->peek_string("false")) {
    	                $this->add_token(new Token("False", 0, "Boolean"));
    	                continue;
    	            }
    	            break;
    	    }
    	}
    	
    	return $this;
	}
	
	public function __toString() {
	    $output = "";
	    foreach($this->tokens as $token) {
	        switch($token->type) {
	            case "String":
	                $output .= "'".$token->value."'";
	                break;
	            case "Boolean":
	                $output = ($token->value === 0) ? "false" : "true";
	                break;
	        }
	    }
	    return $output;
	}
}

class Scripting {
    public function __construct() {
        $this->i =& get_instance();
    }
    
    public function tokenize($string) {
    	$this->input = new InputStream($string);
    	$this->lexer = new Lexer($this->input);
    	echo $this->lexer->tokenize();
    }
	
	public function evaluate($code, $inst, $return = true, $vars = array()) {
		log_message("info", $this->tokenize($code));
		return call_user_func(function() use($code, $inst, $vars, $return) {
			$vars_exp = "";
			foreach($vars as $k => $v) {
				$vars_exp .= "$".$k . "=" . var_export($b) . ";";
			}
			//echo 'return ' . $vars_exp . ' ' . $code . ';';
			return eval((($return) ? 'return ': '') . $vars_exp . ' ' . $code . ';');
		});
	}
}