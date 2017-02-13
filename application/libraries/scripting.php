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
	
	public function set_left($token) {
	    $this->left = $token;
	}
	
	public function set_right($token) {
	    $this->right = $token;
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
		$ch = $input->next();
        while($ch !== null) {
            if(!$escape && ($ch == "'" || $ch == "\"")) {
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
        	$ch = $input->next();
        }
        show_error: {
            show_error("Unterminated string literal at line: 1, character: " . $input->index. ", input: '" . $string,"200","Lexing Error!");
        }
    }
}

class NumberReader {
    public static function ReadNumber($first, $input) {
        $is_float = false;
        $number = $first;
        $escapes = array(
            "(",
            ")",
            ",",
            " ",
            "\t",
            "\n",
            "\r",
            ";",
            "=",
            "?",
            ":",
            "+",
        );
        $escaped = false;
        while(!$escaped) {
            $ch = $input->next();
            if(is_numeric($ch)) {
                $number .= $ch;
            } else if($ch === ".") {
                $is_float = true;
                $number .= ".";
                continue;
            } else if(in_array($ch, $escapes) || $ch === null) {
                $input->index -= 1;
                $escaped = true;
                continue;
            } else {
                show_error("Unexpected character: " . $ch .  " expected number at line: 1, character: " . $input->index, 200, "Lexing Error!");
            }
        }
        if($is_float) {
            return new Token("Float", $number, "Number");
        } else {
            return new Token("Integer", $number, "Number");
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
            ";",
            "=",
            "?",
            ":",
            "+",
            "-",
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
	private $inst;
    
	public function __construct($stream, $inst, $locals) {
	    $this->input = $stream;
	    $this->tokens = array();
		$this->inst = $inst;
		$this->locals = $locals;
	}
	
	public function add_token($token) {
	    array_push($this->tokens, $token);
	}
	
	public function get_left() {
	    if(isset($this->tokens[count($this->tokens) - 2])) {
	        return $this->tokens[count($this->tokens) - 2];
	    }
	    return null;
	}
	
	public function current_token() {
	    if(isset($this->tokens[count($this->tokens) - 1])) {
	        return $this->tokens[count($this->tokens) - 1];
	    }
	    return null;
	}
	
	public function update() {
	    $token = $this->get_left();
	    $current = $this->current_token();
	    if(isset($current)) {
	        $current->set_left($token);
	    }
	    if(isset($token)) {
	        $token->set_right($current);
	    }
	}
	
	public function tokenize() {
		$ch = $this->input->next();
    	while($ch !== null) {
    		//var_dump($this->input);
    		//echo "<br/>".$ch."<br/>";
    	    $token = null;
    	    switch($ch) {
    	        case "":
    	        case " ":
    	        case "\r":
    	        case "\t":
    	        case "\n":
    	            continue;
				case "\"":
    	        case "'":
    	            $token = StringReader::ReadString($this->input);
    	            $this->add_token($token);
    	            break;
    	        case "-":
					if($this->input->peek() != ">") {
						break;
					}
					$this->input->next();
    	            $this->add_token(new Token("Accessor","->","Accessor"));
    	            break;
				case ".":
					$this->add_token(new Token("Operator",".","Concatenation"));
					break;
    	        case "$":
    	            $token = IdentifierReader::ReadIdentifier($ch, $this->input, true);
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
    	        case "=":
				case ">":
				case "<":
				case "!":
				case ",":
				case "|":
				case "&":
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
    	            
    	            if(is_numeric($ch)) {
    	                $token = NumberReader::ReadNumber($ch, $this->input, false);
        	            $this->add_token($token);
    	            }
    	            
    	            if(ctype_alpha($ch)) {
        	            $token = IdentifierReader::ReadIdentifier($ch, $this->input, false);
        	            $this->add_token($token);
    	            }
    	            break;
    	    }
    	    $this->update();			
    		$ch = $this->input->next();
    	}
    	
    	
    	return $this;
	}

	public function handle_ident($token) {
		/*if(isset($token->right) && isset($token->left)) {
			if($token->right->type == "Operator" && $token->right->value == "=" && ($token->right->right->type != "Operator" && $token->right->right->value != "=") && ($token->left->type != "Accessor" && $token->left->value != "->")) {
				return '$inst->'.$token->value;
			}
		}*/
		
		if($token->global) {
			return '$inst->globals->'.substr($token->value, 1);
		}
		
		if(isset($token->left) && $token->left->value != ";") {
			if($token->left->type == "Accessor" && $token->left->value == "->") {
				return $token->value;
			}
			
			if($token->left->type == "Operator" && $token->left->value == "=") {
				return '$inst->'.$token->value;
			}
			
			if(isset($this->locals->{$token->value}) || method_exists($this->locals, $token->value)) {
				return '$locals->'.$token->value;
			} else {
				if(isset($this->inst->{$token->value}) || method_exists($this->inst, $token->value)) {
					return '$inst->'.$token->value;
				}							
				return $token->value;
			}
		}else{
			//var_dump($this->inst);
			if(isset($this->locals->{$token->value}) || method_exists($this->locals, $token->value)) {
				return '$locals->'.$token->value;
			} else {
				if(isset($this->inst->{$token->value}) || method_exists($this->inst, $token->value)) {					
					return '$inst->'.$token->value;
				}			
				
				if(isset($token->right)) {
					if(isset($token->right->right)) {
						if(($token->right->right->value != "=" || $token->right->right->value != "+"  || $token->right->right->value != "-" || $token->right->right->value != "*" || $token->right->right->value != "!") && $token->right->value == "=") {
							return '$inst->'.$token->value;
						}
					}
				}				
				return $token->value;
			}
		}
	}
	
	public function __toString() {
	    $output = "";
		//echo "<pre>";
		//var_dump($this->tokens);
		//echo "</pre>";
		//echo "<br/>";
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
	                $output .= $this->handle_ident($token);
	                break;
    	        case "Number":
    	            $output .= $token->value;
    	            break;
				case "Concatenation":
					$output .= $token->value;
					break;
	            case "Operator":
	                	$output .= $token->value;
	                break;
	            case "Boolean":
	                $output .= ($token->value === 0) ? "false" : "true";
	                break;
	        }
	    }
       
       if(strlen($output) > 0) {
   	    if($output[strlen($output) - 1] != ";") {
   	        $output .= ";";
   	    }
       }
       
	    return $output;
	}
}

class Scripting {
    public function __construct() {
        $this->i =& get_instance();
    }
    
    public function tokenize($string, $inst, $locals) {
    	$this->input = new InputStream($string);
    	$this->lexer = new Lexer($this->input, $inst, $locals);
    	return $this->lexer->tokenize();
    }
	
	public function evaluate($code, $inst, $locals = NULL, $return = true) {
		if(!isset($vars)) {
			$vars = array();
		}
      
		return call_user_func(function() use($code, $inst, $locals, $return) {			
			$php = $this->tokenize($code, $inst, $locals);
			
			//echo $php."<br/>";
			
			$ret = null;
			//echo $php."<br/>\n";
			try {
				$ret = eval((($return) ? 'return ': '') . $php . ';');
			} catch(Exception $e) {
				echo $php;
				return null;
			}
			
			return $ret;
		});
	}
}