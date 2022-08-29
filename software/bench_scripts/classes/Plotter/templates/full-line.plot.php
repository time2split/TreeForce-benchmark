set terminal pngcairo size 1000,500
set key outside below horizontal maxcols 2
<?php
$plotConfig = $PLOTTER->plot_getConfig();

$xRangeMax = $plotConfig['plot.xrange.max'] ?? null;
$yRangeMax = $plotConfig['plot.yrange.max'] ?? null;

if ($yRangeMax !== null)
    echo "set yrange [0:$yRangeMax]\n";

echo <<<EOD
set xrange [0:$xRangeMax]
set xlabel "{$plotConfig['xlabel']}"
set ylabel "{$plotConfig['ylabel']}"
set format y "%gs"

EOD;

$lines = [];
$points = [];
$interpolate = [];
$i = 1;
$fit = "";

// Search if multiple dataset are used
{
    $groups = \array_keys($PLOTTER->getCsvGroups());
    $names = [];

    foreach ($groups as $g) {
        $elements = \Help\Plotter::extractDirNameElements($g);
        $names[] = $elements['group'];
    }
    $names = \array_unique($names);
    $hasMultipleDataset = \count($names) > 1;
}

foreach ($PLOTTER->getCsvGroups() as $group => $csvPaths) {
    $csvData = $PLOTTER->getCsvData(\Help\Arrays::first($csvPaths));
    $dirName = \basename(\dirname(\Help\Arrays::first($csvPaths)));

    $titleRef = \Plotter\AbstractFullStrategy::makeXTic_fromDirName($dirName);

    if ($hasMultipleDataset) {
        $elements = \Help\Plotter::extractDirNameElements($dirName);
        $titleRef = "{$elements['group']} $titleRef";
    }

    $titleRef = "\"$titleRef\"";

    $notitle = false;
    $styleReplacement = [
        'lc' => $i,
        'lt' => $i
    ];

    if ($plotConfig['plot.points']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $plotConfig['plot.points.style'];
        $style = \str_format($style, $styleReplacement);

        $points[] = "'$group.dat' u 2:($3/1000) with points $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.lines']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $plotConfig['plot.lines.style'];
        $style = \str_format($style, $styleReplacement);

        $lines[] = "'$group.dat' u 2:($3/1000) with lines $title $style";
        $notitle = true;
    }

    if ($plotConfig['plot.fit.linear']) {
        $title = $notitle ? "notitle" : "title $titleRef";
        $style = $plotConfig['plot.fit.linear.style'];
        $style = \str_format($style, $styleReplacement);

        echo "f$i(x) = a$i + b$i*x\n";
        echo "fit f$i(x) '$group.dat' u 2:($3/1000) via a$i,b$i\n";

        $interpolate[] = "f$i(x) $title $style";
    }
    $i ++;
}
$tmp = \array_merge($interpolate, $lines, $points);

echo "plot ", implode(",\\\n", $tmp), "\n";
