<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token {
	public $name;

	public $value;

	public $type;

	public $global;
	
	public $left;
	
	public $right;

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

class IdentifierReader {
    public static function ReadIdentifier($first, $input, $global = false) {
        $identifier = $first;
        $escapes = array(
            "(",
            ")",
            ".",
            ",",
            " ",
            "\t",
            "\n",
            "\r",
            ";"
        );
        $escaped = false;
        while(!$escaped) {
            $ch = $input->next();
            if(ctype_alnum($ch) || $ch == "_") {
                $identifier .= $ch;
            } else if(in_array($ch, $escapes) || $ch === null) {
                $input->index -= 1;
                $escaped = true;
                continue;
            } else {
                show_error("Unexpected character: " . $ch .  " at line: 1, character: " . $input->index, 200, "Lexing Error!");
            }
        }
        return new Token("Identifier", $identifier, "Identifier", $global);
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
	
	public function get_left() {
	    if(isset($this->tokens[count($this->tokens) - 1])) {
	        return $this->tokens[count($this->tokens) - 1];
	    }
	    return null;
	}
	
	public function tokenize() {
	    
    	while(($ch = $this->input->next()) !== null) {
    	    $token = null;
    	    switch($ch) {
    	        case "":
    	        case " ":
    	        case "\r":
    	        case "\t":
    	        case "\n":
    	            continue;
    	        case "'":
    	            $token = StringReader::ReadString($this->input);
    	            $this->add_token($token);
    	            break;
    	        case ".":
    	            $this->add_token(new Token("Accessor","->","Accessor"));
    	            break;
    	        case "$":
    	            $token = IdentifierReader::ReadIdentifier($this->input, true);
    	            $token->left = $this->get_left();
    	            $this->add_token($token);
    	            break;
    	        case "?":
    	        case "(":
    	        case ")":
    	        case "_":
    	        case "-":
    	        case ":":
    	        case "+":
    	        case ";":
    	            $this->add_token(new Token("Operator", $ch, "Operator"));
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
    	            
    	            $token = IdentifierReader::ReadIdentifier($ch, $this->input, false);
    	            $token->left = $this->get_left();
    	            $this->add_token($token);
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
	            case "Global":
	                $output .= '$this->globals->'.$token->value;
	                break;
	            case "Accessor":
	                $output .= "->";
	                break;
	            case "Identifier":
	                if($token->global) {
	                    if(isset($token->left)) {
	                        if(!$token->left->type != "Accessor") {
	                            $output .= '$inst->globals->';
	                        }
	                    } else {
	                        $output .= '$inst->globals->';
	                    }
	                    $output .= '$inst->globals->'.$token->value;
	                }else{
	                    if(isset($token->left)) {
	                        if($token->left->type != "Accessor") {
	                            $output .= '$inst->';
	                        }
	                    } else {
	                        $output .= '$inst->';
	                    }
	                    $output .= $token->value;
	                }
	                break;
	            case "Operator":
	                $output .= $token->value;
	                break;
	            case "Boolean":
	                $output = ($token->value === 0) ? "false" : "true";
	                break;
	        }
	    }
	    if($output[strlen($output) - 1] != ";") {
	        $output .= ";";
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