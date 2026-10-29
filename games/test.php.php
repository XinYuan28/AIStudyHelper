<?php
require_once __DIR__ . '/test.php';

$t = ($_GET['t'] ?? 'A') === 'B' ? 'B' : 'A';
if ($t === 'A') {
    $_SESSION['game_data'] = ["template"=>"A","title"=>"Photosynthesis","pairs"=>[
        ["term"=>"Chlorophyll","definition"=>"Light-absorbing pigment"],
        ["term"=>"Stomata","definition"=>"Gas exchange pores"],
        ["term"=>"Chloroplast","definition"=>"Site of photosynthesis"],
        ["term"=>"Glucose","definition"=>"Sugar produced"],
        ["term"=>"Oxygen","definition"=>"Released by-product"],
        ["term"=>"Carbon dioxide","definition"=>"Gas taken from air"],
        ["term"=>"Sunlight","definition"=>"Energy source"],
        ["term"=>"Xylem","definition"=>"Carries water upward"],
        ["term"=>"Phloem","definition"=>"Carries sugars around"],
        ["term"=>"Thylakoid","definition"=>"Light reaction membrane"],
        ["term"=>"Stroma","definition"=>"Calvin cycle fluid"],
        ["term"=>"ATP","definition"=>"Cell energy molecule"]]];
} else {
    $_SESSION['game_data'] = ["template"=>"B","title"=>"Photosynthesis","sentences"=>[
        "Photosynthesis converts light energy into chemical energy stored in glucose.",
        "Chlorophyll is the green pigment that absorbs sunlight inside the chloroplast.",
        "Stomata are tiny pores that let carbon dioxide enter and oxygen leave the leaf.",
        "The light reactions happen in the thylakoid membranes of the chloroplast.",
        "The Calvin cycle uses ATP to build sugar from carbon dioxide in the stroma.",
        "Water is absorbed by the roots and carried to the leaves through the xylem.",
        "Oxygen is released as a by-product when water molecules are split.",
        "Plants make their own food, so they are called producers in a food chain."]];
}
header("Location: play.php?t=$t");