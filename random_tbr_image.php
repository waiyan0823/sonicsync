<?php
declare(strict_types=1);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');

$seed = filter_input(INPUT_GET, 'seed', FILTER_VALIDATE_INT);
if ($seed === false || $seed === null) {
    $seed = 1;
}

$scene = abs($seed) % 6;
$gradients = [
    ['#ff9a62', '#512da8'],
    ['#0f2027', '#2c5364'],
    ['#16222a', '#3a6073'],
    ['#42275a', '#734b6d'],
    ['#134e5e', '#71b280'],
    ['#141e30', '#243b55'],
];
[$top, $bottom] = $gradients[$scene];

function stars(int $seed, int $count): string {
    mt_srand($seed);
    $output = '';
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(20, 780);
        $y = mt_rand(20, 250);
        $r = mt_rand(1, 3);
        $output .= "<circle cx=\"$x\" cy=\"$y\" r=\"$r\" fill=\"#fff\" opacity=\"0.7\"/>";
    }
    return $output;
}

$foreground = '';
switch ($scene) {
    case 0:
        $foreground = '<circle cx="620" cy="125" r="62" fill="#ffd180" opacity=".9"/>
            <path d="M0 360 Q180 270 360 350 T800 335 V520 H0Z" fill="#301b4f"/>
            <path d="M0 405 Q210 335 420 400 T800 385 V520 H0Z" fill="#16162b"/>
            <path d="M0 430 Q230 410 420 432 T800 420" fill="none" stroke="#ffc98b" stroke-width="8" opacity=".55"/>';
        break;
    case 1:
        $foreground = stars($seed, 28) . '<path d="M0 390 L80 390 80 230 150 230 150 350 225 350 225 175 300 175 300 390 390 390 390 260 470 260 470 390 555 390 555 205 650 205 650 390 735 390 735 285 800 285 V520 H0Z" fill="#080b12"/>
            <g fill="#ffd54f" opacity=".85"><rect x="245" y="210" width="12" height="18"/><rect x="275" y="245" width="12" height="18"/><rect x="585" y="240" width="12" height="18"/><rect x="615" y="290" width="12" height="18"/><rect x="105" y="270" width="12" height="18"/></g>';
        break;
    case 2:
        $foreground = '<circle cx="130" cy="105" r="48" fill="#d5f5e3" opacity=".75"/>
            <g fill="#101b20"><path d="M80 430 L145 185 210 430Z"/><path d="M190 430 L270 125 350 430Z"/><path d="M480 430 L555 145 630 430Z"/><path d="M600 430 L685 205 770 430Z"/></g>
            <path d="M330 520 Q370 360 405 330 Q445 365 485 520Z" fill="#d4a574" opacity=".75"/>';
        break;
    case 3:
        $foreground = '<g fill="none" stroke="#f8e1ff" stroke-width="9" opacity=".8"><circle cx="285" cy="245" r="105"/><circle cx="515" cy="245" r="105"/><path d="M365 235 Q400 185 435 235"/><path d="M255 245 Q285 270 315 245"/><path d="M485 245 Q515 215 545 245"/></g>
            <path d="M185 465 Q285 340 385 465" fill="#251638"/><path d="M415 465 Q515 340 615 465" fill="#251638"/>';
        break;
    case 4:
        $foreground = '<path d="M0 380 L145 250 240 330 390 155 560 350 665 265 800 390 V520 H0Z" fill="#193b46"/>
            <path d="M265 290 L390 155 475 255 420 235 385 270 350 235Z" fill="#e8f5e9" opacity=".9"/>
            <circle cx="665" cy="110" r="45" fill="#fff9c4" opacity=".85"/>';
        break;
    default:
        $foreground = stars($seed, 18) . '<circle cx="400" cy="125" r="55" fill="#fff" opacity=".65"/>
            <path d="M0 405 Q180 330 360 405 T800 395 V520 H0Z" fill="#0b1020"/>
            <g fill="#141e30"><circle cx="300" cy="330" r="34"/><path d="M250 445 Q300 355 350 445Z"/><circle cx="405" cy="315" r="38"/><path d="M345 445 Q405 340 465 445Z"/><circle cx="515" cy="335" r="32"/><path d="M470 445 Q515 365 560 445Z"/></g>';
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 520" role="img" aria-label="Random visual prompt for student description">
    <defs>
        <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="<?= $top ?>"/>
            <stop offset="1" stop-color="<?= $bottom ?>"/>
        </linearGradient>
    </defs>
    <rect width="800" height="520" fill="url(#sky)"/>
    <?= $foreground ?>
</svg>

