<?php
namespace Plotter;

final class FullAggregationStrategy extends AbstractFullStrategy
{

    private const toPlot = [
        'threads.time|stats.db.time' => 'r',
        'rewriting.total' => 'r',
        'rewritings.generation' => 'r'
    ];

    private const stackedMeasuresToPlot = [
        [
            2 => 'time'
        ]
    ];

    public function __construct()
    {
        parent::__construct();
        $this->setToPlot(self::toPlot);
        $this->setStackedMeasuresToPlot(self::stackedMeasuresToPlot);
    }

    public function getId(): string
    {
        return 'aggregation';
    }

    function groupTests(array $groupTests): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p), $groupTests));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $groupTests));
        $groups = \array_map(function ($p) {
            $dirName = \basename($p);
            \preg_match("#^\[(.+)(?:\..+)?\]#U", $dirName, $matches);
            return $matches[1];
        }, $dirs);
        $groups = \array_unique($groups, SORT_REGULAR);
        \natcasesort($groups);

        foreach ($groups as $groupName) {
            $regex = "#\[$groupName(?:\..+)?\]#U";
            $gdirs = \array_filter($dirs, fn ($d) => \preg_match($regex, $d));
            $gscores = \array_map(fn ($d) => $this->sortScore(\basename($d)), $gdirs);
            $gdirs = \array_map(null, $gscores, $gdirs);

            \usort($gdirs, function ($a, $b) {

                if ($a[0] !== $b[0])
                    return $a[0] - $b[0];

                return \strnatcasecmp($a[1], $b[1]);
            });
            $gdirs = \array_column($gdirs, 1);

            foreach ($queries as $query) {
                $dd = \array_map(fn ($p) => "$p/$query", $gdirs);
                $ret["{$groupName}_$query"] = \array_values($dd);
            }
        }
        return $ret;
    }
}