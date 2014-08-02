<?php

class RGB
{
  // Based on algorithm presented here:
  // http://www.runtime-era.com/2011/11/grouping-html-hex-colors-by-hue-in.html
  public static function to_hsv($red, $green, $blue)
  {
    // Normalize RGB channels between 0 and 1
    $red /= 255;
    $green /= 255;
    $blue /= 255;

    // Find minimum and maximum of RGB channels
    $min_rgb = min($red, $green, $blue);
    $max_rgb = max($red, $green, $blue);

    // Initialize
    $chroma = $max_rgb - $min_rgb;
    $hue = 0.0;
    $saturation = 0.0;
    $value = $max_rgb;

    // Compute HSV
    if ($value > 0) {
      // Compute saturation only if value isn't 0
      $saturation = $chroma / $value;
      if ($saturation > 0) {
        if ($red === $max_rgb) {
          $hue = 60*((($green-$min_rgb)-($blue-$min_rgb))/$chroma);
          if ($hue < 0) {
            $hue += 360;
          }
        } else if ($green === $max_rgb) {
          $hue = 120+60*((($blue-$min_rgb)-($red-$min_rgb))/$chroma);
        } else if ($blue === $max_rgb) {
          $hue = 240+60*((($red-$min_rgb)-($green-$min_rgb))/$chroma);
        }
      }
    }

    return array($hue, $saturation, $value);
  }
}
