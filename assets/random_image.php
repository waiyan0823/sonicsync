<?php
header('Content-Type: image/svg+xml');

$palettes = [
    ['#1a1a2e','#16213e','#0f3460','#e94560','#533483'],
    ['#0f0c29','#302b63','#24243e','#d4a5a5','#7b2d8e'],
    ['#2c3e50','#8e44ad','#3498db','#e74c3c','#f39c12'],
    ['#0b0c10','#1f2833','#45a29e','#66fcf1','#c5c6c7'],
    ['#2d2d2d','#4a4a4a','#7a7a7a','#b8b8b8','#e0e0e0'],
    ['#1a3a2a','#2d5a3e','#4a7c59','#68a678','#a8d5ba'],
    ['#2b1a3a','#4a2b5a','#6b3a7a','#8a5a9a','#b87ad4'],
    ['#1a2a3a','#2a4a5a','#3a6a7a','#5a8a9a','#7aaaba'],
];

$shapes = ['circle','triangle','mountain','wave','sun','star','tree','cloud','spiral'];

$palette = $palettes[array_rand($palettes)];
$bg = $palette[0];
$w = 400; $h = 300;

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
$svg .= '<rect width="'.$w.'" height="'.$h.'" fill="'.$bg.'"/>';

// Random gradient background
$grad_id = 'g'.mt_rand();
$g1 = $palette[array_rand($palette)];
$g2 = $palette[array_rand($palette)];
$svg .= '<defs><linearGradient id="'.$grad_id.'" x1="0%" y1="0%" x2="100%" y2="100%">';
$svg .= '<stop offset="0%" style="stop-color:'.$g1.';stop-opacity:0.3" />';
$svg .= '<stop offset="100%" style="stop-color:'.$g2.';stop-opacity:0.1" />';
$svg .= '</linearGradient></defs>';
$svg .= '<rect width="'.$w.'" height="'.$h.'" fill="url(#'.$grad_id.')"/>';

// Draw 8-15 random elements
$count = mt_rand(8, 15);
for ($i = 0; $i < $count; $i++) {
    $color = $palette[array_rand($palette)];
    $x = mt_rand(0, $w);
    $y = mt_rand(0, $h);
    $size = mt_rand(20, 120);
    $opacity = mt_rand(10, 50) / 100;
    $shape = $shapes[array_rand($shapes)];

    switch ($shape) {
        case 'circle':
            $r = $size / 2;
            $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="'.$r.'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="'.($r*0.6).'" fill="'.$palette[array_rand($palette)].'" opacity="'.($opacity*0.8).'"/>';
            break;
        case 'triangle':
            $p1x = $x; $p1y = $y - $size/2;
            $p2x = $x - $size/2; $p2y = $y + $size/2;
            $p3x = $x + $size/2; $p3y = $y + $size/2;
            $svg .= '<polygon points="'.$p1x.','.$p1y.' '.$p2x.','.$p2y.' '.$p3x.','.$p2y.'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            break;
        case 'mountain':
            $svg .= '<polygon points="'.($x-$size).','.$y.' '.$x.','.($y-$size*0.8).' '.($x+$size).','.$y.'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            $svg .= '<polygon points="'.($x-$size*0.3).','.$y.' '.($x+$size*0.2).','.($y-$size*0.5).' '.($x+$size*0.7).','.$y.'" fill="'.$palette[array_rand($palette)].'" opacity="'.($opacity*0.7).'"/>';
            break;
        case 'wave':
            $svg .= '<path d="M0,'.$y.' Q'.($x-$size*0.5).','.($y-$size).' '.$x.','.$y.' T'.($x+$size*1.5).','.$y.'" stroke="'.$color.'" stroke-width="3" fill="none" opacity="'.$opacity.'"/>';
            break;
        case 'sun':
            $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="'.($size/3).'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            for ($a = 0; $a < 8; $a++) {
                $angle = deg2rad($a * 45);
                $ex = $x + cos($angle) * ($size/2);
                $ey = $y + sin($angle) * ($size/2);
                $svg .= '<line x1="'.$x.'" y1="'.$y.'" x2="'.$ex.'" y2="'.$ey.'" stroke="'.$color.'" stroke-width="2" opacity="'.($opacity*0.6).'"/>';
            }
            break;
        case 'star':
            $points = '';
            for ($a = 0; $a < 10; $a++) {
                $angle = deg2rad($a * 36 - 90);
                $r2 = ($a % 2 === 0) ? $size/2 : $size/5;
                $points .= ($x + cos($angle) * $r2).','.($y + sin($angle) * $r2).' ';
            }
            $svg .= '<polygon points="'.$points.'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            break;
        case 'tree':
            $svg .= '<rect x="'.($x-3).'" y="'.($y-$size*0.3).'" width="6" height="'.($size*0.3).'" fill="'.$palette[array_rand($palette)].'" opacity="'.$opacity.'"/>';
            $svg .= '<circle cx="'.$x.'" cy="'.($y-$size*0.5).'" r="'.($size*0.4).'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            break;
        case 'cloud':
            $cy = $y - $size/3;
            $svg .= '<ellipse cx="'.$x.'" cy="'.$cy.'" rx="'.($size/3).'" ry="'.($size/5).'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            $svg .= '<ellipse cx="'.($x-$size/5).'" cy="'.$cy.'" rx="'.($size/4).'" ry="'.($size/6).'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            $svg .= '<ellipse cx="'.($x+$size/5).'" cy="'.$cy.'" rx="'.($size/4).'" ry="'.($size/6).'" fill="'.$color.'" opacity="'.$opacity.'"/>';
            break;
        case 'spiral':
            $svg .= '<path d="M'.$x.','.$y.' Q'.($x+$size/2).','.($y-$size/2).' '.($x+$size).','.$y.' T'.$x.','.($y+$size).'" stroke="'.$color.'" stroke-width="2" fill="none" opacity="'.$opacity.'"/>';
            break;
    }
}

// Add small dots scattered
for ($i = 0; $i < mt_rand(15, 30); $i++) {
    $dx = mt_rand(0, $w);
    $dy = mt_rand(0, $h);
    $ds = mt_rand(2, 6);
    $dc = $palette[array_rand($palette)];
    $svg .= '<circle cx="'.$dx.'" cy="'.$dy.'" r="'.$ds.'" fill="'.$dc.'" opacity="'.(mt_rand(20,60)/100).'"/>';
}

$svg .= '</svg>';
echo $svg;
