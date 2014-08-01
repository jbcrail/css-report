<?php

/*
 * A subset implementation of http://www.w3.org/TR/CSS2/syndata.html
 *
 * Note: Only rulesets with declarations are handled. At-rules are
 * ignored and will break the parser.
 *
 * I used a quick-and-dirty state machine instead of regular
 * expressions for better parsing. I minimized the usage of regular
 * expressions to only splitting strings in order to avoid potential
 * exploits.
 *
 * Also, the parser minimizes memory usage by looking at the CSS one
 * chunk at a time, instead of reading everything into memory.
 */
class CssParser
{
  const STATE_SELECTOR = 0;
  const STATE_PROPERTY = 1;
  const STATE_VALUE    = 2;

  const END_SELECTOR     = "{";
  const END_PROPERTY     = ":";
  const END_DECLARATION  = ";";
  const END_BLOCK        = "}";

  const CHUNK_SIZE = 16*1024;

  private $selectors = array();
  private $declarations = array();

  private function strip_comments($str)
  {
    while (($pos = strpos($str, "/*")) !== false) {
      $pos2 = strpos($str, "*/");
      $str = substr_replace($str, "", $pos, $pos2-$pos+2);
    }
    return $str;
  }

  private function parse_selector($str)
  {
    return array_map('trim', explode(",", $this->strip_comments($str)));
  }

  private function parse_property($str)
  {
    return trim($this->strip_comments($str));
  }

  private function parse_value($str)
  {
    return trim($this->strip_comments($str));
  }

  public function __construct($filename)
  {
    $state = self::STATE_SELECTOR;
    $token = "";

    $f = fopen($filename, "rb");
    while (!feof($f)) {
      $contents = fread($f, self::CHUNK_SIZE);
      $len = strlen($contents);
      for ($i = 0; $i < $len; $i++) {
        if ($state === self::STATE_SELECTOR && $contents[$i] === self::END_SELECTOR) {
          $state = self::STATE_PROPERTY;
          $this->selectors = array_merge($this->selectors, $this->parse_selector($token));
          $token = "";
        } else if ($state === self::STATE_SELECTOR) {
          $token .= $contents[$i];
        } else if ($state === self::STATE_PROPERTY && $contents[$i] === self::END_PROPERTY) {
          $state = self::STATE_VALUE;
          $property = $this->parse_property($token);
          $token = "";
        } else if ($state === self::STATE_PROPERTY && $contents[$i] === self::END_BLOCK) {
          $state = self::STATE_SELECTOR;
          $token = "";
        } else if ($state === self::STATE_PROPERTY) {
          $token .= $contents[$i];
        } else if ($state === self::STATE_VALUE && $contents[$i] === self::END_DECLARATION) {
          $state = self::STATE_PROPERTY;
          $this->declarations[] = array("property" => $property, "value" => $this->parse_value($token));
          $token = "";
        } else if ($state === self::STATE_VALUE && $contents[$i] === self::END_BLOCK) {
          $state = self::STATE_SELECTOR;
          $this->declarations[] = array("property" => $property, "value" => $this->parse_value($token));
          $token = "";
        } else if ($state === self::STATE_VALUE) {
          $token .= $contents[$i];
        }
      }
    }
    fclose($f);
  }

  public function getSelectors()
  {
    return $this->selectors;
  }

  public function getDeclarations()
  {
    return $this->declarations;
  }

  // All hex-notation colors available in the declarations values are
  // returned. This can be enhanced for RGB and named colors.
  public function getColors()
  {
    $colors = array();
    foreach ($this->declarations as $declaration) {
      foreach (preg_split("/[\s]+/", $declaration['value']) as $word) {
        if (isset($word[0]) && $word[0] === "#") {
          $colors[] = $word;
        }
      }
    }
    return $colors;
  }
}
