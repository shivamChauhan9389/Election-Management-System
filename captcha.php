<?php
session_start();

// Generate random alphanumeric string
$captcha_text = '';
$characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghijklmnpqrstuvwxyz0123456789';
for ($i = 0; $i < 6; $i++) {
    $captcha_text .= $characters[rand(0, strlen($characters) - 1)];
}

$_SESSION['captcha'] = $captcha_text;

// Create image
$width = 150;
$height = 50;
$image = imagecreatetruecolor($width, $height);

// Colors
$bg_color = imagecolorallocate($image, 255, 255, 255); // white
$text_color = imagecolorallocate($image, 0, 0, 0);     // black

// Fill background
imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// noise
for ($i = 0; $i < 1000; $i++) {
    $noise_color = imagecolorallocate($image, rand(150, 255), rand(150, 255), rand(150, 255));
    imagesetpixel($image, rand(0, $width), rand(0, $height), $noise_color);
}

// Add text
$font_size = 5; 
$x = 20;
$y = 15;
imagestring($image, $font_size, $x, $y, $captcha_text, $text_color);

// Output image
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
